'use client'

import { useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import {
  ArrowLeft,
  ArrowRight,
  Check,
  Heart,
  LogOut,
  Plus,
  ShoppingBag,
  Sparkles,
  Trash2,
  User,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { StorefrontAuthModal } from '@/components/store/StorefrontAuthModal'
import { resolveStorefrontStyle, type BrandTheme } from '@/lib/theme-utils'

type StoreProduct = {
  id: string
  slug?: string | null
  name: string
  price: number
  compareAtPrice?: number | null
  onSale?: boolean
  discountPercent?: number | null
  soldOut?: boolean
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
    authCustomer?: { id: number; name: string; email: string } | null
    theme?: BrandTheme
  }
  products: StoreProduct[]
  wishlist?: string[]
  cartCount: number
  chrome?: { cart?: string; wishlist?: string }
  seo?: { title?: string; description?: string }
}

function formatPrice(amount: number, currency: string, rate: number = 1): string {
  const converted = amount * (rate || 1)
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(converted)
  } catch {
    return `${currency} ${converted.toFixed(2)}`
  }
}

export default function StoreWishlistPage({
  slug,
  company,
  products: initialProducts,
  cartCount,
  chrome,
  seo,
}: Props) {
  const [products, setProducts] = useState(initialProducts)
  const [busyId, setBusyId] = useState<string | null>(null)
  const [movingAll, setMovingAll] = useState(false)
  const [authModalOpen, setAuthModalOpen] = useState(false)

  const displayCurrency = company.displayCurrency || company.currency
  const displayRate = company.displayRate || 1.0

  const removeFromWishlist = async (productId: string) => {
    setBusyId(productId)
    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      const res = await fetch(`/s/${slug}/wishlist/toggle`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ productId: Number(productId) }),
      })
      if (res.ok) {
        setProducts((prev) => prev.filter((p) => p.id !== productId))
      }
    } finally {
      setBusyId(null)
    }
  }

  const addToCart = (productId: string) => {
    setBusyId(productId)
    router.post(
      `/s/${slug}/cart`,
      { productId, quantity: 1 },
      {
        preserveScroll: true,
        onFinish: () => setBusyId(null),
      }
    )
  }

  const moveAllToCart = async () => {
    setMovingAll(true)
    for (const product of products) {
      if (!product.soldOut) {
        await router.post(`/s/${slug}/cart`, { productId: product.id, quantity: 1 }, { preserveScroll: true })
      }
    }
    setMovingAll(false)
    router.visit(`/s/${slug}/cart`)
  }

  const clearAllWishlist = async () => {
    if (!confirm('Are you sure you want to clear your saved items?')) return
    for (const product of products) {
      await removeFromWishlist(product.id)
    }
  }

  const style = resolveStorefrontStyle(company.theme)

  return (
    <div
      className="min-h-screen bg-slate-50/80 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100"
      style={style}
    >
      <Head>
        <title>{seo?.title || `Wishlist — ${company.name}`}</title>
        {seo?.description ? <meta head-key="description" name="description" content={seo.description} /> : null}
      </Head>

      {/* Top Navbar */}
      <header className="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90">
        <div className="mx-auto flex max-w-4xl items-center justify-between gap-3 px-4 py-3.5">
          <Link
            href={`/s/${slug}`}
            className="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
          >
            <ArrowLeft className="h-4 w-4" /> Continue Shopping
          </Link>

          <div className="flex items-center gap-2">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-400">{company.name}</span>
            <span className="text-slate-300 dark:text-slate-700">|</span>
            <h1 className="text-sm font-bold tracking-tight text-slate-900 dark:text-white">Saved Wishlist</h1>
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

            <Link href={`/s/${slug}/cart`}>
              <Button size="sm" className="gap-2 rounded-xl bg-slate-900 text-xs font-semibold text-white shadow-xs hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900">
                <ShoppingBag className="h-3.5 w-3.5" />
                <span>{chrome?.cart || 'Cart'}</span>
                {cartCount > 0 && (
                  <span className="rounded-full bg-emerald-500 px-1.5 py-0.5 text-[10px] font-extrabold text-white">
                    {cartCount}
                  </span>
                )}
              </Button>
            </Link>
          </div>
        </div>
      </header>

      {/* Main Container */}
      <main className="mx-auto max-w-4xl px-4 py-8 pb-24">
        {products.length === 0 ? (
          <div className="mx-auto max-w-md rounded-3xl border border-slate-200/80 bg-white p-12 text-center shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-rose-50 text-rose-500 dark:bg-rose-950/40">
              <Heart className="h-8 w-8" />
            </div>
            <h2 className="text-lg font-bold tracking-tight text-slate-900 dark:text-white">Your Wishlist is Empty</h2>
            <p className="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
              Tap the heart icon on any product in our store to save it here for later.
            </p>
            <div className="mt-6">
              <Link href={`/s/${slug}`}>
                <Button size="lg" className="w-full gap-2 rounded-2xl bg-slate-900 text-xs font-semibold dark:bg-slate-100 dark:text-slate-900">
                  Browse {company.name} Products <ArrowRight className="h-4 w-4" />
                </Button>
              </Link>
            </div>
          </div>
        ) : (
          <div className="space-y-4">
            {/* Action header bar */}
            <div className="flex flex-wrap items-center justify-between gap-2 px-1">
              <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
                {products.length} Saved {products.length === 1 ? 'Item' : 'Items'}
              </p>

              <div className="flex items-center gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={clearAllWishlist}
                  className="rounded-xl border-slate-200 text-xs font-semibold text-slate-500 hover:text-rose-600 dark:border-slate-800"
                >
                  Clear Wishlist
                </Button>

                <Button
                  type="button"
                  size="sm"
                  disabled={movingAll}
                  onClick={moveAllToCart}
                  className="gap-1.5 px-3 text-xs font-bold text-white shadow-xs transition-opacity hover:opacity-90"
                  style={{
                    background: 'var(--sf-primary, #0f172a)',
                    borderRadius: 'var(--sf-radius, 0.75rem)',
                  }}
                >
                  <ShoppingBag className="h-3.5 w-3.5" />
                  {movingAll ? 'Moving…' : 'Move All to Cart'}
                </Button>
              </div>
            </div>

            {/* List */}
            <div
              className="overflow-hidden border border-slate-200/80 bg-white shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none"
              style={{ borderRadius: 'var(--sf-radius, 1.5rem)' }}
            >
              {products.map((product) => {
                const href = `/s/${slug}/p/${product.slug || product.id}`
                const busy = busyId === product.id
                return (
                  <div
                    key={product.id}
                    className="flex items-center justify-between gap-4 border-b border-slate-100 p-4 transition-colors last:border-b-0 dark:border-slate-800/80"
                  >
                    <div className="flex items-center gap-3.5">
                      <Link href={href} className="h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-slate-100 dark:bg-slate-800">
                        {product.image ? (
                          <img src={product.image} alt={product.name} className="h-full w-full object-cover" />
                        ) : (
                          <div className="flex h-full w-full items-center justify-center text-slate-300">
                            <ShoppingBag className="h-6 w-6" />
                          </div>
                        )}
                      </Link>
                      <div>
                        <Link href={href}>
                          <h3 className="text-xs font-bold text-slate-900 hover:text-emerald-600 transition-colors dark:text-white">
                            {product.name}
                          </h3>
                        </Link>
                        <div className="mt-1 flex items-baseline gap-2 text-xs">
                          <span className="font-extrabold text-slate-900 dark:text-white">
                            {formatPrice(product.price, displayCurrency, displayRate)}
                          </span>
                          {product.onSale && product.compareAtPrice != null && (
                            <span className="text-[11px] text-slate-400 line-through">
                              {formatPrice(product.compareAtPrice, displayCurrency, displayRate)}
                            </span>
                          )}
                          {product.soldOut && <span className="font-bold text-rose-600 text-[10px]">Sold out</span>}
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      <Button
                        type="button"
                        size="sm"
                        disabled={busy || !!product.soldOut}
                        onClick={() => addToCart(product.id)}
                        className="px-3.5 text-xs font-bold text-white shadow-xs transition-opacity hover:opacity-90"
                        style={{
                          background: 'var(--sf-primary, #0f172a)',
                          borderRadius: 'var(--sf-radius, 0.75rem)',
                        }}
                      >
                        <Plus className="h-3.5 w-3.5 mr-1" /> Add to Cart
                      </Button>

                      <button
                        type="button"
                        disabled={busy}
                        onClick={() => void removeFromWishlist(product.id)}
                        className="rounded-xl p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40 dark:hover:text-rose-400 transition-colors"
                        title="Remove from Wishlist"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                )
              })}
            </div>
          </div>
        )}
      </main>

      <footer className="border-t border-slate-200/80 py-8 text-center text-xs font-medium text-slate-400 dark:border-slate-800">
        {company.theme?.footer_text || 'Powered by RelayIQ'}
      </footer>

      <StorefrontAuthModal
        open={authModalOpen}
        onOpenChange={setAuthModalOpen}
        slug={slug}
        companyName={company.name}
      />
    </div>
  )
}

