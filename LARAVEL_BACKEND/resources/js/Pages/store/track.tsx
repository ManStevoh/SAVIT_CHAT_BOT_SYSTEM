'use client'

import { FormEvent, useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  Clock,
  CreditCard,
  FileText,
  MapPin,
  Package,
  Phone,
  Search,
  ShieldCheck,
  ShoppingBag,
  Truck,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { resolveStorefrontStyle, type BrandTheme } from '@/lib/theme-utils'

type OrderPayload = {
  orderNumber: string
  status: string
  paymentStatus: string
  total: number
  payToken?: string | null
  invoiceToken?: string | null
  createdAt?: string | null
  items: { name: string; quantity: number; price: number }[]
}

type Props = {
  slug: string
  company: {
    name: string
    currency: string
    displayCurrency?: string
    displayRate?: number
    logo?: string | null
    theme?: BrandTheme
  }
  order: OrderPayload | null
  defaultPhone?: string
  notFound: boolean
}

function formatPrice(amount: number, currency: string, rate: number = 1): string {
  const converted = amount * (rate || 1)
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(converted)
  } catch {
    return `${currency} ${converted.toFixed(2)}`
  }
}

export default function StoreTrackPage({ slug, company, order, defaultPhone = '', notFound }: Props) {
  const [phone, setPhone] = useState(defaultPhone)
  const [orderNumber, setOrderNumber] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const displayCurrency = company.displayCurrency || company.currency
  const displayRate = company.displayRate || 1.0

  const onSubmit = (e: FormEvent) => {
    e.preventDefault()
    setSubmitting(true)
    router.post(`/s/${slug}/track`, { phone, orderNumber }, { onFinish: () => setSubmitting(false) })
  }

  // Calculate order step index (0 to 4)
  const getStepIndex = (status?: string) => {
    const s = (status || '').toLowerCase()
    if (s === 'delivered' || s === 'completed') return 4
    if (s === 'out_for_delivery' || s === 'shipping' || s === 'in_transit') return 3
    if (s === 'preparing' || s === 'processing' || s === 'in_kitchen') return 2
    if (s === 'confirmed' || s === 'accepted') return 1
    return 0 // pending / created
  }

  const currentStep = getStepIndex(order?.status)

  const steps = [
    { label: 'Order Placed', icon: ShoppingBag },
    { label: 'Confirmed', icon: CheckCircle2 },
    { label: 'Preparing', icon: Package },
    { label: 'Out for Delivery', icon: Truck },
    { label: 'Delivered', icon: CheckCircle2 },
  ]

  const style = resolveStorefrontStyle(company.theme)

  return (
    <div
      className="min-h-screen bg-slate-50/80 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100"
      style={style}
    >
      <Head title={`Track order — ${company.name}`} />

      <header className="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90">
        <div className="mx-auto flex max-w-2xl items-center justify-between px-4 py-3.5">
          <Link
            href={`/s/${slug}`}
            className="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
          >
            <ArrowLeft className="h-4 w-4" /> Back to Store
          </Link>
          <h1 className="text-sm font-bold tracking-tight text-slate-900 dark:text-white">Track Your Order</h1>
        </div>
      </header>

      <main className="mx-auto max-w-2xl space-y-6 px-4 py-8 pb-24">
        {/* Lookup Form */}
        <form onSubmit={onSubmit} className="space-y-4 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
          <div>
            <h2 className="text-base font-extrabold text-slate-900 dark:text-white">Find Your Order</h2>
            <p className="text-xs text-slate-500 mt-0.5">Enter your WhatsApp phone number and Order ID to check live status.</p>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label className="text-xs font-semibold text-slate-700 dark:text-slate-300">Phone Number</Label>
              <div className="relative">
                <Phone className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
                <Input
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  required
                  placeholder="254712345678"
                  className="pl-10 rounded-2xl text-xs"
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <Label className="text-xs font-semibold text-slate-700 dark:text-slate-300">Order Number</Label>
              <div className="relative">
                <Search className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
                <Input
                  value={orderNumber}
                  onChange={(e) => setOrderNumber(e.target.value)}
                  required
                  placeholder="ORD-XXXXXX or Number"
                  className="pl-10 rounded-2xl text-xs uppercase"
                />
              </div>
            </div>
          </div>

          <Button
            type="submit"
            disabled={submitting}
            className="w-full gap-2 rounded-2xl bg-slate-900 py-5 text-xs font-bold text-white shadow-md hover:bg-slate-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"
          >
            {submitting ? 'Looking up order…' : 'Track Order Status'} <ArrowRight className="h-4 w-4" />
          </Button>
        </form>

        {/* Not Found Alert */}
        {notFound && (
          <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs font-medium text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300">
            We couldn't find an order matching that phone number and order ID. Please double check the details and try again.
          </div>
        )}

        {/* Order Details & Visual Timeline */}
        {order && (
          <div className="space-y-6 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
            
            {/* Header info */}
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4 dark:border-slate-800">
              <div>
                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Order Reference</span>
                <p className="text-base font-extrabold text-slate-900 dark:text-white">#{order.orderNumber}</p>
              </div>

              <div className="flex items-center gap-2">
                <span
                  className={`rounded-full px-3 py-1 text-xs font-bold capitalize ${
                    order.status === 'delivered' || order.status === 'completed'
                      ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:border-emerald-900/50 dark:text-emerald-300'
                      : 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/40 dark:border-blue-900/50 dark:text-blue-300'
                  }`}
                >
                  {order.status.replace('_', ' ')}
                </span>

                <span
                  className={`rounded-full px-3 py-1 text-xs font-bold capitalize ${
                    order.paymentStatus === 'paid'
                      ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:border-emerald-900/50 dark:text-emerald-300'
                      : 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:border-amber-900/50 dark:text-amber-300'
                  }`}
                >
                  {order.paymentStatus}
                </span>
              </div>
            </div>

            {/* Visual Fulfillment Stepper */}
            <div className="py-2">
              <p className="mb-4 text-xs font-bold uppercase tracking-wider text-slate-400">Fulfillment Status</p>
              <div className="grid grid-cols-5 gap-1 text-center">
                {steps.map((step, idx) => {
                  const Icon = step.icon
                  const isDone = idx <= currentStep
                  const isCurrent = idx === currentStep

                  return (
                    <div key={step.label} className="flex flex-col items-center gap-1.5">
                      <div
                        className={`flex h-9 w-9 items-center justify-center rounded-2xl transition-all ${
                          isCurrent
                            ? 'bg-slate-900 text-white ring-4 ring-slate-900/20 dark:bg-emerald-500 dark:ring-emerald-500/20 shadow-md'
                            : isDone
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'
                            : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-600'
                        }`}
                      >
                        <Icon className="h-4 w-4" />
                      </div>
                      <span
                        className={`text-[10px] leading-tight font-semibold ${
                          isDone ? 'text-slate-900 dark:text-white' : 'text-slate-400 dark:text-slate-600'
                        }`}
                      >
                        {step.label}
                      </span>
                    </div>
                  )
                })}
              </div>
            </div>

            {/* Ordered Items List */}
            <div className="space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
              <p className="text-xs font-bold uppercase tracking-wider text-slate-400">Items in this Order</p>
              <ul className="divide-y divide-slate-100 text-xs dark:divide-slate-800">
                {order.items.map((item, idx) => (
                  <li key={idx} className="flex items-center justify-between py-2.5">
                    <span className="font-medium text-slate-800 dark:text-slate-200">
                      {item.quantity}× {item.name}
                    </span>
                    <span className="font-bold text-slate-900 dark:text-white">
                      {formatPrice(item.price * item.quantity, displayCurrency, displayRate)}
                    </span>
                  </li>
                ))}
              </ul>

              <div className="flex items-baseline justify-between border-t border-slate-200/80 pt-3 text-sm font-extrabold text-slate-900 dark:border-slate-700 dark:text-white">
                <span>Total Amount</span>
                <span className="text-base text-emerald-600 dark:text-emerald-400">
                  {formatPrice(order.total, displayCurrency, displayRate)}
                </span>
              </div>
            </div>

            {/* Actions for pending payment */}
            <div className="flex flex-col gap-2 pt-2 sm:flex-row">
              {order.paymentStatus !== 'paid' && order.payToken && (
                <Button asChild size="lg" className="flex-1 gap-2 rounded-2xl bg-emerald-600 font-bold text-white hover:bg-emerald-700 shadow-md">
                  <Link href={`/pay/${order.payToken}`}>
                    <CreditCard className="h-4 w-4" /> Complete Payment Now
                  </Link>
                </Button>
              )}

              {order.invoiceToken && (
                <Button asChild variant="outline" size="lg" className="flex-1 gap-2 rounded-2xl border-slate-200 text-xs font-semibold dark:border-slate-800">
                  <a href={`/invoice/${order.invoiceToken}`} target="_blank" rel="noreferrer">
                    <FileText className="h-4 w-4" /> View / Print Receipt
                  </a>
                </Button>
              )}
            </div>
          </div>
        )}
      </main>
    </div>
  )
}

