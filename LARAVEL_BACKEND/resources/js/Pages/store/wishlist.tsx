'use client'

import { useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import { ArrowLeft, Heart, ShoppingBag, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'

type StoreProduct = {
  id: string
  slug?: string | null
  name: string
  price: number
  compareAtPrice?: number | null
  onSale?: boolean
  soldOut?: boolean
  image?: string | null
}

type Props = {
  slug: string
  company: { name: string; currency: string }
  products: StoreProduct[]
  wishlist?: string[]
  cartCount: number
  chrome?: { cart?: string; wishlist?: string }
  seo?: { title?: string; description?: string }
}

function formatPrice(amount: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount)
  } catch {
    return `${currency} ${amount.toFixed(2)}`
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
        body: JSON.stringify({ productId }),
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
      { onFinish: () => setBusyId(null) }
    )
  }

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900">
      <Head>
        <title>{seo?.title || `Wishlist — ${company.name}`}</title>
        {seo?.description ? <meta head-key="description" name="description" content={seo.description} /> : null}
      </Head>

      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-4">
          <Link href={`/s/${slug}`} className="inline-flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900">
            <ArrowLeft className="h-4 w-4" /> Continue shopping
          </Link>
          <div className="flex items-center gap-2">
            <h1 className="text-lg font-semibold">{chrome?.wishlist || 'Wishlist'}</h1>
            <Link href={`/s/${slug}/cart`}>
              <Button variant="outline" className="gap-2">
                <ShoppingBag className="h-4 w-4" />
                {chrome?.cart || 'Cart'}
                {cartCount > 0 ? ` (${cartCount})` : ''}
              </Button>
            </Link>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-3xl px-4 py-8">
        {products.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-slate-200 bg-white p-12 text-center text-slate-500">
            <Heart className="mx-auto mb-3 h-10 w-10 text-slate-300" />
            Your wishlist is empty.
            <div className="mt-4">
              <Link href={`/s/${slug}`}>
                <Button variant="outline">Browse {company.name}</Button>
              </Link>
            </div>
          </div>
        ) : (
          <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            {products.map((product) => {
              const href = `/s/${slug}/p/${product.slug || product.id}`
              const busy = busyId === product.id
              return (
                <div key={product.id} className="flex items-center gap-3 border-b border-slate-100 p-4 last:border-b-0">
                  <Link href={href} className="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                    {product.image ? (
                      <img src={product.image} alt={product.name} className="h-full w-full object-cover" />
                    ) : (
                      <div className="flex h-full w-full items-center justify-center text-slate-300">
                        <ShoppingBag className="h-6 w-6" />
                      </div>
                    )}
                  </Link>
                  <div className="min-w-0 flex-1">
                    <Link href={href} className="text-sm font-medium hover:underline">
                      {product.name}
                    </Link>
                    <div className="mt-0.5 flex items-center gap-2 text-sm text-slate-500">
                      <span className="font-medium text-slate-900">{formatPrice(product.price, company.currency)}</span>
                      {product.onSale && product.compareAtPrice != null ? (
                        <span className="line-through">{formatPrice(product.compareAtPrice, company.currency)}</span>
                      ) : null}
                      {product.soldOut ? <span className="text-rose-600">Sold out</span> : null}
                    </div>
                  </div>
                  <div className="flex shrink-0 items-center gap-1">
                    <Button
                      type="button"
                      size="sm"
                      disabled={busy || !!product.soldOut}
                      onClick={() => addToCart(product.id)}
                    >
                      Add
                    </Button>
                    <button
                      type="button"
                      disabled={busy}
                      onClick={() => void removeFromWishlist(product.id)}
                      className="rounded-md p-2 text-slate-400 hover:text-rose-500 disabled:opacity-50"
                      aria-label="Remove from wishlist"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </main>
    </div>
  )
}
