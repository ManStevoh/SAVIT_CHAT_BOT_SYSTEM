'use client'

import { FormEvent, useEffect, useState } from 'react'
import { Link, router } from '@inertiajs/react'
import { ArrowLeft } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

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
  company: { name: string; currency: string; whatsappUrl?: string | null }
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
  company,
  cart,
  dineInEnabled,
  presetDineInTableCode,
  suggestedAddress = null,
  errors = {},
}: Props) {
  const [customerName, setCustomerName] = useState(suggestedAddress?.customerName || '')
  const [customerPhone, setCustomerPhone] = useState('')
  const [customerEmail, setCustomerEmail] = useState('')
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
      // ignore quote errors — checkout still works
    }
  }

  useEffect(() => {
    const t = setTimeout(() => {
      void refreshQuote()
    }, 350)
    return () => clearTimeout(t)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [fulfillmentType, deliveryAddress, couponCode, tipAmount])

  const onSubmit = (e: FormEvent) => {
    e.preventDefault()
    setSubmitting(true)
    router.post(
      `/s/${slug}/checkout`,
      {
        customerName,
        customerPhone,
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
    <div className="min-h-screen bg-slate-50 text-slate-900">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-3xl items-center justify-between px-4 py-4">
          <Link href={`/s/${slug}/cart`} className="inline-flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900">
            <ArrowLeft className="h-4 w-4" /> Back to cart
          </Link>
          <h1 className="text-lg font-semibold">Checkout</h1>
        </div>
      </header>

      <main className="mx-auto grid max-w-3xl gap-6 px-4 py-8 sm:grid-cols-2">
        <form onSubmit={onSubmit} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          {errors.checkout && <p className="text-sm text-red-600">{errors.checkout}</p>}

          <div>
            <Label>Your name</Label>
            <Input value={customerName} onChange={(e) => setCustomerName(e.target.value)} required />
          </div>
          <div>
            <Label>WhatsApp number</Label>
            <Input
              value={customerPhone}
              onChange={(e) => setCustomerPhone(e.target.value)}
              required
              inputMode="tel"
              autoComplete="tel"
              placeholder="2547…"
            />
            <p className="mt-1 text-xs text-slate-500">We’ll send order updates and payment links here.</p>
          </div>
          <div>
            <Label>Email (optional)</Label>
            <Input type="email" value={customerEmail} onChange={(e) => setCustomerEmail(e.target.value)} />
          </div>

          <div className="space-y-2">
            <Label>Fulfillment</Label>
            <div className="flex flex-wrap gap-2">
              {(['delivery', 'pickup', ...(dineInEnabled ? (['dine_in'] as const) : [])] as const).map((type) => (
                <button
                  key={type}
                  type="button"
                  onClick={() => setFulfillmentType(type)}
                  className={`rounded-full border px-3 py-1.5 text-sm capitalize ${
                    fulfillmentType === type
                      ? 'border-slate-900 bg-slate-900 text-white'
                      : 'border-slate-200 bg-white hover:border-slate-400'
                  }`}
                >
                  {type.replace('_', ' ')}
                </button>
              ))}
            </div>
          </div>

          {fulfillmentType === 'delivery' && (
            <div>
              <Label>Delivery address</Label>
              <Textarea value={deliveryAddress} onChange={(e) => setDeliveryAddress(e.target.value)} rows={3} required />
              {suggestedAddress?.line ? (
                <p className="mt-1 text-xs text-slate-500">Suggested from your last order: {suggestedAddress.line}</p>
              ) : null}
              {errors.deliveryAddress && <p className="text-sm text-red-600">{errors.deliveryAddress}</p>}
            </div>
          )}

          {fulfillmentType === 'dine_in' && (
            <div>
              <Label>Table code</Label>
              <Input value={dineInTableCode} onChange={(e) => setDineInTableCode(e.target.value)} />
            </div>
          )}

          <div>
            <Label>Coupon code</Label>
            <Input value={couponCode} onChange={(e) => setCouponCode(e.target.value)} placeholder="SAVE10" />
            {quote && couponCode && !quote.couponValid ? (
              <p className="mt-1 text-xs text-red-600">This coupon is invalid or does not apply.</p>
            ) : null}
          </div>
          <div>
            <Label>Tip (optional)</Label>
            <Input type="number" min="0" step="0.01" value={tipAmount} onChange={(e) => setTipAmount(e.target.value)} />
          </div>
          <div>
            <Label>Order notes</Label>
            <Textarea value={orderNotes} onChange={(e) => setOrderNotes(e.target.value)} rows={2} />
          </div>
          <div>
            <Label>Gift message</Label>
            <Textarea value={giftMessage} onChange={(e) => setGiftMessage(e.target.value)} rows={2} />
          </div>

          <Button type="submit" disabled={submitting} className="w-full">
            {submitting ? 'Placing order…' : 'Place order'}
          </Button>
          {company.whatsappUrl ? (
            <a
              href={company.whatsappUrl}
              target="_blank"
              rel="noreferrer"
              className="block text-center text-sm font-medium text-emerald-700 hover:underline"
            >
              Need help? Chat on WhatsApp
            </a>
          ) : null}
        </form>

        <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-6">
          <h2 className="text-sm font-medium text-slate-500">Order summary — {company.name}</h2>
          {cart.items.map((item) => (
            <div key={item.key} className="flex justify-between text-sm">
              <span>
                {item.quantity} × {item.name}
              </span>
              <span>{formatPrice(item.lineTotal, company.currency)}</span>
            </div>
          ))}
          <div className="border-t border-slate-100 pt-3 text-sm text-slate-600">
            <div className="flex justify-between">
              <span>Subtotal</span>
              <span>{formatPrice(quote?.subtotal ?? cart.subtotal, company.currency)}</span>
            </div>
            {(quote?.taxTotal ?? cart.taxTotal) > 0 && (
              <div className="flex justify-between">
                <span>Tax</span>
                <span>{formatPrice(quote?.taxTotal ?? cart.taxTotal, company.currency)}</span>
              </div>
            )}
            {(quote?.deliveryFee ?? 0) > 0 && (
              <div className="flex justify-between">
                <span>Delivery</span>
                <span>{formatPrice(quote!.deliveryFee, company.currency)}</span>
              </div>
            )}
            {(quote?.discountTotal ?? 0) > 0 && (
              <div className="flex justify-between text-emerald-700">
                <span>Discount</span>
                <span>-{formatPrice(quote!.discountTotal, company.currency)}</span>
              </div>
            )}
            {(quote?.tipAmount ?? 0) > 0 && (
              <div className="flex justify-between">
                <span>Tip</span>
                <span>{formatPrice(quote!.tipAmount, company.currency)}</span>
              </div>
            )}
            <div className="mt-1 flex justify-between text-base font-semibold text-slate-900">
              <span>Total</span>
              <span>{formatPrice(displayTotal, company.currency)}</span>
            </div>
          </div>
        </div>
      </main>
    </div>
  )
}
