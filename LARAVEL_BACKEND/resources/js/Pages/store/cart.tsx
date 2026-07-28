'use client'

import { Link, router } from '@inertiajs/react'
import { ArrowLeft, ShoppingBag, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'

type CartItem = {
  key: string
  name: string
  price: number
  quantity: number
  lineTotal: number
  image?: string | null
}

type CartSummary = {
  items: CartItem[]
  subtotal: number
  taxTotal: number
  total: number
  itemCount: number
}

type Props = {
  slug: string
  company: { name: string; currency: string; whatsappUrl?: string | null }
  cart: CartSummary
}

function formatPrice(amount: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount)
  } catch {
    return `${currency} ${amount.toFixed(2)}`
  }
}

export default function StoreCartPage({ slug, company, cart }: Props) {
  const updateQuantity = (key: string, quantity: number) => {
    router.post(`/s/${slug}/cart/update`, { key, quantity })
  }

  const clearCart = () => {
    router.post(`/s/${slug}/cart/clear`, {})
  }

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-3xl items-center justify-between px-4 py-4">
          <Link href={`/s/${slug}`} className="inline-flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900">
            <ArrowLeft className="h-4 w-4" /> Continue shopping
          </Link>
          <h1 className="text-lg font-semibold">Your cart</h1>
        </div>
      </header>

      <main className="mx-auto max-w-3xl px-4 py-8">
        {cart.items.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-slate-200 bg-white p-12 text-center text-slate-500">
            <ShoppingBag className="mx-auto mb-3 h-10 w-10 text-slate-300" />
            Your cart is empty.
            <div className="mt-4">
              <Link href={`/s/${slug}`}>
                <Button variant="outline">Browse {company.name}</Button>
              </Link>
            </div>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
              {cart.items.map((item) => (
                <div key={item.key} className="flex items-center gap-3 border-b border-slate-100 p-4 last:border-b-0">
                  <div className="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                    {item.image ? (
                      <img src={item.image} alt={item.name} className="h-full w-full object-cover" />
                    ) : null}
                  </div>
                  <div className="flex-1">
                    <p className="text-sm font-medium">{item.name}</p>
                    <p className="text-sm text-slate-500">{formatPrice(item.price, company.currency)}</p>
                  </div>
                  <div className="flex items-center rounded-full border border-slate-200">
                    <button
                      type="button"
                      onClick={() => updateQuantity(item.key, item.quantity - 1)}
                      className="px-3 py-1 text-slate-600 hover:text-slate-900"
                    >
                      −
                    </button>
                    <span className="w-6 text-center text-sm">{item.quantity}</span>
                    <button
                      type="button"
                      onClick={() => updateQuantity(item.key, item.quantity + 1)}
                      className="px-3 py-1 text-slate-600 hover:text-slate-900"
                    >
                      +
                    </button>
                  </div>
                  <p className="w-20 text-right text-sm font-medium">{formatPrice(item.lineTotal, company.currency)}</p>
                  <button
                    type="button"
                    onClick={() => updateQuantity(item.key, 0)}
                    className="text-slate-400 hover:text-red-500"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              ))}
            </div>

            <div className="rounded-2xl border border-slate-200 bg-white p-4">
              <div className="flex justify-between text-sm text-slate-600">
                <span>Subtotal</span>
                <span>{formatPrice(cart.subtotal, company.currency)}</span>
              </div>
              {cart.taxTotal > 0 && (
                <div className="flex justify-between text-sm text-slate-600">
                  <span>Tax</span>
                  <span>{formatPrice(cart.taxTotal, company.currency)}</span>
                </div>
              )}
              <div className="mt-2 flex justify-between border-t border-slate-100 pt-2 text-base font-semibold">
                <span>Total</span>
                <span>{formatPrice(cart.total, company.currency)}</span>
              </div>
            </div>

            <div className="flex items-center justify-between gap-3">
              <button type="button" onClick={clearCart} className="text-sm text-slate-500 hover:text-red-500">
                Clear cart
              </button>
              <div className="flex items-center gap-3">
                {company.whatsappUrl ? (
                  <a
                    href={company.whatsappUrl}
                    target="_blank"
                    rel="noreferrer"
                    className="text-sm font-medium text-emerald-700 hover:underline"
                  >
                    Help on WhatsApp
                  </a>
                ) : null}
                <Link href={`/s/${slug}/checkout`}>
                  <Button size="lg">Checkout</Button>
                </Link>
              </div>
            </div>
          </div>
        )}
      </main>
    </div>
  )
}
