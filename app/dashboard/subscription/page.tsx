"use client"

import { useState, useEffect } from "react"
import { useSearchParams } from "next/navigation"
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
import { createCheckoutSession, createBillingPortalSession, createMpesaCheckout } from "@/lib/api-actions"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

export default function SubscriptionPage() {
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
    if (q === "success" || q === "cancelled") {
      setCheckoutMessage(q)
      mutate()
      if (typeof window !== "undefined") window.history.replaceState({}, "", "/dashboard/subscription")
    }
  }, [searchParams, mutate])

  // Auto-start checkout when redirected from register/login with ?subscribe=planId
  useEffect(() => {
    const subscribePlanId = searchParams.get("subscribe")
    if (!subscribePlanId || autoSubscribeDone || !plansData.length) return
    const plan = plansData.find((p) => p.id === subscribePlanId)
    if (!plan?.checkoutAvailable) return
    setAutoSubscribeDone(true)
    if (typeof window !== "undefined") window.history.replaceState({}, "", "/dashboard/subscription")
    createCheckoutSession(subscribePlanId).then((result) => {
      if (result.success && result.url) window.location.href = result.url
      else if (!result.success) alert(result.message ?? "Could not start checkout.")
    })
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

  const planName = subscription?.plan === "professional" ? "Growth" : subscription?.plan === "starter" ? "Starter" : subscription?.plan === "enterprise" ? "Enterprise" : "Growth"
  const planPrice = subscription?.amount ? `$${Number(subscription.amount)}` : "$99"
  const renewalDate = subscription?.endDate ? new Date(subscription.endDate).toLocaleDateString("en-US", { month: "long", day: "numeric", year: "numeric" }) : "—"
  const status = subscription?.status ?? "active"

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
    else alert(result.message ?? "Could not start checkout.")
  }

  const handleMpesaSubmit = async (planId: string) => {
    const phone = mpesaPhone.trim().replace(/\s/g, "")
    if (!phone) {
      setMpesaError("Enter your M-Pesa phone number (e.g. 254712345678 or 0712345678)")
      return
    }
    setMpesaError(null)
    const result = await createMpesaCheckout(planId, phone)
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
    else alert(result.message ?? "Could not open billing portal.")
  }
  const formatInvoiceDate = (d: string) => {
    if (/^\d{4}-\d{2}-\d{2}$/.test(d)) {
      return new Date(d + "Z").toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" })
    }
    return d
  }
  const billingList = billingHistory.length > 0 ? billingHistory : [
    { id: "INV-001", date: "Mar 14, 2024", amount: planPrice + ".00", status: "paid" },
    { id: "INV-002", date: "Feb 14, 2024", amount: planPrice + ".00", status: "paid" },
  ] as BillingInvoice[]
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
                </div>
                <p className="text-muted-foreground">
                  {planPrice}/month • Renews on {renewalDate}
                </p>
              </div>
            </div>
            <div className="flex w-full gap-2 sm:w-auto">
              <Button className="w-full sm:w-auto" variant="outline" onClick={handleBillingPortal} disabled={portalLoading}>
                {portalLoading ? "Opening…" : "Manage billing"}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Usage — API: GET /api/company/subscription/usage */}
      <Card>
        <CardHeader>
          <CardTitle>Usage</CardTitle>
          <CardDescription>Current billing period usage</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-6">
            {usage.map((item) => (
              <div key={item.name} className="space-y-2">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <item.icon className="h-4 w-4 text-muted-foreground" />
                    <span className="font-medium text-foreground">{item.name}</span>
                  </div>
                  <span className="text-sm text-muted-foreground">
                    {item.used.toLocaleString()} / {item.limit.toLocaleString()}
                  </span>
                </div>
                <Progress value={(item.used / item.limit) * 100} className="h-2" />
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Available Plans — from usePlans() */}
      <Card>
        <CardHeader>
          <CardTitle>Available Plans</CardTitle>
          <CardDescription>Compare and switch plans</CardDescription>
        </CardHeader>
        <CardContent>
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
                      {(plan.paymentMethods?.stripe ?? plan.checkoutAvailable) && (
                        <Button
                          className="w-full"
                          variant="default"
                          disabled={checkoutPlanId !== null && checkoutPlanId !== plan.id}
                          onClick={() => handleSubscribe(plan.id)}
                        >
                          {checkoutPlanId === plan.id ? "Redirecting…" : plan.paymentMethods?.mpesa ? "Subscribe with Card" : "Subscribe"}
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
            <CardDescription>Your recent invoices</CardDescription>
          </div>
          <Button variant="outline" size="sm" onClick={handleBillingPortal} disabled={portalLoading}>
            <CreditCard className="h-4 w-4 mr-2" />
            {portalLoading ? "Opening…" : "Manage billing"}
          </Button>
        </CardHeader>
        <CardContent>
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
        </CardContent>
      </Card>
    </div>
  )
}
