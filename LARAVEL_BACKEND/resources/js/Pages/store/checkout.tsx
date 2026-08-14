'use client'

import { FormEvent, useEffect, useState } from 'react'
import { Link, router } from '@inertiajs/react'
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  ChevronDown,
  ChevronUp,
  Gift,
  Lock,
  Mail,
  MapPin,
  MessageCircle,
  MessageSquare,
  Phone,
  ShieldCheck,
  ShoppingBag,
  Store,
  Tag,
  Truck,
  User,
  UtensilsCrossed,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { StorefrontAuthModal } from '@/components/store/StorefrontAuthModal'

type CartItem = { key: string; name: string; price: number; quantity: number; lineTotal: number }
type CartSummary = { items: CartItem[]; subtotal: number; taxTotal: number; total: number }

type SuggestedAddress = { line: string; city?: string | null; label?: string | null; customerName?: string | null } | null

type Quote = {
  subtotal: number
  taxTotal: number
  deliveryFee: number
  discountTotal: number
  tipAmount: number
  total: number
  couponValid: boolean
}

type Props = {
  slug: string
  sessionPhone?: string | null
  company: { name: string; currency: string; whatsappUrl?: string | null; authCustomer?: { id: number; name: string; email: string } | null }
  cart: CartSummary
  dineInEnabled: boolean
  deliveryFeesEnabled: boolean
  presetDineInTableCode?: string | null
  suggestedAddress?: SuggestedAddress
  errors?: Record<string, string>
}

function formatPrice(amount: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount)
  } catch {
    return `${currency} ${amount.toFixed(2)}`
  }
}

