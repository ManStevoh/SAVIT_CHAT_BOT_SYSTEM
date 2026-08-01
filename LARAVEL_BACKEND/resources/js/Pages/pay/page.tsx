'use client'

import { FormEvent, useState } from 'react'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

type OrderPayload = {
  orderNumber: string
  customerName: string
  totalFormatted: string
  paymentStatus: string
  paymentMethod?: string | null
  items: { name: string; quantity: number; lineSubtotal: number }[]
}

type PaymentOptions = {
  cod: boolean
  stripe: boolean
  paystack: boolean
  mpesa: boolean
  bankTransfer: boolean
  bankTransferInstructions?: string | null
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
  const [submitting, setSubmitting] = useState(false)

  const isPaid = order.paymentStatus === 'paid'

  const submit = (e: FormEvent) => {
    e.preventDefault()
    if (!method) return
    setSubmitting(true)
    router.post(
      `/pay/${token}`,
      { method, phone: phone || null },
      { onFinish: () => setSubmitting(false) }
    )
  }

  const availableMethods = [
    paymentOptions.mpesa && { key: 'mpesa', label: 'M-Pesa' },
    paymentOptions.stripe && { key: 'stripe', label: 'Card (Stripe)' },
    paymentOptions.paystack && { key: 'paystack', label: 'Paystack' },
    paymentOptions.cod && { key: 'cod', label: 'Cash on delivery' },
  ].filter(Boolean) as { key: string; label: string }[]

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

        {isPaid ? (
          <div className="rounded-lg bg-emerald-50 p-4 text-center text-sm font-medium text-emerald-700">
            Payment received. Thank you!
          </div>
        ) : (
          <form onSubmit={submit} className="space-y-4">
            <div className="space-y-2">
              <Label>Choose a payment method</Label>
              <div className="flex flex-wrap gap-2">
                {availableMethods.map((m) => (
                  <button
                    key={m.key}
                    type="button"
                    onClick={() => setMethod(m.key)}
                    className={`rounded-full border px-3 py-1.5 text-sm ${
                      method === m.key
                        ? 'border-slate-900 bg-slate-900 text-white'
                        : 'border-slate-200 bg-white hover:border-slate-400'
                    }`}
                  >
                    {m.label}
                  </button>
                ))}
              </div>
            </div>

            {method === 'mpesa' && (
              <div>
                <Label>M-Pesa phone number</Label>
                <Input value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="254712345678" />
              </div>
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
