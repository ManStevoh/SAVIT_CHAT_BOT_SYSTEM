'use client'

import { FormEvent, useState } from 'react'
import { router } from '@inertiajs/react'
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
  mpesa: boolean
  manual: boolean
}

type Props = {
  token: string
  order: OrderPayload
  company: { name: string }
  paymentOptions: PaymentOptions
  status?: string
  errors?: Record<string, string>
}

export default function PublicPayPage({ token, order, company, paymentOptions, status, errors = {} }: Props) {
  const [method, setMethod] = useState<string>('')
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState(order.customerEmail ?? '')
  const [submitting, setSubmitting] = useState(false)

  const isPaid = order.paymentStatus === 'paid'

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

  // The server resolves this list through PaymentGatewayRegistry. Keep it intact:
  // hand-maintaining a subset here previously hid manual payment from public links.
  const availableMethods = paymentOptions.options
  const selectedMethod = availableMethods.find((option) => option.id === method)

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12">
      <div className="w-full max-w-md space-y-6 rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <div>
          <p className="text-sm uppercase tracking-[0.2em] text-slate-500">{company.name}</p>
          <h1 className="text-2xl font-semibold">Order #{order.orderNumber}</h1>
          <p className="text-slate-600">Amount due: {order.totalFormatted}</p>
        </div>

        {status && <p className="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700">{status}</p>}
        {errors.method && <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{errors.method}</p>}
        {errors.email && <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{errors.email}</p>}

        {isPaid ? (
          <div className="rounded-lg bg-emerald-50 p-4 text-center text-sm font-medium text-emerald-700">
            Payment received. Thank you!
          </div>
        ) : (
          <form onSubmit={submit} className="space-y-4">
            <div className="space-y-2">
              <Label>Choose a payment method</Label>
              {availableMethods.length > 0 ? (
              <div className="flex flex-wrap gap-2">
                {availableMethods.map((m) => (
                  <button
                    key={m.id}
                    type="button"
                    onClick={() => setMethod(m.id)}
                    className={`rounded-full border px-3 py-1.5 text-sm ${
                      method === m.id
                        ? 'border-slate-900 bg-slate-900 text-white'
                        : 'border-slate-200 bg-white hover:border-slate-400'
                    }`}
                  >
                    {m.label}
                  </button>
                ))}
              </div>
              ) : (
                <p className="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                  No online payment methods are currently available for this order. Please contact the business for help.
                </p>
              )}
            </div>

            {selectedMethod?.requiresEmail && (
              <div>
                <Label htmlFor="pay-email">Email for payment receipt</Label>
                <Input
                  id="pay-email"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="you@example.com"
                  autoComplete="email"
                />
                <p className="mt-1 text-xs text-slate-500">
                  Optional. If you leave this blank, we use your phone number to complete checkout.
                </p>
              </div>
            )}

            {selectedMethod?.requiresPhone && (
              <div>
                <Label>M-Pesa phone number</Label>
                <Input value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="254712345678" />
              </div>
            )}

            {selectedMethod?.instructions && (
              <p className="rounded-lg bg-slate-50 p-3 text-sm text-slate-700 whitespace-pre-line">
                {selectedMethod.instructions}
              </p>
            )}

            <Button type="submit" disabled={!method || submitting} className="w-full">
              {submitting ? 'Processing…' : 'Continue'}
            </Button>
          </form>
        )}
      </div>
    </div>
  )
}
