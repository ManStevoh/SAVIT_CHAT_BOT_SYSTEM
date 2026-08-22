'use client'

import { useState } from 'react'
import { Link, router } from '@inertiajs/react'
import {
  ArrowLeft,
  ArrowRight,
  Check,
  Heart,
  LogOut,
  MessageCircle,
  Minus,
  Plus,
  ShieldCheck,
  ShoppingBag,
  Sparkles,
  Trash2,
  Truck,
  User,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { StorefrontAuthModal } from '@/components/store/StorefrontAuthModal'
import { resolveStorefrontStyle, type BrandTheme } from '@/lib/theme-utils'

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

type RelatedProduct = {
  id: string
  slug?: string | null
  name: string
  price: number
  compareAtPrice?: number | null
  onSale?: boolean
  image?: string | null
}

type Props = {
  slug: string
  company: {
    name: string
    logo?: string | null
    currency: string
    displayCurrency?: string
    displayRate?: number
    freeDeliveryAbove?: number | null
    whatsappUrl?: string | null
    authCustomer?: { id: number; name: string; email: string } | null
    theme?: BrandTheme
  }
  cart: CartSummary
  related?: RelatedProduct[]
}

function formatPrice(amount: number, currency: string, rate: number = 1): string {
  const converted = amount * (rate || 1)
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(converted)
  } catch {
    return `${currency} ${converted.toFixed(2)}`
  }
}

