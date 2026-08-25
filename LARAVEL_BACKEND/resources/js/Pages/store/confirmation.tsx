'use client'

import { useState } from 'react'
import { Link } from '@inertiajs/react'
import { CheckCircle2, Copy, Check, ShoppingBag, ArrowRight } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { resolveStorefrontStyle, type BrandTheme } from '@/lib/theme-utils'

type OrderItem = { name: string; quantity: number; lineSubtotal: number }
type OrderPayload = {
  id?: string
  orderNumber: string
  customerName: string
  totalFormatted: string
  payToken: string
  items: OrderItem[]
}

type Props = {
  slug: string
  company: { name: string; logo?: string | null; theme?: BrandTheme }
  order: OrderPayload
}

export default function StoreConfirmationPage({ slug, company, order }: Props) {
  const [copied, setCopied] = useState(false)
  const payUrl = `/pay/${order.payToken || order.id}`
  const style = resolveStorefrontStyle(company.theme)

  const copyPayLink = () => {
    if (typeof window !== 'undefined') {
      const fullUrl = `${window.location.origin}${payUrl}`
      navigator.clipboard.writeText(fullUrl)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    }
  }

  return (
    <div
      className="flex min-h-screen items-center justify-center bg-slate-50/80 px-4 py-12 dark:bg-slate-950"
      style={style}
    >
      <div className="w-full max-w-lg space-y-6 rounded-3xl border border-slate-200/80 bg-white p-8 text-center shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
        
        {/* Animated Check Icon */}
        <div className="relative mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-8 ring-emerald-50/50 dark:bg-emerald-950/40 dark:text-emerald-400 dark:ring-emerald-950/20">
          <CheckCircle2 className="h-10 w-10 stroke-[2.2]" />
        </div>

        {/* Heading & Summary */}
        <div className="space-y-1">
          <span className="inline-block rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
            Order #{order.orderNumber}
          </span>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Order Confirmed!</h1>
          <p className="text-sm text-slate-600 dark:text-slate-400">
            Thanks <strong className="font-semibold text-slate-900 dark:text-white">{order.customerName}</strong>! <strong className="font-semibold text-slate-900 dark:text-white">{company.name}</strong> has received your order.
          </p>
        </div>

        {/* Itemized Receipt Box */}
        <div className="space-y-3 rounded-2xl border border-slate-100 bg-slate-50/70 p-5 text-left text-sm dark:border-slate-800/80 dark:bg-slate-800/40">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-400">Order Summary</p>
          <div className="space-y-2">
            {order.items.map((item, idx) => (
              <div key={idx} className="flex justify-between text-slate-700 dark:text-slate-300">
                <span className="font-medium">
                  {item.quantity} × {item.name}
                </span>
                <span className="font-semibold">{item.lineSubtotal.toFixed(2)}</span>
              </div>
            ))}
          </div>
          <div className="flex justify-between border-t border-slate-200/80 pt-3 text-base font-bold text-slate-900 dark:border-slate-700 dark:text-white">
            <span>Total Amount</span>
            <span className="text-emerald-600 dark:text-emerald-400">{order.totalFormatted}</span>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-center">
          <Button asChild size="lg" className="gap-2 bg-emerald-600 shadow-md transition-all hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-500">
            <a href={payUrl}>
              Complete Payment <ArrowRight className="h-4 w-4" />
            </a>
          </Button>

          <Button
            type="button"
            variant="outline"
            size="lg"
            onClick={copyPayLink}
            className="gap-2 border-slate-200/80 hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
          >
            {copied ? <Check className="h-4 w-4 text-emerald-600" /> : <Copy className="h-4 w-4" />}
            {copied ? 'Link Copied!' : 'Copy Payment Link'}
          </Button>
        </div>

        {/* Continue Shopping Footer */}
        <div className="border-t border-slate-100 pt-4 dark:border-slate-800">
          <Link href={`/s/${slug}`} className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
            <ShoppingBag className="h-3.5 w-3.5" /> Continue Shopping at {company.name}
          </Link>
        </div>
      </div>
    </div>
  )
}
