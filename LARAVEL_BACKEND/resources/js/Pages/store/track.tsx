'use client'

import { FormEvent, useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import { ArrowLeft } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

type OrderPayload = {
  orderNumber: string
  status: string
  paymentStatus: string
  total: number
  payToken?: string | null
  items: { name: string; quantity: number; price: number }[]
}

type Props = {
  slug: string
  company: { name: string; currency: string }
  order: OrderPayload | null
  notFound: boolean
}

function formatPrice(amount: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount)
  } catch {
    return `${currency} ${amount.toFixed(2)}`
  }
}

export default function StoreTrackPage({ slug, company, order, notFound }: Props) {
  const [phone, setPhone] = useState('')
  const [orderNumber, setOrderNumber] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const onSubmit = (e: FormEvent) => {
    e.preventDefault()
    setSubmitting(true)
    router.post(`/s/${slug}/track`, { phone, orderNumber }, { onFinish: () => setSubmitting(false) })
  }

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900">
      <Head title={`Track order — ${company.name}`} />
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-lg items-center justify-between px-4 py-4">
          <Link href={`/s/${slug}`} className="inline-flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900">
            <ArrowLeft className="h-4 w-4" /> Back to shop
          </Link>
          <h1 className="text-lg font-semibold">Track order</h1>
        </div>
      </header>

      <main className="mx-auto max-w-lg space-y-6 px-4 py-8">
        <form onSubmit={onSubmit} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div>
            <Label>Phone number</Label>
            <Input value={phone} onChange={(e) => setPhone(e.target.value)} required placeholder="2547..." />
          </div>
          <div>
            <Label>Order number</Label>
            <Input value={orderNumber} onChange={(e) => setOrderNumber(e.target.value)} required placeholder="ORD-XXXXXXXX" />
          </div>
          <Button type="submit" disabled={submitting} className="w-full">
            {submitting ? 'Looking up…' : 'Find order'}
          </Button>
        </form>

        {notFound && (
          <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            We could not find an order with those details.
          </p>
        )}

        {order && (
          <div className="space-y-3 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-sm text-slate-500">Order</p>
                <p className="font-semibold">{order.orderNumber}</p>
              </div>
              <div className="text-right text-sm">
                <p className="capitalize text-slate-600">{order.status}</p>
                <p className="capitalize text-slate-500">Payment: {order.paymentStatus}</p>
              </div>
            </div>
            <ul className="divide-y divide-slate-100 text-sm">
              {order.items.map((item, idx) => (
                <li key={idx} className="flex justify-between py-2">
                  <span>
                    {item.name} × {item.quantity}
                  </span>
                  <span>{formatPrice(item.price * item.quantity, company.currency)}</span>
                </li>
              ))}
            </ul>
            <p className="text-right font-semibold">{formatPrice(order.total, company.currency)}</p>
            {order.paymentStatus !== 'paid' && order.payToken && (
              <Link href={`/pay/${order.payToken}`}>
                <Button className="w-full">Pay now</Button>
              </Link>
            )}
          </div>
        )}
      </main>
    </div>
  )
}
