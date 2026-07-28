'use client'

import { Link } from '@inertiajs/react'
import { CheckCircle2 } from 'lucide-react'
import { Button } from '@/components/ui/button'

type OrderItem = { name: string; quantity: number; lineSubtotal: number }
type OrderPayload = {
  orderNumber: string
  customerName: string
  totalFormatted: string
  payToken: string
  items: OrderItem[]
}

type Props = {
  slug: string
  company: { name: string }
  order: OrderPayload
}

export default function StoreConfirmationPage({ slug, company, order }: Props) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12">
      <div className="w-full max-w-lg space-y-6 rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
        <CheckCircle2 className="mx-auto h-12 w-12 text-emerald-500" />
        <div>
          <h1 className="text-2xl font-semibold">Order confirmed!</h1>
          <p className="mt-1 text-slate-600">
            Thanks {order.customerName}, {company.name} received your order #{order.orderNumber}.
          </p>
        </div>

        <div className="space-y-1 rounded-xl bg-slate-50 p-4 text-left text-sm">
          {order.items.map((item, idx) => (
            <div key={idx} className="flex justify-between">
              <span>
                {item.quantity} × {item.name}
              </span>
              <span>{item.lineSubtotal.toFixed(2)}</span>
            </div>
          ))}
          <div className="mt-2 flex justify-between border-t border-slate-200 pt-2 font-semibold">
            <span>Total</span>
            <span>{order.totalFormatted}</span>
          </div>
        </div>

        <div className="flex flex-wrap justify-center gap-2">
          <Button asChild>
            <a href={`/pay/${order.payToken}`}>Complete payment</a>
          </Button>
          <Link href={`/s/${slug}`}>
            <Button variant="outline">Continue shopping</Button>
          </Link>
        </div>
      </div>
    </div>
  )
}
