"use client"

import { Suspense, useState, useEffect } from "react"
import { useSearchParams } from "next/navigation"
import Link from "next/link"
import { toast } from "sonner"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Progress } from "@/components/ui/progress"
import { Badge } from "@/components/ui/badge"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Check, CreditCard, Download, MessageSquare, Smartphone, Users, Zap } from "lucide-react"
import { useSubscription, useSubscriptionInvoices, useSubscriptionUsage, usePlans, type BillingInvoice } from "@/lib/api-hooks"
import { createCheckoutSession, createBillingPortalSession, createMpesaCheckout, createPaystackCheckout, verifyPaystackCheckout, cancelSubscription, previewCoupon } from "@/lib/api-actions"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

function SubscriptionPageContent() {
  const searchParams = useSearchParams()
  const { data: subscription, error, isLoading, mutate } = useSubscription()
  const { data: billingHistory = [] } = useSubscriptionInvoices()
  const { data: usageData } = useSubscriptionUsage()
  const { data: plansData = [] } = usePlans()
  const [checkoutPlanId, setCheckoutPlanId] = useState<string | null>(null)
  const [portalLoading, setPortalLoading] = useState(false)
  const [checkoutMessage, setCheckoutMessage] = useState<"success" | "cancelled" | null>(null)
  const [mpesaPlanId, setMpesaPlanId] = useState<string | null>(null)
  const [mpesaPhone, setMpesaPhone] = useState("")
  const [mpesaWaiting, setMpesaWaiting] = useState<string | null>(null)
  const [mpesaError, setMpesaError] = useState<string | null>(null)
  const [autoSubscribeDone, setAutoSubscribeDone] = useState(false)
  const [cancelLoading, setCancelLoading] = useState(false)
  const [verifyDone, setVerifyDone] = useState(false)
  const [couponCode, setCouponCode] = useState("")
  const [couponPreview, setCouponPreview] = useState<{
    code: string
    originalAmount: number
    discountAmount: number
    finalAmount: number
    currency: string
  } | null>(null)
  const [couponError, setCouponError] = useState<string | null>(null)
  const [couponChecking, setCouponChecking] = useState(false)

  const planSlug = subscription?.plan ?? "starter"

  const plans = plansData.map((p) => ({
    id: p.id,
    name: p.name,
    slug: p.slug,
    price: p.price ?? p.priceDisplay ?? "—",
    features: p.features ?? [],
    current: p.slug === planSlug,
    checkoutAvailable: p.checkoutAvailable ?? false,
    paymentMethods: p.paymentMethods ?? {},
  }))

  useEffect(() => {
    const q = searchParams.get("checkout")
    const paystackRef = searchParams.get("reference") || searchParams.get("trxref")
    if (!q && !paystackRef) return

    const run = async () => {
      if (paystackRef && !verifyDone) {
        setVerifyDone(true)
        const result = await verifyPaystackCheckout(paystackRef)
        if (!result.success) {
          toast.error(result.message ?? "Could not confirm Paystack payment.")
          setCheckoutMessage("cancelled")
        } else {
          setCheckoutMessage("success")
          toast.success(result.message ?? "Payment confirmed.")
        }
        await mutate()
      } else if (q === "success" || q === "cancelled") {
        setCheckoutMessage(q === "cancelled" ? "cancelled" : "success")
        mutate()
      }
      if (typeof window !== "undefined") window.history.replaceState({}, "", "/dashboard/subscription")
    }
    void run()
  }, [searchParams, mutate, verifyDone])

  // Auto-start checkout when redirected from register/login with ?subscribe=planId
  useEffect(() => {
    const subscribePlanId = searchParams.get("subscribe")
    if (!subscribePlanId || autoSubscribeDone || !plansData.length) return
    const plan = plansData.find((p) => p.id === subscribePlanId)
    if (!plan?.checkoutAvailable) return
    setAutoSubscribeDone(true)
    if (typeof window !== "undefined") window.history.replaceState({}, "", "/dashboard/subscription")
    const start = async () => {
      if (plan.paymentMethods?.paystack) {
        const callbackUrl =
          typeof window !== "undefined"
            ? `${window.location.origin}/dashboard/subscription?checkout=success`
            : undefined
        const result = await createPaystackCheckout(subscribePlanId, { callbackUrl })
        if (result.success && result.url) window.location.href = result.url
        else toast.error(result.message ?? "Could not start Paystack checkout.")
        return
      }
      const result = await createCheckoutSession(subscribePlanId)
      if (result.success && result.url) window.location.href = result.url
      else if (!result.success) toast.error(result.message ?? "Could not start checkout.")
    }
    void start()
  }, [searchParams, plansData, autoSubscribeDone])

  // Poll subscription when M-Pesa payment is pending
  useEffect(() => {
    if (!mpesaWaiting || !subscription) return
    const planSlug = plans.find((p) => p.id === mpesaWaiting)?.slug
    if (subscription.plan === planSlug && subscription.status === "active") {
      setMpesaWaiting(null)
      setCheckoutMessage("success")
      mutate()
    }
  }, [mpesaWaiting, subscription, plans, mutate])

  useEffect(() => {
    if (!mpesaWaiting) return
    const interval = setInterval(() => mutate(), 3000)
    const timeout = setTimeout(() => {
      setMpesaWaiting(null)
    }, 120000)
    return () => {
      clearInterval(interval)
      clearTimeout(timeout)
    }
  }, [mpesaWaiting, mutate])

  if (isLoading && !subscription) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Subscription</h1>
          <p className="text-muted-foreground">Manage your subscription and billing</p>
        </div>
        <Card>
          <CardContent className="p-8">
            <div className="flex items-center justify-center gap-2 text-muted-foreground">
              <span className="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
              Loading subscription...
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Subscription</h1>
          <p className="text-muted-foreground">Manage your subscription and billing</p>
        </div>
        <Card className="border-destructive/50">
          <CardContent className="p-6">
            <p className="text-destructive">Failed to load subscription. Please try again.</p>
            <Button variant="outline" className="mt-2" onClick={() => mutate()}>
              Retry
            </Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  const planName =
    subscription?.planName ??
    plans.find((p) => p.slug === subscription?.plan)?.name ??
    (subscription?.plan === "professional"
      ? "Growth"
      : subscription?.plan === "starter"
        ? "Starter"
        : subscription?.plan === "enterprise"
          ? "Enterprise"
          : subscription?.plan ?? "Plan")
  const currency = (subscription?.currency || "").toUpperCase()
  const planPrice = subscription?.amount
    ? `${currency ? currency + " " : ""}${Number(subscription.amount).toLocaleString()}`
    : "—"
  const renewalDate = subscription?.endDate ? new Date(subscription.endDate).toLocaleDateString("en-US", { month: "long", day: "numeric", year: "numeric" }) : "—"
  const accessLabel = subscription?.accessEndsLabel ?? "Renews on"
  const daysRemaining = subscription?.daysRemaining
  const status = subscription?.status ?? "active"
  const isStripeManaged = subscription?.paymentMethod === "stripe"
  const canCancelLocal =
    !!subscription &&
    ["active", "trial"].includes(status) &&
    !isStripeManaged &&
    subscription.id !== "0"

  const applyCoupon = async (planId: string) => {
    const code = couponCode.trim()
    if (!code) {
      setCouponPreview(null)
      setCouponError(null)
      return
    }
    setCouponChecking(true)
    setCouponError(null)
    const result = await previewCoupon(planId, code)
    setCouponChecking(false)
    if (!result.success) {
      setCouponPreview(null)
      setCouponError(result.message ?? "Invalid coupon")
      return
    }
    setCouponPreview({
      code: result.code ?? code,
      originalAmount: result.originalAmount ?? 0,
      discountAmount: result.discountAmount ?? 0,
      finalAmount: result.finalAmount ?? 0,
      currency: result.currency ?? "",
    })
  }

  const usage = (usageData?.items ?? [
    { name: "Messages", used: 0, limit: 5000 },
    { name: "Team members", used: 0, limit: 3 },
  ]).map((item) => ({
    ...item,
    icon: item.name === "Messages" ? MessageSquare : Users,
  }))

  const handleSubscribe = async (planId: string) => {
    setCheckoutPlanId(planId)
    const result = await createCheckoutSession(planId)
    setCheckoutPlanId(null)
    if (result.success && result.url) window.location.href = result.url
    else toast.error(result.message ?? "Could not start checkout.")
  }

  const handlePaystackSubscribe = async (planId: string) => {
    setCheckoutPlanId(planId)
    const callbackUrl =
      typeof window !== "undefined"
        ? `${window.location.origin}/dashboard/subscription?checkout=success`
        : undefined
    const result = await createPaystackCheckout(planId, {
      callbackUrl,
      couponCode: couponCode.trim() || undefined,
    })
    setCheckoutPlanId(null)
    if (result.success && result.url) window.location.href = result.url
    else toast.error(result.message ?? "Could not start Paystack checkout.")
  }

  const handleMpesaSubmit = async (planId: string) => {
    const phone = mpesaPhone.trim().replace(/\s/g, "")
    if (!phone) {
      setMpesaError("Enter your M-Pesa phone number (e.g. 254712345678 or 0712345678)")
      return
    }
    setMpesaError(null)
    const result = await createMpesaCheckout(planId, phone, couponCode.trim() || undefined)
    if (!result.success) {
      setMpesaError(result.message ?? "Failed to send M-Pesa prompt")
      return
    }
    setMpesaPlanId(null)
    setMpesaPhone("")
    setMpesaWaiting(planId)
  }

  const handleBillingPortal = async () => {
    setPortalLoading(true)
    const result = await createBillingPortalSession()
    setPortalLoading(false)
    if (result.success && result.url) window.location.href = result.url
    else toast.error(result.message ?? "Could not open billing portal.")
  }

  const handleCancel = async () => {
    if (!canCancelLocal) return
    if (!window.confirm("Cancel your subscription? Access continues until the current period ends.")) return
    setCancelLoading(true)
    const result = await cancelSubscription()
    setCancelLoading(false)
    if (result.success) {
      toast.success(result.message ?? "Subscription cancelled.")
      mutate()
    } else {
      toast.error(result.message ?? "Could not cancel subscription.")
    }
  }

  const formatInvoiceDate = (d: string) => {
    if (/^\d{4}-\d{2}-\d{2}$/.test(d)) {
      return new Date(d + "Z").toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" })
    }
    return d
  }
  const billingList = billingHistory
  const getStatusVariant = (status: string): "default" | "secondary" | "destructive" | "outline" => {
    const s = status.toLowerCase()
    if (s === "paid") return "default"
    if (s === "open" || s === "uncollectible" || s === "unpaid") return "destructive"
    if (s === "draft" || s === "void") return "secondary"
    return "outline"
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Subscription</h1>
        <p className="text-muted-foreground">Manage your subscription and billing</p>
      </div>

      {searchParams.get("expired") === "1" && (
        <div className="rounded-lg border border-amber-500/50 bg-amber-500/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-400">
          Your subscription has expired or was cancelled. Choose a plan below to continue using the service.
        </div>
      )}
      {checkoutMessage === "success" && (
        <div className="rounded-lg border border-green-500/50 bg-green-500/10 px-4 py-3 text-sm text-green-700 dark:text-green-400">
          Payment successful. Your subscription is now active.
        </div>
      )}
      {checkoutMessage === "cancelled" && (
        <div className="rounded-lg border border-amber-500/50 bg-amber-500/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-400">
          Checkout was cancelled. You can try again when ready.
        </div>
      )}

      {/* Current Plan — from useSubscription() */}
      <Card>
        <CardHeader>
          <CardTitle>Current Plan</CardTitle>
          <CardDescription>Your subscription details</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-4">
              <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10">
                <Zap className="h-8 w-8 text-primary" />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <h3 className="text-2xl font-bold text-foreground">{planName}</h3>
                  <Badge>{status}</Badge>
                  {subscription?.isExpiringSoon && (
                    <Badge variant="outline" className="border-amber-500 text-amber-700">
                      Expiring soon
                    </Badge>
                  )}
                </div>
                <p className="text-muted-foreground">
                  {planPrice}/month • {accessLabel} {renewalDate}
                  {typeof daysRemaining === "number" && status !== "expired" ? (
                    <span> ({daysRemaining} day{daysRemaining === 1 ? "" : "s"} left)</span>
                  ) : null}
                </p>
              </div>
            </div>
            <div className="flex w-full gap-2 sm:w-auto">
              {isStripeManaged && (
                <Button className="w-full sm:w-auto" variant="outline" onClick={handleBillingPortal} disabled={portalLoading}>
                  {portalLoading ? "Opening…" : "Manage billing"}
                </Button>
              )}
              {canCancelLocal && (
                <Button className="w-full sm:w-auto" variant="outline" onClick={handleCancel} disabled={cancelLoading}>
                  {cancelLoading ? "Cancelling…" : "Cancel subscription"}
                </Button>
              )}
            </div>
          </div>
        </CardContent>
      </Card>

      {(usageData?.warnings ?? []).length > 0 && (
        <div className="space-y-2">
          {(usageData?.warnings ?? []).map((w) => (
            <div
              key={w.resource}
              className={`rounded-lg border px-4 py-3 text-sm ${
                w.level === 'critical'
                  ? 'border-destructive/50 bg-destructive/10 text-destructive'
                  : w.level === 'warning'
                    ? 'border-amber-500/50 bg-amber-500/10 text-amber-800 dark:text-amber-300'
                    : 'border-primary/30 bg-primary/5 text-muted-foreground'
              }`}
            >
              {w.message}
              {w.projectedOverage && (
                <span className="block text-xs mt-1 opacity-80">Projected to exceed limit this month at current pace.</span>
              )}
            </div>
          ))}
          <Button asChild variant="outline" size="sm">
            <Link href="/dashboard/subscription#plans">Upgrade plan</Link>
          </Button>
        </div>
      )}

      {/* Usage — API: GET /api/company/subscription/usage */}
      <Card>
        <CardHeader>
          <CardTitle>Usage</CardTitle>
          <CardDescription>Current billing period usage</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-6">
            {usage.map((item) => {
              const pct = item.limit > 0 ? (item.used / item.limit) * 100 : 0
              const nearLimit = pct >= 80
              return (
              <div key={item.name} className="space-y-2">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <item.icon className="h-4 w-4 text-muted-foreground" />
                    <span className="font-medium text-foreground">{item.name}</span>
                  </div>
                  <span className={`text-sm ${nearLimit ? 'text-amber-600 font-medium' : 'text-muted-foreground'}`}>
                    {item.used.toLocaleString()} / {item.limit.toLocaleString()}
                  </span>
                </div>
                <Progress value={Math.min(100, pct)} className={`h-2 ${nearLimit ? '[&>div]:bg-amber-500' : ''}`} />
              </div>
            )})}
          </div>
        </CardContent>
      </Card>

      {/* Available Plans — from usePlans() */}
      <Card id="plans">
        <CardHeader>
          <CardTitle>Available Plans</CardTitle>
          <CardDescription>Compare and switch plans. Apply a coupon before Paystack or M-Pesa checkout.</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="mb-6 max-w-md space-y-2 rounded-lg border p-4 bg-muted/20">
            <Label htmlFor="coupon-code">Coupon code</Label>
            <div className="flex gap-2">
              <Input
                id="coupon-code"
                placeholder="e.g. SAVE20"
                value={couponCode}
                onChange={(e) => {
                  setCouponCode(e.target.value.toUpperCase())
                  setCouponPreview(null)
                  setCouponError(null)
                }}
                className="bg-background"
              />
              <Button
                type="button"
                variant="outline"
                disabled={couponChecking || !couponCode.trim() || !plans.find((p) => !p.current && p.checkoutAvailable)}
                onClick={() => {
                  const target = plans.find((p) => !p.current && p.checkoutAvailable)
                  if (target) void applyCoupon(target.id)
                }}
              >
                {couponChecking ? "…" : "Apply"}
              </Button>
            </div>
            {couponError && <p className="text-sm text-destructive">{couponError}</p>}
            {couponPreview && (
              <p className="text-sm text-muted-foreground">
                {couponPreview.code}: {couponPreview.currency} {couponPreview.originalAmount} →{" "}
                <span className="font-medium text-foreground">
                  {couponPreview.currency} {couponPreview.finalAmount}
                </span>{" "}
                (save {couponPreview.discountAmount})
              </p>
            )}
          </div>
          <div className="grid gap-6 md:grid-cols-3">
            {plans.map((plan) => (
              <div
                key={plan.id}
                className={`relative rounded-xl border p-6 ${
                  plan.current ? "border-primary bg-primary/5" : "border-border"
                }`}
              >
                {plan.current && (
                  <Badge className="absolute -top-3 left-1/2 -translate-x-1/2">
                    Current Plan
                  </Badge>
                )}
                <div className="text-center mb-6">
                  <h3 className="text-lg font-semibold text-foreground">{plan.name}</h3>
                  <div className="mt-2">
                    <span className="text-3xl font-bold text-foreground">{plan.price}</span>
                    {plan.price !== "Custom" && (
                      <span className="text-muted-foreground">/month</span>
                    )}
                  </div>
                </div>
                <ul className="space-y-3 mb-6">
                  {plan.features.map((feature) => (
                    <li key={feature} className="flex items-center gap-2 text-sm text-muted-foreground">
                      <Check className="h-4 w-4 text-primary shrink-0" />
                      {feature}
                    </li>
                  ))}
                </ul>
                <div className="space-y-2">
                  {plan.current ? (
                    <Button className="w-full" variant="secondary" disabled>
                      Current Plan
                    </Button>
                  ) : plan.price === "Custom" || !plan.checkoutAvailable ? (
                    <Button className="w-full" variant="secondary" disabled>
                      Contact Sales
                    </Button>
                  ) : (
                    <>
                      {plan.paymentMethods?.stripe && (
                        <Button
                          className="w-full"
                          variant="default"
                          disabled={checkoutPlanId !== null && checkoutPlanId !== plan.id}
                          onClick={() => handleSubscribe(plan.id)}
                        >
                          {checkoutPlanId === plan.id
                            ? "Redirecting…"
                            : plan.paymentMethods?.mpesa || plan.paymentMethods?.paystack
                              ? "Subscribe with Card"
                              : "Subscribe"}
                        </Button>
                      )}
                      {plan.paymentMethods?.paystack && (
                        <Button
                          className="w-full"
                          variant={plan.paymentMethods?.stripe ? "outline" : "default"}
                          disabled={checkoutPlanId !== null && checkoutPlanId !== plan.id}
                          onClick={() => handlePaystackSubscribe(plan.id)}
                        >
                          {checkoutPlanId === plan.id ? "Redirecting…" : "Pay with Paystack"}
                        </Button>
                      )}
                      {plan.paymentMethods?.mpesa && (
                        <>
                          {mpesaPlanId !== plan.id && !mpesaWaiting ? (
                            <Button
                              className="w-full mt-2"
                              variant="outline"
                              disabled={!!checkoutPlanId}
                              onClick={() => setMpesaPlanId(mpesaPlanId === plan.id ? null : plan.id)}
                            >
                              <Smartphone className="h-4 w-4 mr-2" />
                              Pay with M-Pesa
                            </Button>
                          ) : mpesaWaiting === plan.id ? (
                            <p className="text-sm text-center text-muted-foreground py-2">
                              Check your phone and enter PIN. We&apos;ll update when payment is received…
                            </p>
                          ) : null}
                          {mpesaPlanId === plan.id && !mpesaWaiting && (
                            <div className="mt-3 space-y-2 rounded-lg border p-3 bg-muted/30">
                              <Label htmlFor={`mpesa-phone-${plan.id}`}>M-Pesa phone number</Label>
                              <Input
                                id={`mpesa-phone-${plan.id}`}
                                placeholder="254712345678 or 0712345678"
                                value={mpesaPhone}
                                onChange={(e) => setMpesaPhone(e.target.value)}
                                className="bg-background"
                              />
                              {mpesaError && (
                                <p className="text-sm text-destructive">{mpesaError}</p>
                              )}
                              <div className="flex gap-2">
                                <Button size="sm" onClick={() => handleMpesaSubmit(plan.id)}>
                                  Send M-Pesa prompt
                                </Button>
                                <Button size="sm" variant="ghost" onClick={() => { setMpesaPlanId(null); setMpesaError(null) }}>
                                  Cancel
                                </Button>
                              </div>
                            </div>
                          )}
                        </>
                      )}
                    </>
                  )}
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Billing History — API: GET /api/company/subscription/invoices */}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <div>
            <CardTitle>Billing History</CardTitle>
            <CardDescription>Your recent payments</CardDescription>
          </div>
          {isStripeManaged && (
            <Button variant="outline" size="sm" onClick={handleBillingPortal} disabled={portalLoading}>
              <CreditCard className="h-4 w-4 mr-2" />
              {portalLoading ? "Opening…" : "Manage billing"}
            </Button>
          )}
        </CardHeader>
        <CardContent>
          {billingList.length === 0 ? (
            <p className="text-sm text-muted-foreground py-4">No payments yet.</p>
          ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Invoice</TableHead>
                <TableHead>Date</TableHead>
                <TableHead>Amount</TableHead>
                <TableHead>Status</TableHead>
                <TableHead></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {billingList.map((invoice) => (
                <TableRow key={invoice.id}>
                  <TableCell className="font-medium text-foreground">{invoice.id}</TableCell>
                  <TableCell className="text-muted-foreground">{formatInvoiceDate(invoice.date)}</TableCell>
                  <TableCell className="text-foreground">{invoice.amount}</TableCell>
                  <TableCell>
                    <Badge variant={getStatusVariant(invoice.status)}>{invoice.status}</Badge>
                  </TableCell>
                  <TableCell>
                    {invoice.invoicePdf ? (
                      <Button variant="ghost" size="icon" asChild>
                        <a href={invoice.invoicePdf} target="_blank" rel="noopener noreferrer" title="Download invoice">
                          <Download className="h-4 w-4" />
                        </a>
                      </Button>
                    ) : (
                      <Button variant="ghost" size="icon" disabled title="No invoice link">
                        <Download className="h-4 w-4 opacity-50" />
                      </Button>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

export default function SubscriptionPage() {
  return (
    <Suspense fallback={
      <div className="flex items-center justify-center py-20">
        <span className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    }>
      <SubscriptionPageContent />
    </Suspense>
  )
}
