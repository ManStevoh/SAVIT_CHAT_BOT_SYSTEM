'use client'

import { FormEvent, useMemo, useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import { ArrowLeft, Heart, MessageCircle, Minus, Plus, ShoppingBag } from 'lucide-react'
import { Button } from '@/components/ui/button'

type Variant = { id: string; label: string; price: number; stock: number | null; soldOut?: boolean }
type Review = { id: string; authorName: string; rating: number; body?: string | null }
type StoreProduct = {
  id: string
  slug?: string | null
  name: string
  description?: string | null
  price: number
  compareAtPrice?: number | null
  onSale?: boolean
  soldOut?: boolean
  lowStock?: boolean
  images: string[]
  image?: string | null
  variants: Variant[]
  stock: number | null
  trackInventory: boolean
  isSubscription?: boolean
  subscriptionIntervalLabel?: string | null
  averageRating?: number | null
  reviewCount?: number
  reviews?: Review[]
  bundleItems?: { productId: string; name: string; quantity: number }[]
}

type Props = {
  slug: string
  company: { name: string; currency: string; whatsappUrl?: string | null }
  product: StoreProduct
  related?: StoreProduct[]
  cartCount: number
  wishlist?: string[]
  shareUrl?: string
  seo?: { title?: string; description?: string; image?: string | null }
}

function formatPrice(amount: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount)
  } catch {
    return `${currency} ${amount.toFixed(2)}`
  }
}