export default function StoreCartPage({ slug, company, cart, related = [] }: Props) {
  const [authModalOpen, setAuthModalOpen] = useState(false)
  const displayCurrency = company.displayCurrency || company.currency
  const displayRate = company.displayRate || 1.0
  const style = resolveStorefrontStyle(company.theme)

  const updateQuantity = (key: string, quantity: number) => {
    router.post(`/s/${slug}/cart/update`, { key, quantity }, { preserveScroll: true })
  }

  const clearCart = () => {
    if (confirm('Are you sure you want to clear your cart?')) {
      router.post(`/s/${slug}/cart/clear`, {})
    }
  }

  const addRecommendedItem = (productId: string) => {
    router.post(
      `/s/${slug}/cart`,
      { productId, quantity: 1 },
      { preserveScroll: true }
    )
  }

  // Free delivery calculation
  const freeDeliveryThreshold = company.freeDeliveryAbove ?? null
  const hasFreeDeliveryGoal = freeDeliveryThreshold != null && freeDeliveryThreshold > 0
  const freeDeliveryRemaining = hasFreeDeliveryGoal ? Math.max(0, freeDeliveryThreshold - cart.subtotal) : 0
  const freeDeliveryProgress = hasFreeDeliveryGoal
    ? Math.min(100, Math.round((cart.subtotal / freeDeliveryThreshold) * 100))
    : 0
  const unlockedFreeDelivery = hasFreeDeliveryGoal && freeDeliveryRemaining <= 0

  return (
    <div
      className="min-h-screen bg-slate-50/80 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100"
      style={style}
    >
      {/* Top Navbar */}
      <header className="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90">
        <div className="mx-auto flex max-w-4xl items-center justify-between px-4 py-3.5">
          <Link
            href={`/s/${slug}`}
            className="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
          >
            <ArrowLeft className="h-4 w-4" /> Continue Shopping
          </Link>

          <div className="flex items-center gap-2">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-400">{company.name}</span>
            <span className="text-slate-300 dark:text-slate-700">|</span>
            <h1 className="text-sm font-bold tracking-tight text-slate-900 dark:text-white">Shopping Cart</h1>
          </div>

          <div className="flex items-center gap-2">
            {company.authCustomer ? (
              <div className="flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                <User className="h-3.5 w-3.5" />
                <span>{company.authCustomer.name.split(' ')[0]}</span>
                <button
                  type="button"
                  onClick={() => router.post(`/s/${slug}/account/logout`)}
                  className="ml-1 text-slate-400 hover:text-slate-900 dark:hover:text-white"
                  title="Sign Out"
                >
                  <LogOut className="h-3.5 w-3.5" />
                </button>
              </div>
            ) : (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => setAuthModalOpen(true)}
                className="gap-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
              >
                <User className="h-3.5 w-3.5" /> Sign In
              </Button>
            )}
          </div>
        </div>
      </header>

      {/* Main Container */}
      <main className="mx-auto max-w-4xl px-4 py-8 pb-24 space-y-8">
        {cart.items.length === 0 ? (
          /* Empty Cart State */
          <div className="mx-auto max-w-md rounded-3xl border border-slate-200/80 bg-white p-12 text-center shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
              <ShoppingBag className="h-8 w-8" />
            </div>
            <h2 className="text-lg font-bold tracking-tight text-slate-900 dark:text-white">Your Cart is Empty</h2>
            <p className="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
              You haven't added any products to your cart yet. Explore our store to find your favorite items.
            </p>
            <div className="mt-6">
              <Link href={`/s/${slug}`}>
                <Button size="lg" className="w-full gap-2 rounded-2xl bg-slate-900 text-xs font-semibold dark:bg-slate-100 dark:text-slate-900">
                  Explore {company.name} Products <ArrowRight className="h-4 w-4" />
                </Button>
              </Link>
            </div>
          </div>
        ) : (
          /* Active Cart State */
          <div className="space-y-6">
            
            {/* Free Delivery Goal Card */}
            {hasFreeDeliveryGoal && (
              <div className="rounded-3xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div className="flex items-center justify-between text-xs font-bold">
                  <span className="flex items-center gap-1.5 text-slate-900 dark:text-white">
                    <Truck className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                    {unlockedFreeDelivery ? (
                      <span className="text-emerald-700 dark:text-emerald-400">You've qualified for FREE delivery! 🎉</span>
                    ) : (
                      <span>
                        Add <strong className="text-emerald-700 dark:text-emerald-400">{formatPrice(freeDeliveryRemaining, displayCurrency, displayRate)}</strong> more to get FREE Delivery!
                      </span>
                    )}
                  </span>
                  <span className="text-slate-400">{freeDeliveryProgress}%</span>
                </div>
                <div className="mt-2.5 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                  <div
                    className="h-full rounded-full bg-emerald-500 transition-all duration-500"
                    style={{ width: `${freeDeliveryProgress}%` }}
                  />
                </div>
              </div>
            )}

            <div className="grid gap-6 lg:grid-cols-12">
              
              {/* Left Column: Cart Items List */}
              <div className="space-y-4 lg:col-span-7">
                <div className="flex items-center justify-between px-1">
                  <h2 className="text-xs font-bold uppercase tracking-wider text-slate-400">Items in Cart ({cart.itemCount})</h2>
                  <button
                    type="button"
                    onClick={clearCart}
                    className="text-xs font-semibold text-rose-600 transition-colors hover:text-rose-700 dark:text-rose-400"
                  >
                    Clear Cart
                  </button>
                </div>

                <div className="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
                  {cart.items.map((item) => (
                    <div
                      key={item.key}
                      className="flex items-center justify-between gap-4 border-b border-slate-100 p-4 transition-colors last:border-b-0 dark:border-slate-800/80"
                    >
                      {/* Image & Title */}
                      <div className="flex items-center gap-3.5">
                        <div className="h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-slate-100 dark:bg-slate-800">
                          {item.image ? (
                            <img src={item.image} alt={item.name} className="h-full w-full object-cover" />
                          ) : (
                            <div className="flex h-full w-full items-center justify-center text-slate-300 dark:text-slate-600">
                              <ShoppingBag className="h-6 w-6" />
                            </div>
                          )}
                        </div>
                        <div>
                          <h3 className="text-xs font-bold text-slate-900 dark:text-white">{item.name}</h3>
                          <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {formatPrice(item.price, displayCurrency, displayRate)} each
                          </p>
                        </div>
                      </div>

                      {/* Quantity Stepper & Line Total */}
                      <div className="flex items-center gap-3.5">
                        <div className="flex items-center rounded-xl border border-slate-200 bg-slate-50 p-0.5 dark:border-slate-700 dark:bg-slate-800">
                          <button
                            type="button"
                            onClick={() => updateQuantity(item.key, item.quantity - 1)}
                            className="flex h-7 w-7 items-center justify-center rounded-lg text-slate-600 transition-colors hover:bg-white hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                            title="Decrease Quantity"
                          >
                            <Minus className="h-3.5 w-3.5" />
                          </button>
                          <span className="w-8 text-center text-xs font-bold text-slate-800 dark:text-slate-200">{item.quantity}</span>
                          <button
                            type="button"
                            onClick={() => updateQuantity(item.key, item.quantity + 1)}
                            className="flex h-7 w-7 items-center justify-center rounded-lg text-slate-600 transition-colors hover:bg-white hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                            title="Increase Quantity"
                          >
                            <Plus className="h-3.5 w-3.5" />
                          </button>
                        </div>

                        <div className="text-right">
                          <p className="text-xs font-extrabold text-slate-900 dark:text-white">
                            {formatPrice(item.lineTotal, displayCurrency, displayRate)}
                          </p>
                        </div>

                        <button
                          type="button"
                          onClick={() => updateQuantity(item.key, 0)}
                          className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40 dark:hover:text-rose-400"
                          title="Remove Item"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Right Column: Order Summary Card */}
              <div className="lg:col-span-5">
                <div className="sticky top-20 space-y-5 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
                  <div className="border-b border-slate-100 pb-3 dark:border-slate-800">
                    <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">Order Summary</h3>
                  </div>

                  <div className="space-y-2.5 text-xs">
                    <div className="flex justify-between text-slate-600 dark:text-slate-400">
                      <span>Subtotal</span>
                      <span className="font-semibold text-slate-800 dark:text-slate-200">
                        {formatPrice(cart.subtotal, displayCurrency, displayRate)}
                      </span>
                    </div>

                    {cart.taxTotal > 0 && (
                      <div className="flex justify-between text-slate-600 dark:text-slate-400">
                        <span>Estimated Tax</span>
                        <span className="font-semibold text-slate-800 dark:text-slate-200">
                          {formatPrice(cart.taxTotal, displayCurrency, displayRate)}
                        </span>
                      </div>
                    )}

                    <div className="flex items-baseline justify-between border-t border-slate-200/80 pt-3 text-base font-extrabold text-slate-900 dark:border-slate-700 dark:text-white">
                      <span>Subtotal Due</span>
                      <span className="text-xl text-emerald-600 dark:text-emerald-400">
                        {formatPrice(cart.total, displayCurrency, displayRate)}
                      </span>
                    </div>
                  </div>

                  <div className="space-y-3 pt-2">
                    <Link href={`/s/${slug}/checkout`} className="block">
                      <Button size="lg" className="w-full gap-2 rounded-2xl bg-slate-900 py-6 text-sm font-semibold shadow-md transition-all hover:bg-slate-800 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                        Proceed to Checkout <ArrowRight className="h-4 w-4" />
                      </Button>
                    </Link>

                    {company.whatsappUrl && (
                      <a
                        href={company.whatsappUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="flex w-full items-center justify-center gap-1.5 text-xs font-semibold text-emerald-700 transition-colors hover:underline dark:text-emerald-400"
                      >
                        <MessageCircle className="h-3.5 w-3.5" /> Need help? Chat on WhatsApp
                      </a>
                    )}
                  </div>

                  <div className="flex items-center justify-center gap-1.5 border-t border-slate-100 pt-3 text-[11px] font-medium text-slate-400 dark:border-slate-800">
                    <ShieldCheck className="h-3.5 w-3.5 text-emerald-600" /> Guaranteed Secure & Encryption
                  </div>
                </div>
              </div>
            </div>

            {/* Cross-Sell Recommendations */}
            {related.length > 0 && (
              <div className="space-y-4 pt-6 border-t border-slate-200/80 dark:border-slate-800">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-1.5">
                    <Sparkles className="h-4 w-4 text-amber-500" /> You May Also Like
                  </h3>
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                  {related.map((item) => (
                    <div
                      key={item.id}
                      className="group flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-3 shadow-xs dark:border-slate-800 dark:bg-slate-900"
                    >
                      <Link href={`/s/${slug}/p/${item.slug || item.id}`}>
                        <div className="aspect-square w-full overflow-hidden rounded-xl bg-slate-100 dark:bg-slate-800 mb-2">
                          {item.image ? (
                            <img src={item.image} alt={item.name} className="h-full w-full object-cover group-hover:scale-105 transition-transform" />
                          ) : (
                            <div className="flex h-full w-full items-center justify-center text-slate-300">
                              <ShoppingBag className="h-6 w-6" />
                            </div>
                          )}
                        </div>
                        <h4 className="line-clamp-1 text-xs font-bold text-slate-900 dark:text-white">{item.name}</h4>
                        <p className="mt-0.5 text-xs font-extrabold text-slate-900 dark:text-white">
                          {formatPrice(item.price, displayCurrency, displayRate)}
                        </p>
                      </Link>
                      <Button
                        type="button"
                        size="sm"
                        onClick={() => addRecommendedItem(item.id)}
                        className="mt-2.5 h-7 w-full rounded-xl bg-slate-900 text-[11px] font-bold text-white hover:bg-slate-800 dark:bg-emerald-600"
                      >
                        <Plus className="h-3 w-3 mr-1" /> Add
                      </Button>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}
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

