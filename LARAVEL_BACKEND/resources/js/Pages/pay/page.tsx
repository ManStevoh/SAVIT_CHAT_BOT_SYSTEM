'use client'

import { FormEvent, useEffect, useState } from 'react'
import { router } from '@inertiajs/react'
import { CheckCircle2, CreditCard, Lock, Smartphone, ShieldCheck, ArrowRight, Check, ShoppingBag, FileText, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

type OrderPayload = {
  orderNumber: string
  customerName: string
  customerEmail?: string | null
  totalFormatted: string
  paymentStatus: string
  paymentMethod?: string | null
  invoiceToken?: string | null
  items: { name: string; quantity: number; lineSubtotal: number }[]
}

type PaymentOptions = {
  options: {
    id: string
    label: string
    category: string
    instructions?: string | null
    requiresPhone: boolean
    requiresEmail?: boolean
  }[]
  cod: boolean
  stripe: boolean
  paystack: boolean
  pesapal?: boolean
  flutterwave?: boolean
  paypal?: boolean
  mpesa: boolean
  manual: boolean
}

type Props = {
  token: string
  order: OrderPayload
  company: { name: string; customDomain?: string | null; storeSlug?: string | null }
  paymentOptions: PaymentOptions
  initialMethod?: string | null
  status?: string
  errors?: Record<string, string>
}

export default function PublicPayPage({ token, order, company, paymentOptions, initialMethod, status, errors = {} }: Props) {
  const getInitialMethod = () => {
    if (initialMethod) return initialMethod
    if (typeof window !== 'undefined') {
      const params = new URLSearchParams(window.location.search)
      return params.get('method') || params.get('gateway') || ''
    }
    return ''
  }

  const [method, setMethod] = useState<string>(getInitialMethod)
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState(order.customerEmail ?? '')
  const [submitting, setSubmitting] = useState(false)

  const isPaid = order.paymentStatus === 'paid'

  // Live polling for STK Push / Webhook confirmation if pending
  useEffect(() => {
    if (isPaid) return

    // If status exists and mentions STK or pending, poll every 4 seconds
    const interval = setInterval(() => {
      router.reload({
        only: ['order', 'status'],
      })
    }, 4000)

    return () => clearInterval(interval)
  }, [isPaid, token])

  const submit = (e: FormEvent) => {
    e.preventDefault()
    if (!method) return
    setSubmitting(true)
    router.post(
      `/pay/${token}`,
      { method, phone: phone || null, email: email.trim() || null },
      { onFinish: () => setSubmitting(false) }
    )
  }

  const availableMethods = paymentOptions.options
  const selectedMethod = availableMethods.find((option) => option.id === method)

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50/80 px-4 py-12 dark:bg-slate-950">
      <div className="w-full max-w-md space-y-6 rounded-3xl border border-slate-200/80 bg-white p-7 shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
        
        {/* Header Branding */}
        <div className="space-y-1 text-center sm:text-left">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-400">{company.name}</span>
            <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
              #{order.orderNumber}
            </span>
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Payment Checkout</h1>
          <div className="mt-2 flex items-baseline justify-between rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/50">
            <span className="text-sm text-slate-500 dark:text-slate-400">Total Amount</span>
            <span className="text-2xl font-extrabold text-slate-900 dark:text-white">{order.totalFormatted}</span>
          </div>
        </div>

        {/* Status / Errors */}
        {status && (
          <div className="flex items-start gap-2.5 rounded-2xl bg-emerald-50 p-4 text-xs font-medium text-emerald-800 border border-emerald-200/60 dark:bg-emerald-950/40 dark:border-emerald-900/50 dark:text-emerald-300">
            <Loader2 className="h-4 w-4 shrink-0 animate-spin text-emerald-600 dark:text-emerald-400 mt-0.5" />
            <div>
              <p className="font-bold">{status}</p>
              <p className="mt-0.5 text-[11px] text-emerald-700 dark:text-emerald-400">Waiting for payment confirmation. This page updates automatically.</p>
            </div>
          </div>
        )}
        {errors.method && <p className="rounded-2xl bg-red-50 p-3.5 text-xs font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">{errors.method}</p>}
        {errors.email && <p className="rounded-2xl bg-red-50 p-3.5 text-xs font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">{errors.email}</p>}

        {isPaid ? (
          <div className="space-y-5 rounded-2xl bg-emerald-50/90 p-6 text-center border border-emerald-200/80 dark:bg-emerald-950/30 dark:border-emerald-900/50">
            <CheckCircle2 className="mx-auto h-12 w-12 text-emerald-600 dark:text-emerald-400" />
            <div className="space-y-1">
              <h2 className="text-xl font-bold text-emerald-950 dark:text-emerald-200">Payment Received!</h2>
              <p className="text-xs text-emerald-800 dark:text-emerald-300">
                Thank you, <strong className="font-semibold">{order.customerName}</strong>. Your payment for Order #{order.orderNumber} is complete.
              </p>
            </div>
            
            <div className="space-y-1.5 border-t border-emerald-200/70 pt-3 text-left text-xs dark:border-emerald-900/50">
              <div className="font-bold uppercase tracking-wider text-emerald-800/70 dark:text-emerald-400">Order Details</div>
              {order.items.map((item, idx) => (
                <div key={idx} className="flex justify-between text-emerald-900 dark:text-emerald-200">
                  <span>{item.quantity} × {item.name}</span>
                  <span className="font-semibold">{item.lineSubtotal.toFixed(2)}</span>
                </div>
              ))}
            </div>

            {/* Navigation Action Buttons */}
            <div className="flex flex-col gap-2 pt-2">
              <Button asChild size="lg" className="w-full gap-2 bg-slate-900 hover:bg-slate-800 text-white dark:bg-emerald-600 dark:hover:bg-emerald-500">
                <a href={company.customDomain ? '/' : company.storeSlug ? `/s/${company.storeSlug}` : '/'}>
                  <ShoppingBag className="h-4 w-4" /> Return to Store
                </a>
              </Button>

              {order.invoiceToken && (
                <Button asChild type="button" variant="outline" size="sm" className="w-full gap-2 border-emerald-300 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-900/40">
                  <a href={`/invoice/${order.invoiceToken}`} target="_blank" rel="noreferrer">
                    <FileText className="h-4 w-4" /> View / Print Receipt
                  </a>
                </Button>
              )}
            </div>
          </div>
        ) : (
          <form onSubmit={submit} className="space-y-5">
            <div className="space-y-2.5">
              <Label className="text-xs font-bold uppercase tracking-wider text-slate-500">Choose Payment Method</Label>
              {availableMethods.length > 0 ? (
                <div className="grid gap-2">
                  {availableMethods.map((m) => {
                    const isSelected = method === m.id
                    return (
                      <button
                        key={m.id}
                        type="button"
                        onClick={() => setMethod(m.id)}
                        className={`flex items-center justify-between rounded-2xl border p-3.5 text-left text-sm font-medium transition-all ${
                          isSelected
                            ? 'border-slate-900 bg-slate-900 text-white shadow-md ring-2 ring-slate-900/20 dark:border-white dark:bg-white dark:text-slate-900 dark:ring-white/20'
                            : 'border-slate-200 bg-white hover:border-slate-400 text-slate-800 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-700'
                        }`}
                      >
                        <div className="flex items-center gap-3">
                          {m.id === 'mpesa' ? (
                            <Smartphone className="h-5 w-5 shrink-0" />
                          ) : m.id === 'stripe' || m.id === 'paystack' || m.id === 'flutterwave' || m.id === 'paypal' ? (
                            <CreditCard className="h-5 w-5 shrink-0" />
                          ) : (
                            <ShieldCheck className="h-5 w-5 shrink-0" />
                          )}
                          <span>{m.label}</span>
                        </div>
                        {isSelected && <Check className="h-4 w-4 shrink-0 stroke-[3]" />}
                      </button>
                    )
                  })}
                </div>
              ) : (
                <p className="rounded-2xl bg-amber-50 p-4 text-sm font-medium text-amber-800 border border-amber-200/60 dark:bg-amber-950/40 dark:text-amber-300">
                  No online payment methods are currently configured for this store. Please contact the business for help.
                </p>
              )}
            </div>

            {selectedMethod?.requiresEmail && (
              <div className="space-y-1.5">
                <Label htmlFor="pay-email" className="text-xs font-semibold text-slate-700 dark:text-slate-300">Email for Payment Receipt</Label>
                <Input
                  id="pay-email"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="you@example.com"
                  autoComplete="email"
                  className="rounded-xl"
                />
                <p className="text-xs text-slate-500">
                  Optional. Receipt will be sent to this email address.
                </p>
              </div>
            )}

            {selectedMethod?.requiresPhone && (
              <div className="space-y-1.5">
                <Label className="text-xs font-semibold text-slate-700 dark:text-slate-300">M-Pesa Phone Number</Label>
                <Input
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="e.g. 254712345678"
                  className="rounded-xl"
                />
                <p className="text-xs text-slate-500">
                  Enter your M-Pesa registered phone number to receive the payment prompt.
                </p>
              </div>
            )}

            {selectedMethod?.instructions && (
              <div className="rounded-2xl bg-slate-50 p-4 text-xs leading-relaxed text-slate-700 border border-slate-200/60 whitespace-pre-line break-words [overflow-wrap:anywhere] dark:bg-slate-800/40 dark:border-slate-800 dark:text-slate-300">
                {selectedMethod.instructions.split(/(https?:\/\/[^\s]+)/g).map((part, i) =>
                  part.match(/^https?:\/\//) ? (
                    <a
                      key={i}
                      href={part}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="font-medium text-blue-600 underline hover:text-blue-800 break-all dark:text-blue-400"
                    >
                      {part}
                    </a>
                  ) : (
                    part
                  )
                )}
              </div>
            )}

            <Button
              type="submit"
              disabled={!method || submitting}
              size="lg"
              className="w-full gap-2 rounded-2xl bg-slate-900 py-6 text-base font-semibold shadow-md transition-all hover:bg-slate-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"
            >
              {submitting ? 'Processing Payment…' : 'Proceed to Pay'} <ArrowRight className="h-5 w-5" />
            </Button>

            <div className="flex items-center justify-center gap-1.5 text-xs text-slate-400 pt-1">
              <Lock className="h-3.5 w-3.5" /> 256-Bit Encrypted & Secure Checkout
            </div>
          </form>
        )}
      </div>
    </div>
  )
}

