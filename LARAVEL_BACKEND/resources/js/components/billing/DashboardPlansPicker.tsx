"use client"

import { useEffect, useState } from "react"
import Link from "next/link"
import { toast } from "sonner"
import { Check, Smartphone } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { cn } from "@/lib/utils"
import { PlanPrice } from "@/components/shared/plan-price"
import { usePlans, useSubscription } from "@/lib/api-hooks"
import {
  apiRequest,
  createCheckoutSession,
  createMpesaCheckout,
  createPaystackCheckout,
  previewCoupon,
  submitManualPaymentProof,
} from "@/lib/api-actions"

type PickerPlan = {
  id: string
  name: string
  slug: string
  price: string
  originalPrice: string | null
  offer: {
    code: string
    name?: string
    discountType: "percent" | "fixed"
    discountValue: number
    discountAmount: number
  } | null
  features: string[]
  current: boolean
  isFree: boolean
  popular: boolean
  checkoutAvailable: boolean
  paymentMethods: {
    stripe?: boolean
    mpesa?: boolean
    paystack?: boolean
    pesapal?: boolean
    flutterwave?: boolean
    paypal?: boolean
    manual?: boolean
  }
}

export function DashboardPlansPicker({
  title = "Available plans",
  description = "Compare plans and upgrade to unlock this feature.",
}: {
  title?: string
  description?: string
}) {
  const { data: subscription, mutate } = useSubscription()
  const [pricingCurrency, setPricingCurrency] = useState<string | null>(null)
  const { data: plansResponse, isLoading: plansLoading } = usePlans(pricingCurrency)
  const plansData = plansResponse?.plans ?? []
  const activeCurrency = plansResponse?.currency ?? pricingCurrency ?? "KES"
  const currencies = plansResponse?.availableCurrencies?.length
    ? plansResponse.availableCurrencies
    : [
        { code: "KES", label: "Kenyan Shilling", symbol: "KSh" },
        { code: "USD", label: "US Dollar", symbol: "$" },
        { code: "NGN", label: "Nigerian Naira", symbol: "₦" },
      ]

  const [checkoutPlanId, setCheckoutPlanId] = useState<string | null>(null)
  const [mpesaPlanId, setMpesaPlanId] = useState<string | null>(null)
  const [mpesaPhone, setMpesaPhone] = useState("")
  const [mpesaWaiting, setMpesaWaiting] = useState<string | null>(null)
  const [mpesaError, setMpesaError] = useState<string | null>(null)
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
  const [manualCheckout, setManualCheckout] = useState<{
    reference: string
    instructions: string
    amount: number
    currency: string
    bankName?: string | null
    accountName?: string | null
    accountNumber?: string | null
  } | null>(null)
  const [proofFile, setProofFile] = useState<File | null>(null)
  const [proofNote, setProofNote] = useState("")
  const [proofSubmitting, setProofSubmitting] = useState(false)

  const planSlug = subscription?.plan ?? "free"
  const status = subscription?.status ?? "active"
  const daysRemaining = subscription?.daysRemaining
  const isCommissionMerchant =
    subscription?.billingModel === "commission" || subscription?.plan === "commission"
  const needsPaidActivation =
    !isCommissionMerchant &&
    planSlug !== "free" &&
    (["trial", "expired", "cancelled"].includes(status) ||
      (typeof daysRemaining === "number" && daysRemaining <= 0))

  const plans: PickerPlan[] = plansData.map((p) => ({
    id: p.id,
    name: p.name,
    slug: p.slug,
    price: p.price ?? p.priceDisplay ?? "—",
    originalPrice: p.originalPrice ?? null,
    offer: p.offer ?? null,
    features: Array.isArray(p.features) ? p.features : [],
    current: p.slug === planSlug,
    isFree: !!p.isFree,
    popular: !!p.popular,
    checkoutAvailable: p.checkoutAvailable ?? false,
    paymentMethods: p.paymentMethods && typeof p.paymentMethods === "object" ? p.paymentMethods : {},
  }))

  useEffect(() => {
    if (!mpesaWaiting || !subscription) return
    const waitingSlug = plans.find((p) => p.id === mpesaWaiting)?.slug
    if (subscription.plan === waitingSlug && subscription.status === "active") {
      setMpesaWaiting(null)
      toast.success("Payment received. Your plan is active.")
      mutate()
    }
  }, [mpesaWaiting, subscription, plans, mutate])

  useEffect(() => {
    if (!mpesaWaiting) return
    const interval = setInterval(() => mutate(), 3000)
    const timeout = setTimeout(() => setMpesaWaiting(null), 120000)
    return () => {
      clearInterval(interval)
      clearTimeout(timeout)
    }
  }, [mpesaWaiting, mutate])

  const applyCoupon = async (planId: string) => {
    const code = couponCode.trim()
    if (!code) {
      setCouponPreview(null)
      setCouponError(null)
      return
    }
    setCouponChecking(true)
    setCouponError(null)
    const result = await previewCoupon(planId, code, activeCurrency)
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
      currency: result.currency ?? activeCurrency,
    })
  }

  const checkoutCallbackUrl =
    typeof window !== "undefined" ? `${window.location.origin}/dashboard/subscription?checkout=success` : undefined

  const handleSubscribe = async (planId: string) => {
    setCheckoutPlanId(planId)
    const result = await createCheckoutSession(planId)
    setCheckoutPlanId(null)
    if (result.success && result.url) window.location.href = result.url
    else toast.error(result.message ?? "Could not start checkout.")
  }

  const handlePaystackSubscribe = async (planId: string) => {
    setCheckoutPlanId(planId)
    const result = await createPaystackCheckout(planId, {
      callbackUrl: checkoutCallbackUrl,
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

  const handleGenericCheckout = async (planId: string, gatewayId: string) => {
    setCheckoutPlanId(planId)
    const result = await apiRequest<{
      success: boolean
      checkout_url?: string
      instructions?: string
      invoice_reference?: string
      amount?: number
      currency?: string
      bank_name?: string | null
      account_name?: string | null
      account_number?: string | null
      message?: string
    }>("/api/company/subscription/checkout", {
      method: "POST",
      body: { plan: planId, gateway: gatewayId },
    })
    setCheckoutPlanId(null)
    if (result.success) {
      if (result.checkout_url) {
        window.location.href = result.checkout_url
      } else if (gatewayId === "manual" && result.invoice_reference) {
        setManualCheckout({
          reference: result.invoice_reference,
          instructions: result.instructions ?? "",
          amount: result.amount ?? 0,
          currency: result.currency ?? "KES",
          bankName: result.bank_name,
          accountName: result.account_name,
          accountNumber: result.account_number,
        })
        setProofFile(null)
        setProofNote("")
      } else if (result.instructions) {
        toast.success(result.instructions, { duration: 10000 })
      } else {
        toast.success(result.message ?? "Checkout initiated.")
      }
    } else {
      toast.error(result.message ?? "Could not start checkout.")
    }
  }

  const handleSubmitProof = async () => {
    if (!manualCheckout?.reference || !proofFile) {
      toast.error("Choose a payment proof image or PDF first.")
      return
    }
    setProofSubmitting(true)
    const res = await submitManualPaymentProof(manualCheckout.reference, proofFile, proofNote.trim() || undefined)
    setProofSubmitting(false)
    if (res.success) {
      toast.success(res.message ?? "Proof submitted for review.")
      setManualCheckout(null)
      setProofFile(null)
      setProofNote("")
      mutate()
    } else {
      toast.error(res.message ?? "Could not submit proof.")
    }
  }

  const checkoutLabel = (plan: PickerPlan, payingCurrent: boolean) => {
    if (payingCurrent) {
      if (status === "trial") return "Subscribe now"
      if (status === "expired") return "Renew plan"
      if (status === "cancelled") return "Re-subscribe"
      return "Pay now"
    }
    return status === "trial" || status === "active" ? "Upgrade" : "Subscribe"
  }

  const renderCheckoutButtons = (plan: PickerPlan, payingCurrent = false) => {
    const busy = checkoutPlanId !== null && checkoutPlanId !== plan.id
    const primaryLabel = checkoutLabel(plan, payingCurrent)

    return (
      <>
        {plan.paymentMethods?.stripe && (
          <Button className="w-full" disabled={busy} onClick={() => handleSubscribe(plan.id)}>
            {checkoutPlanId === plan.id
              ? "Redirecting…"
              : plan.paymentMethods?.mpesa || plan.paymentMethods?.paystack
                ? `${primaryLabel} with Card`
                : primaryLabel}
          </Button>
        )}
        {plan.paymentMethods?.paystack && (
          <Button
            className="w-full"
            variant={plan.paymentMethods?.stripe ? "outline" : "default"}
            disabled={busy}
            onClick={() => handlePaystackSubscribe(plan.id)}
          >
            {checkoutPlanId === plan.id ? "Redirecting…" : `${primaryLabel} with Paystack`}
          </Button>
        )}
        {plan.paymentMethods?.mpesa && (
          <>
            {mpesaPlanId !== plan.id && !mpesaWaiting ? (
              <Button
                className="mt-2 w-full"
                variant="outline"
                disabled={!!checkoutPlanId}
                onClick={() => setMpesaPlanId(plan.id)}
              >
                <Smartphone className="mr-2 h-4 w-4" />
                Pay with M-Pesa
              </Button>
            ) : mpesaWaiting === plan.id ? (
              <p className="py-2 text-center text-sm text-muted-foreground">
                Check your phone and enter PIN. We&apos;ll update when payment is received…
              </p>
            ) : null}
            {mpesaPlanId === plan.id && !mpesaWaiting && (
              <div className="mt-3 space-y-2 rounded-lg border bg-muted/30 p-3">
                <Label htmlFor={`gate-mpesa-${plan.id}`}>M-Pesa phone number</Label>
                <Input
                  id={`gate-mpesa-${plan.id}`}
                  placeholder="254712345678 or 0712345678"
                  value={mpesaPhone}
                  onChange={(e) => setMpesaPhone(e.target.value)}
                  className="bg-background"
                />
                {mpesaError && <p className="text-xs text-destructive">{mpesaError}</p>}
                <div className="flex gap-2">
                  <Button size="sm" className="w-full" onClick={() => handleMpesaSubmit(plan.id)}>
                    Send M-Pesa prompt
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => {
                      setMpesaPlanId(null)
                      setMpesaError(null)
                    }}
                  >
                    Cancel
                  </Button>
                </div>
              </div>
            )}
          </>
        )}
        {plan.paymentMethods?.pesapal && (
          <Button className="mt-2 w-full" variant="outline" disabled={busy} onClick={() => handleGenericCheckout(plan.id, "pesapal")}>
            Pay with Pesapal
          </Button>
        )}
        {plan.paymentMethods?.flutterwave && (
          <Button className="mt-2 w-full" variant="outline" disabled={busy} onClick={() => handleGenericCheckout(plan.id, "flutterwave")}>
            Pay with Flutterwave
          </Button>
        )}
        {plan.paymentMethods?.paypal && (
          <Button className="mt-2 w-full" variant="outline" disabled={busy} onClick={() => handleGenericCheckout(plan.id, "paypal")}>
            Pay with PayPal
          </Button>
        )}
        {plan.paymentMethods?.manual && (
          <Button className="mt-2 w-full" variant="outline" disabled={busy} onClick={() => handleGenericCheckout(plan.id, "manual")}>
            Bank Transfer / Invoice
          </Button>
        )}
      </>
    )
  }

  return (
    <div className="space-y-4">
      {manualCheckout && (
        <Card className="border-primary/30">
          <CardHeader>
            <CardTitle>Bank transfer instructions</CardTitle>
            <CardDescription>
              Pay {manualCheckout.currency} {Number(manualCheckout.amount).toLocaleString()} using reference{" "}
              <span className="font-mono font-medium text-foreground">{manualCheckout.reference}</span>, then upload
              proof for admin approval.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {(manualCheckout.bankName || manualCheckout.accountNumber) && (
              <div className="space-y-1 rounded-lg border border-border bg-muted/40 p-3 text-sm">
                {manualCheckout.bankName ? <div>Bank: {manualCheckout.bankName}</div> : null}
                {manualCheckout.accountName ? <div>Account name: {manualCheckout.accountName}</div> : null}
                {manualCheckout.accountNumber ? <div>Account number: {manualCheckout.accountNumber}</div> : null}
              </div>
            )}
            {manualCheckout.instructions ? (
              <p className="text-sm text-muted-foreground whitespace-pre-wrap">{manualCheckout.instructions}</p>
            ) : null}
            <div className="space-y-2">
              <Label htmlFor="gate-proof">Payment proof</Label>
              <Input id="gate-proof" type="file" accept="image/*,.pdf" onChange={(e) => setProofFile(e.target.files?.[0] ?? null)} />
              <Input placeholder="Optional note" value={proofNote} onChange={(e) => setProofNote(e.target.value)} />
              <Button onClick={() => void handleSubmitProof()} disabled={proofSubmitting || !proofFile}>
                {proofSubmitting ? "Submitting…" : "Submit proof"}
              </Button>
            </div>
          </CardContent>
        </Card>
      )}

      <Card id="plans">
        <CardHeader>
          <CardTitle>{title}</CardTitle>
          <CardDescription>{description}</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="inline-flex rounded-lg border border-border bg-muted/30 p-1">
              {currencies.map((c) => (
                <button
                  key={c.code}
                  type="button"
                  onClick={() => {
                    setPricingCurrency(c.code)
                    setCouponPreview(null)
                    setCouponError(null)
                  }}
                  className={cn(
                    "rounded-md px-3 py-1.5 text-sm font-medium transition-colors",
                    activeCurrency === c.code
                      ? "bg-primary text-primary-foreground"
                      : "text-muted-foreground hover:text-foreground"
                  )}
                >
                  {c.code}
                </button>
              ))}
            </div>
            <p className="text-xs text-muted-foreground">Showing prices in {activeCurrency}.</p>
          </div>
          <div className="mb-6 max-w-md space-y-2 rounded-lg border bg-muted/20 p-4">
            <Label htmlFor="gate-coupon">Coupon code</Label>
            <div className="flex gap-2">
              <Input
                id="gate-coupon"
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
                disabled={couponChecking || !couponCode.trim() || !plans.some((p) => p.checkoutAvailable)}
                onClick={() => {
                  const target = plans.find((p) => p.current && p.checkoutAvailable)
                    ?? plans.find((p) => p.checkoutAvailable)
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
                </span>
              </p>
            )}
          </div>
          <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {plansLoading && plans.length === 0
              ? [1, 2, 3].map((i) => (
                  <div key={i} className="rounded-xl border border-border p-6">
                    <div className="h-16 animate-pulse rounded bg-muted" />
                    <div className="mt-4 space-y-2">
                      <div className="h-3 animate-pulse rounded bg-muted" />
                      <div className="h-3 w-2/3 animate-pulse rounded bg-muted" />
                    </div>
                  </div>
                ))
              : plans.map((plan) => (
                  <div
                    key={plan.id}
                    className={`relative rounded-xl border p-6 ${
                      plan.current ? "border-primary bg-primary/5" : "border-border"
                    }`}
                  >
                    {plan.current && (
                      <Badge className="absolute -top-3 left-1/2 -translate-x-1/2">Current Plan</Badge>
                    )}
                    {plan.popular && !plan.current && (
                      <Badge variant="default" className="absolute -top-3 left-1/2 -translate-x-1/2">
                        Most Popular
                      </Badge>
                    )}
                    <div className="mb-6 text-center">
                      <h3 className="text-lg font-semibold text-foreground">{plan.name}</h3>
                      <div className="mt-2">
                        <PlanPrice
                          price={plan.price}
                          originalPrice={plan.originalPrice}
                          offer={plan.offer}
                          period="/month"
                          size="md"
                        />
                      </div>
                    </div>
                    <ul className="mb-6 space-y-3">
                      {plan.features.map((feature) => (
                        <li key={feature} className="flex items-center gap-2 text-sm text-muted-foreground">
                          <Check className="h-4 w-4 shrink-0 text-primary" />
                          {feature}
                        </li>
                      ))}
                    </ul>
                    <div className="space-y-2">
                      {plan.isFree ? (
                        plan.current && !needsPaidActivation ? (
                          <Button className="w-full" variant="secondary" disabled>
                            Current Plan
                          </Button>
                        ) : (
                          <Button className="w-full" variant="outline" disabled>
                            Free Plan
                          </Button>
                        )
                      ) : plan.price === "Custom" ? (
                        <Button asChild className="w-full" variant="outline">
                          <Link href="/contact">Contact Sales</Link>
                        </Button>
                      ) : !plan.checkoutAvailable ? (
                        <Button className="w-full" variant="secondary" disabled>
                          Payments unavailable
                        </Button>
                      ) : plan.current && !needsPaidActivation ? (
                        <Button className="w-full" variant="secondary" disabled>
                          Current Plan
                        </Button>
                      ) : (
                        renderCheckoutButtons(plan, !!plan.current)
                      )}
                    </div>
                  </div>
                ))}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