export default function StoreProductPage({
  slug,
  company,
  product,
  related = [],
  cartCount,
  wishlist = [],
  shareUrl,
  seo,
}: Props) {
  const [variantId, setVariantId] = useState(product.variants[0]?.id || '')
  const [quantity, setQuantity] = useState(1)
  const [activeImage, setActiveImage] = useState(0)
  const [submitting, setSubmitting] = useState(false)
  const [wishlistIds, setWishlistIds] = useState(wishlist)
  const [copied, setCopied] = useState(false)

  const selectedVariant = useMemo(
    () => product.variants.find((v) => v.id === variantId) || null,
    [product.variants, variantId]
  )
  const price = selectedVariant?.price ?? product.price
  const images = product.images.length > 0 ? product.images : product.image ? [product.image] : []
  const soldOut = selectedVariant?.soldOut || product.soldOut
  const maxQty =
    product.trackInventory && (selectedVariant?.stock ?? product.stock) != null
      ? Math.max(1, (selectedVariant?.stock ?? product.stock) as number)
      : 99
  const wished = wishlistIds.includes(product.id)
  const waUrl =
    company.whatsappUrl?.replace(/\?text=.*/, '') +
    `?text=${encodeURIComponent(`Hi, I'm interested in ${product.name}`)}`

  const addToCart = (e: FormEvent) => {
    e.preventDefault()
    if (soldOut) return
    setSubmitting(true)
    router.post(
      `/s/${slug}/cart`,
      {
        productId: product.id,
        productVariantId: selectedVariant?.id || null,
        quantity,
      },
      { onFinish: () => setSubmitting(false) }
    )
  }

  const toggleWishlist = async () => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    const res = await fetch(`/s/${slug}/wishlist/toggle`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ productId: Number(product.id) }),
    })
    if (res.ok) {
      const data = await res.json()
      setWishlistIds(data.wishlist || [])
    }
  }

  const copyShare = async () => {
    const url = shareUrl || window.location.href
    try {
      await navigator.clipboard.writeText(url)
      setCopied(true)
      setTimeout(() => setCopied(false), 1500)
    } catch {
      // ignore
    }
  }

  return (
    <div className="min-h-screen bg-white text-slate-900">
      <Head>
        <title>{seo?.title || product.name}</title>
        {seo?.description ? <meta head-key="description" name="description" content={seo.description} /> : null}
        {seo?.image ? <meta head-key="og:image" property="og:image" content={seo.image} /> : null}
      </Head>

      <header className="border-b border-slate-200">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
          <Link href={`/s/${slug}`} className="inline-flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900">
            <ArrowLeft className="h-4 w-4" /> {company.name}
          </Link>
          <Link href={`/s/${slug}/cart`}>
            <Button variant="outline" className="gap-2">
              <ShoppingBag className="h-4 w-4" />
              Cart{cartCount > 0 ? ` (${cartCount})` : ''}
            </Button>
          </Link>
        </div>
      </header>

      <main className="mx-auto grid max-w-5xl gap-8 px-4 py-8 sm:grid-cols-2">
        <div className="space-y-3">
          <div className="relative aspect-square overflow-hidden rounded-2xl bg-slate-100">
            {product.onSale && (
              <span className="absolute left-3 top-3 z-10 rounded bg-rose-600 px-2 py-0.5 text-[10px] font-semibold uppercase text-white">
                Sale
              </span>
            )}
            {images[activeImage] ? (
              <img src={images[activeImage]} alt={product.name} className="h-full w-full object-cover" />
            ) : (
              <div className="flex h-full w-full items-center justify-center text-slate-300">
                <ShoppingBag className="h-16 w-16" />
              </div>
            )}
          </div>
          {images.length > 1 && (
            <div className="flex gap-2 overflow-x-auto">
              {images.map((img, idx) => (
                <button
                  key={img}
                  type="button"
                  onClick={() => setActiveImage(idx)}
                  className={`h-16 w-16 shrink-0 overflow-hidden rounded-lg border ${
                    idx === activeImage ? 'border-slate-900' : 'border-slate-200'
                  }`}
                >
                  <img src={img} alt={`${product.name} ${idx + 1}`} loading="lazy" className="h-full w-full object-cover" />
                </button>
              ))}
            </div>
          )}
        </div>

        <form onSubmit={addToCart} className="space-y-5">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight">{product.name}</h1>
            <div className="mt-1 flex flex-wrap items-baseline gap-2">
              <p className="text-xl text-slate-900">{formatPrice(price, company.currency)}</p>
              {product.onSale && product.compareAtPrice != null && (
                <p className="text-sm text-slate-400 line-through">{formatPrice(product.compareAtPrice, company.currency)}</p>
              )}
            </div>
            {product.isSubscription && product.subscriptionIntervalLabel ? (
              <p className="mt-1 text-xs font-medium uppercase tracking-wide text-emerald-700">
                Subscription · {product.subscriptionIntervalLabel}
              </p>
            ) : null}
            {product.averageRating != null ? (
              <p className="mt-1 text-sm text-amber-600">
                ★ {product.averageRating} ({product.reviewCount || 0} reviews)
              </p>
            ) : null}
          </div>

          {product.description && <p className="text-sm leading-relaxed text-slate-600">{product.description}</p>}

          {product.bundleItems && product.bundleItems.length > 0 && (
            <div className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
              <p className="mb-1 font-medium">Bundle includes</p>
              <ul className="list-disc space-y-1 pl-4 text-slate-600">
                {product.bundleItems.map((item) => (
                  <li key={item.productId}>
                    {item.quantity} × {item.name}
                  </li>
                ))}
              </ul>
            </div>
          )}

          {product.variants.length > 0 && (
            <div className="space-y-2">
              <p className="text-sm font-medium">Options</p>
              <div className="flex flex-wrap gap-2">
                {product.variants.map((v) => (
                  <button
                    key={v.id}
                    type="button"
                    onClick={() => setVariantId(v.id)}
                    className={`rounded-full border px-3 py-1.5 text-sm ${
                      variantId === v.id
                        ? 'border-slate-900 bg-slate-900 text-white'
                        : 'border-slate-200 bg-white hover:border-slate-400'
                    }`}
                  >
                    {v.label}
                  </button>
                ))}
              </div>
            </div>
          )}

          <div className="flex items-center gap-3">
            <p className="text-sm font-medium">Quantity</p>
            <div className="flex items-center rounded-full border border-slate-200">
              <button
                type="button"
                onClick={() => setQuantity((q) => Math.max(1, q - 1))}
                className="p-2 text-slate-600 hover:text-slate-900"
              >
                <Minus className="h-4 w-4" />
              </button>
              <span className="w-8 text-center text-sm">{quantity}</span>
              <button
                type="button"
                onClick={() => setQuantity((q) => Math.min(maxQty, q + 1))}
                className="p-2 text-slate-600 hover:text-slate-900"
              >
                <Plus className="h-4 w-4" />
              </button>
            </div>
            {product.lowStock && !soldOut ? <p className="text-xs text-amber-600">Low stock</p> : null}
            {soldOut ? <p className="text-xs font-medium text-rose-600">Sold out</p> : null}
          </div>

          <div className="flex flex-wrap gap-2">
            <Button type="submit" disabled={submitting || !!soldOut} className="w-full sm:w-auto">
              {soldOut ? 'Sold out' : submitting ? 'Adding…' : 'Add to cart'}
            </Button>
            <Button type="button" variant="outline" onClick={() => void toggleWishlist()} className="gap-2">
              <Heart className={`h-4 w-4 ${wished ? 'fill-rose-500 text-rose-500' : ''}`} />
              {wished ? 'Saved' : 'Wishlist'}
            </Button>
            <Button type="button" variant="outline" onClick={() => void copyShare()}>
              {copied ? 'Copied!' : 'Share'}
            </Button>
          </div>

          {company.whatsappUrl ? (
            <a href={waUrl} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 text-sm text-emerald-700 hover:underline">
              <MessageCircle className="h-4 w-4" /> Chat about this product
            </a>
          ) : null}

          {product.reviews && product.reviews.length > 0 && (
            <div className="space-y-3 border-t border-slate-100 pt-4">
              <h2 className="text-sm font-semibold">Reviews</h2>
              {product.reviews.map((review) => (
                <div key={review.id} className="rounded-xl border border-slate-100 p-3 text-sm">
                  <p className="font-medium">
                    {review.authorName} · {'★'.repeat(review.rating)}
                  </p>
                  {review.body ? <p className="mt-1 text-slate-600">{review.body}</p> : null}
                </div>
              ))}
            </div>
          )}
        </form>
      </main>

      {related.length > 0 && (
        <section className="mx-auto max-w-5xl px-4 pb-12">
          <h2 className="mb-4 text-lg font-semibold">You may also like</h2>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            {related.map((item) => (
              <Link key={item.id} href={`/s/${slug}/p/${item.slug || item.id}`} className="overflow-hidden rounded-2xl border border-slate-200">
                <div className="aspect-square bg-slate-100">
                  {item.image ? <img src={item.image} alt={item.name} className="h-full w-full object-cover" /> : null}
                </div>
                <div className="p-3">
                  <p className="line-clamp-1 text-sm font-medium">{item.name}</p>
                  <p className="text-sm text-slate-500">{formatPrice(item.price, company.currency)}</p>
                </div>
              </Link>
            ))}
          </div>
        </section>
      )}
    </div>
  )
}