export default function StoreCheckoutPage({
  slug,
  sessionPhone = null,
  company,
  cart,
  dineInEnabled,
  presetDineInTableCode,
  suggestedAddress = null,
  errors = {},
}: Props) {
  const authCustomer = company.authCustomer
  const [customerName, setCustomerName] = useState(authCustomer?.name || suggestedAddress?.customerName || '')
  const [customerPhone, setCustomerPhone] = useState(sessionPhone || '')
  const [customerEmail, setCustomerEmail] = useState(authCustomer?.email || '')
  const [orderNotes, setOrderNotes] = useState('')
  const [giftMessage, setGiftMessage] = useState('')
  const [tipAmount, setTipAmount] = useState('')
  const [couponCode, setCouponCode] = useState('')
  const [fulfillmentType, setFulfillmentType] = useState<'delivery' | 'pickup' | 'dine_in'>(
    dineInEnabled && presetDineInTableCode ? 'dine_in' : 'delivery'
  )
  const [deliveryAddress, setDeliveryAddress] = useState(suggestedAddress?.line || '')
  const [dineInTableCode, setDineInTableCode] = useState(presetDineInTableCode ?? '')
  const [submitting, setSubmitting] = useState(false)
  const [quote, setQuote] = useState<Quote | null>(null)
  const [authModalOpen, setAuthModalOpen] = useState(false)
  const [showMoreOptions, setShowMoreOptions] = useState(false)

  // Detect WhatsApp traffic via URL phone query or session phone
  const [isWhatsAppVisitor, setIsWhatsAppVisitor] = useState(Boolean(sessionPhone && sessionPhone.trim() !== ''))

  useEffect(() => {
    if (typeof window !== 'undefined') {
      const params = new URLSearchParams(window.location.search)
      const phoneParam = params.get('phone')
      const effectivePhone = (phoneParam && phoneParam.trim() !== '') ? phoneParam.trim() : sessionPhone
      if (effectivePhone && effectivePhone.trim() !== '') {
        setIsWhatsAppVisitor(true)
        if (!customerPhone) setCustomerPhone(effectivePhone.trim())
      }
    }
  }, [sessionPhone])

  useEffect(() => {
    if (dineInEnabled && presetDineInTableCode) {
      setFulfillmentType('dine_in')
      setDineInTableCode(presetDineInTableCode)
    }
  }, [dineInEnabled, presetDineInTableCode])

  useEffect(() => {
    if (suggestedAddress?.line && !deliveryAddress) {
      setDeliveryAddress(suggestedAddress.line)
    }
    if (suggestedAddress?.customerName && !customerName) {
      setCustomerName(suggestedAddress.customerName)
    }
  }, [suggestedAddress, deliveryAddress, customerName])

  useEffect(() => {
    if (authCustomer) {
      if (authCustomer.name && !customerName) setCustomerName(authCustomer.name)
      if (authCustomer.email && !customerEmail) setCustomerEmail(authCustomer.email)
    }
  }, [authCustomer])

  const refreshQuote = async () => {
    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      const res = await fetch(`/s/${slug}/checkout/quote`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          fulfillmentType,
          deliveryAddress: fulfillmentType === 'delivery' ? deliveryAddress : null,
          couponCode: couponCode || null,
          tipAmount: tipAmount ? Number(tipAmount) : 0,
        }),
      })
      if (res.ok) {
        setQuote(await res.json())
      }
    } catch {
      // ignore quote errors
    }
  }

  useEffect(() => {
    const t = setTimeout(() => {
      void refreshQuote()
    }, 350)
    return () => clearTimeout(t)
  }, [fulfillmentType, deliveryAddress, couponCode, tipAmount])

  const onSubmit = (e: FormEvent) => {
    e.preventDefault()

    // Non-WhatsApp Web direct visitors MUST be authenticated
    if (!isWhatsAppVisitor && !authCustomer) {
      setAuthModalOpen(true)
      return
    }

    setSubmitting(true)
    router.post(
      `/s/${slug}/checkout`,
      {
        customerName,
        customerPhone: customerPhone || null,
        customerEmail: customerEmail || null,
        fulfillmentType,
        deliveryAddress: fulfillmentType === 'delivery' ? deliveryAddress : null,
        dineInTableCode: fulfillmentType === 'dine_in' ? dineInTableCode : null,
        orderNotes: orderNotes || null,
        giftMessage: giftMessage || null,
        tipAmount: tipAmount ? Number(tipAmount) : 0,
        couponCode: couponCode || null,
      },
      { onFinish: () => setSubmitting(false) }
    )
  }

  const displayTotal = quote?.total ?? cart.total

  return (
    <div className="min-h-screen bg-slate-50/80 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
      {/* Top Navbar */}
      <header className="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-3.5">
          <Link
            href={`/s/${slug}/cart`}
            className="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
          >
            <ArrowLeft className="h-4 w-4" /> Return to Cart
          </Link>
          <div className="flex items-center gap-2">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-400">{company.name}</span>
            <span className="text-slate-300 dark:text-slate-700">|</span>
            <h1 className="text-sm font-bold tracking-tight text-slate-900 dark:text-white">Order Checkout</h1>
          </div>
          <div className="hidden items-center gap-1 text-xs text-slate-400 sm:flex">
            <Lock className="h-3.5 w-3.5 text-emerald-600" /> Secure Checkout
          </div>
        </div>
      </header>

      {/* Main Container */}
      <main className="mx-auto max-w-5xl px-4 py-8">
        <div className="grid gap-8 lg:grid-cols-12">
          
          {/* Left Column: Form Controls */}
          <div className="space-y-6 lg:col-span-7">
            
            {/* Identity Status Card (Dual Flow Banner) */}
            {isWhatsAppVisitor ? (
              <div className="flex items-center justify-between rounded-3xl border border-emerald-200/80 bg-emerald-50/90 p-4 shadow-xs dark:border-emerald-900/50 dark:bg-emerald-950/40">
                <div className="flex items-center gap-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-sm">
                    <MessageCircle className="h-5 w-5" />
                  </div>
                  <div>
                    <span className="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">WhatsApp Checkout</span>
                    <p className="text-xs text-emerald-700 dark:text-emerald-400">
                      Identified via WhatsApp phone number. Email address is optional.
                    </p>
                  </div>
                </div>
              </div>
            ) : authCustomer ? (
              <div className="flex items-center justify-between rounded-3xl border border-blue-200/80 bg-blue-50/90 p-4 shadow-xs dark:border-blue-900/50 dark:bg-blue-950/40">
                <div className="flex items-center gap-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-sm">
                    <User className="h-5 w-5" />
                  </div>
                  <div>
                    <span className="text-xs font-bold uppercase tracking-wider text-blue-800 dark:text-blue-300">Signed In Account</span>
                    <p className="text-xs font-medium text-blue-950 dark:text-blue-200">{authCustomer.email}</p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => router.post(`/s/${slug}/account/logout`)}
                  className="rounded-xl border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-700 shadow-2xs hover:bg-blue-100 dark:border-blue-800 dark:bg-slate-900 dark:text-blue-300"
                >
                  Sign Out
                </button>
              </div>
            ) : (
              <div className="rounded-3xl border border-amber-200/80 bg-amber-50/90 p-5 shadow-xs dark:border-amber-900/50 dark:bg-amber-950/40">
                <div className="flex items-start gap-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-amber-500 text-white shadow-sm">
                    <Lock className="h-5 w-5" />
                  </div>
                  <div className="space-y-1">
                    <h2 className="text-sm font-bold text-amber-950 dark:text-amber-200">Account Required for Web Checkout</h2>
                    <p className="text-xs leading-relaxed text-amber-800 dark:text-amber-300">
                      Sign in or create an account with Email & Password to track payments, order status, and saved addresses.
                    </p>
                    <Button
                      type="button"
                      size="sm"
                      onClick={() => setAuthModalOpen(true)}
                      className="mt-2.5 gap-2 rounded-xl bg-amber-950 text-white hover:bg-amber-900 dark:bg-amber-600 dark:hover:bg-amber-500"
                    >
                      <User className="h-4 w-4" /> Sign In / Create Account
                    </Button>
                  </div>
                </div>
              </div>
            )}

            {/* Checkout Form */}
            <form onSubmit={onSubmit} className="space-y-6 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none sm:p-7">
              
              {errors.checkout && (
                <div className="rounded-2xl bg-red-50 p-4 text-xs font-medium text-red-700 border border-red-200/60 dark:bg-red-950/40 dark:border-red-900/50 dark:text-red-300">
                  {errors.checkout}
                </div>
              )}

              {/* Customer Contact Section */}
              <div className="space-y-4">
                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">1. Customer Information</h3>
                
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-1.5 sm:col-span-2">
                    <Label className="text-xs font-semibold text-slate-700 dark:text-slate-300">Full Name</Label>
                    <div className="relative">
                      <User className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
                      <Input
                        value={customerName}
                        onChange={(e) => setCustomerName(e.target.value)}
                        required
                        placeholder="e.g. Ken Wafula"
                        className="pl-10 rounded-2xl"
                      />
                    </div>
                  </div>

                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                      Email Address {isWhatsAppVisitor ? '(Optional)' : ''}
                    </Label>
                    <div className="relative">
                      <Mail className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
                      <Input
                        type="email"
                        value={customerEmail}
                        onChange={(e) => setCustomerEmail(e.target.value)}
                        required={!isWhatsAppVisitor}
                        placeholder="you@example.com"
                        className="pl-10 rounded-2xl"
                      />
                    </div>
                  </div>

                  <div className="space-y-1.5">
                    <Label className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                      WhatsApp Phone {isWhatsAppVisitor ? '' : '(Optional)'}
                    </Label>
                    <div className="relative">
                      <Phone className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
                      <Input
                        value={customerPhone}
                        onChange={(e) => setCustomerPhone(e.target.value)}
                        required={isWhatsAppVisitor}
                        inputMode="tel"
                        autoComplete="tel"
                        placeholder="254712345678"
                        className="pl-10 rounded-2xl"
                      />
                    </div>
                  </div>
                </div>
              </div>

              {/* Fulfillment Method Selector */}
              <div className="space-y-3 border-t border-slate-100 pt-5 dark:border-slate-800">
                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">2. Fulfillment Method</h3>
                
                <div className="grid grid-cols-3 gap-2">
                  <button
                    type="button"
                    onClick={() => setFulfillmentType('delivery')}
                    className={`flex flex-col items-center justify-center gap-1.5 rounded-2xl border p-3 text-center text-xs font-semibold transition-all ${
                      fulfillmentType === 'delivery'
                        ? 'border-slate-900 bg-slate-900 text-white shadow-md dark:border-white dark:bg-white dark:text-slate-900'
                        : 'border-slate-200/80 bg-white hover:border-slate-300 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                    }`}
                  >
                    <Truck className="h-4 w-4" />
                    <span>Delivery</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => setFulfillmentType('pickup')}
                    className={`flex flex-col items-center justify-center gap-1.5 rounded-2xl border p-3 text-center text-xs font-semibold transition-all ${
                      fulfillmentType === 'pickup'
                        ? 'border-slate-900 bg-slate-900 text-white shadow-md dark:border-white dark:bg-white dark:text-slate-900'
                        : 'border-slate-200/80 bg-white hover:border-slate-300 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                    }`}
                  >
                    <Store className="h-4 w-4" />
                    <span>Store Pickup</span>
                  </button>

                  {dineInEnabled && (
                    <button
                      type="button"
                      onClick={() => setFulfillmentType('dine_in')}
                      className={`flex flex-col items-center justify-center gap-1.5 rounded-2xl border p-3 text-center text-xs font-semibold transition-all ${
                        fulfillmentType === 'dine_in'
                          ? 'border-slate-900 bg-slate-900 text-white shadow-md dark:border-white dark:bg-white dark:text-slate-900'
                          : 'border-slate-200/80 bg-white hover:border-slate-300 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                      }`}
                    >
                      <UtensilsCrossed className="h-4 w-4" />
                      <span>Dine-In</span>
                    </button>
                  )}
                </div>

                {fulfillmentType === 'delivery' && (
                  <div className="space-y-1.5 pt-1">
                    <Label className="text-xs font-semibold text-slate-700 dark:text-slate-300">Delivery Address</Label>
                    <div className="relative">
                      <MapPin className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
                      <Textarea
                        value={deliveryAddress}
                        onChange={(e) => setDeliveryAddress(e.target.value)}
                        rows={3}
                        required
                        placeholder="Street, Building, City or Landmark"
                        className="pl-10 rounded-2xl text-xs"
                      />
                    </div>
                    {suggestedAddress?.line && (
                      <p className="text-xs text-slate-500">
                        Suggested address: <strong className="font-semibold text-slate-700 dark:text-slate-300">{suggestedAddress.line}</strong>
                      </p>
                    )}
                    {errors.deliveryAddress && <p className="text-xs font-medium text-red-600">{errors.deliveryAddress}</p>}
                  </div>
                )}

                {fulfillmentType === 'dine_in' && (
                  <div className="space-y-1.5 pt-1">
                    <Label className="text-xs font-semibold text-slate-700 dark:text-slate-300">Table Number / Code</Label>
                    <Input
                      value={dineInTableCode}
                      onChange={(e) => setDineInTableCode(e.target.value)}
                      placeholder="e.g. Table 04"
                      className="rounded-2xl"
                    />
                  </div>
                )}
              </div>

              {/* Collapsible Additional Options */}
              <div className="border-t border-slate-100 pt-4 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowMoreOptions(!showMoreOptions)}
                  className="flex w-full items-center justify-between text-xs font-semibold text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                >
                  <span className="flex items-center gap-1.5">
                    <Tag className="h-3.5 w-3.5" /> Coupons, Tips & Special Instructions
                  </span>
                  {showMoreOptions ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                </button>

                {showMoreOptions && (
                  <div className="grid gap-4 pt-4 sm:grid-cols-2">
                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-slate-700 dark:text-slate-300">Promo Code</Label>
                      <Input
                        value={couponCode}
                        onChange={(e) => setCouponCode(e.target.value)}
                        placeholder="SAVE10"
                        className="rounded-xl"
                      />
                      {quote && couponCode && !quote.couponValid && (
                        <p className="text-xs font-medium text-red-600">Invalid or expired coupon.</p>
                      )}
                    </div>

                    <div className="space-y-1.5">
                      <Label className="text-xs font-semibold text-slate-700 dark:text-slate-300">Tip Amount</Label>
                      <Input
                        type="number"
                        min="0"
                        step="0.01"
                        value={tipAmount}
                        onChange={(e) => setTipAmount(e.target.value)}
                        placeholder="0.00"
                        className="rounded-xl"
                      />
                    </div>

                    <div className="space-y-1.5 sm:col-span-2">
                      <Label className="text-xs font-semibold text-slate-700 dark:text-slate-300">Order Notes</Label>
                      <Textarea
                        value={orderNotes}
                        onChange={(e) => setOrderNotes(e.target.value)}
                        rows={2}
                        placeholder="Any special instructions for order preparation or delivery..."
                        className="rounded-xl text-xs"
                      />
                    </div>

                    <div className="space-y-1.5 sm:col-span-2">
                      <Label className="text-xs font-semibold text-slate-700 dark:text-slate-300">Gift Message</Label>
                      <Textarea
                        value={giftMessage}
                        onChange={(e) => setGiftMessage(e.target.value)}
                        rows={2}
                        placeholder="Optional card message for the recipient..."
                        className="rounded-xl text-xs"
                      />
                    </div>
                  </div>
                )}
              </div>

              {/* Submit Button */}
              <Button
                type="submit"
                disabled={submitting}
                size="lg"
                className="w-full gap-2 rounded-2xl bg-slate-900 py-6 text-base font-semibold shadow-md transition-all hover:bg-slate-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"
              >
                {submitting ? 'Processing Order…' : 'Place Order Now'} <ArrowRight className="h-5 w-5" />
              </Button>
            </form>
          </div>

          {/* Right Column: Order Summary Card */}
          <div className="lg:col-span-5">
            <div className="sticky top-20 space-y-4 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
              
              <div className="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                <h3 className="text-sm font-bold tracking-tight text-slate-900 dark:text-white">Order Summary</h3>
                <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                  {cart.items.reduce((acc, item) => acc + item.quantity, 0)} Items
                </span>
              </div>

              {/* Item List */}
              <div className="max-h-60 space-y-3 overflow-y-auto pr-1">
                {cart.items.map((item) => (
                  <div key={item.key} className="flex items-center justify-between text-xs">
                    <div className="flex items-center gap-2">
                      <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        {item.quantity}×
                      </span>
                      <span className="font-medium text-slate-800 dark:text-slate-200">{item.name}</span>
                    </div>
                    <span className="font-semibold text-slate-900 dark:text-white">{formatPrice(item.lineTotal, company.currency)}</span>
                  </div>
                ))}
              </div>

              {/* Price Breakdown */}
              <div className="space-y-2 border-t border-slate-100 pt-4 text-xs dark:border-slate-800">
                <div className="flex justify-between text-slate-600 dark:text-slate-400">
                  <span>Subtotal</span>
                  <span className="font-semibold text-slate-800 dark:text-slate-200">{formatPrice(quote?.subtotal ?? cart.subtotal, company.currency)}</span>
                </div>

                {(quote?.taxTotal ?? cart.taxTotal) > 0 && (
                  <div className="flex justify-between text-slate-600 dark:text-slate-400">
                    <span>Estimated Tax</span>
                    <span className="font-semibold text-slate-800 dark:text-slate-200">{formatPrice(quote?.taxTotal ?? cart.taxTotal, company.currency)}</span>
                  </div>
                )}

                {(quote?.deliveryFee ?? 0) > 0 && (
                  <div className="flex justify-between text-slate-600 dark:text-slate-400">
                    <span>Delivery Fee</span>
                    <span className="font-semibold text-slate-800 dark:text-slate-200">{formatPrice(quote!.deliveryFee, company.currency)}</span>
                  </div>
                )}

                {(quote?.discountTotal ?? 0) > 0 && (
                  <div className="flex justify-between text-emerald-600 dark:text-emerald-400">
                    <span>Discount Code</span>
                    <span className="font-semibold">-{formatPrice(quote!.discountTotal, company.currency)}</span>
                  </div>
                )}

                {(quote?.tipAmount ?? 0) > 0 && (
                  <div className="flex justify-between text-slate-600 dark:text-slate-400">
                    <span>Tip</span>
                    <span className="font-semibold text-slate-800 dark:text-slate-200">{formatPrice(quote!.tipAmount, company.currency)}</span>
                  </div>
                )}

                <div className="flex items-baseline justify-between border-t border-slate-200/80 pt-3 text-base font-extrabold text-slate-900 dark:border-slate-700 dark:text-white">
                  <span>Total Due</span>
                  <span className="text-xl text-emerald-600 dark:text-emerald-400">{formatPrice(displayTotal, company.currency)}</span>
                </div>
              </div>

              {/* Help Link */}
              {company.whatsappUrl && (
                <div className="border-t border-slate-100 pt-3 text-center dark:border-slate-800">
                  <a
                    href={company.whatsappUrl}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-400"
                  >
                    <MessageCircle className="h-3.5 w-3.5" /> Need help? Chat with store on WhatsApp
                  </a>
                </div>
              )}
            </div>
          </div>
        </div>
      </main>

      <StorefrontAuthModal
        open={authModalOpen}
        onOpenChange={setAuthModalOpen}
        slug={slug}
        companyName={company.name}
      />
    </div>
  )
}
