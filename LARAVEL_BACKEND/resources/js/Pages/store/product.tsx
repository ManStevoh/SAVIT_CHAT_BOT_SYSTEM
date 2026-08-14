'use client'

import { FormEvent, useMemo, useState } from 'react'
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
  Share2,
  ShieldCheck,
  ShoppingBag,
  Star,
  User,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { SeoHead, type SeoPayload } from '@/components/seo/SeoHead'
import { StorefrontAuthModal } from '@/components/store/StorefrontAuthModal'

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
  company: {
    name: string
    logo?: string | null
    currency: string
    whatsappUrl?: string | null
    authCustomer?: { id: number; name: string; email: string } | null
  }
  product: StoreProduct
  related?: StoreProduct[]
  cartCount: number
  wishlist?: string[]
  shareUrl?: string
  seo?: SeoPayload | null
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
  const [authModalOpen, setAuthModalOpen] = useState(false)

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
  const waUrl = company.whatsappUrl || undefined

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
    <div className="min-h-screen bg-slate-50/80 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
      <SeoHead seo={seo} fallbackTitle={product.name} />

      {/* Top Navbar */}
      <header className="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-3.5">
          <Link
            href={`/s/${slug}`}
            className="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
          >
            <ArrowLeft className="h-4 w-4" /> {company.name}
          </Link>

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

            <Link href={`/s/${slug}/wishlist`}>
              <Button variant="outline" size="sm" className="gap-1.5 rounded-xl border-slate-200 text-xs font-semibold dark:border-slate-800">
                <Heart className={`h-3.5 w-3.5 ${wishlistIds.length > 0 ? 'fill-rose-500 text-rose-500' : ''}`} />
                <span className="hidden sm:inline">Wishlist</span>
                {wishlistIds.length > 0 ? ` (${wishlistIds.length})` : ''}
              </Button>
            </Link>

            <Link href={`/s/${slug}/cart`}>
              <Button size="sm" className="gap-2 rounded-xl bg-slate-900 text-xs font-semibold text-white shadow-xs hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900">
                <ShoppingBag className="h-3.5 w-3.5" />
                <span>Cart</span>
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

      {/* Main Product Container */}
      <main className="mx-auto grid max-w-5xl gap-8 px-4 py-8 md:grid-cols-12">
        
        {/* Left Column: Image Gallery */}
        <div className="space-y-4 md:col-span-6">
          <div className="relative aspect-square overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
            {product.onSale && (
              <span className="absolute left-3.5 top-3.5 z-10 rounded-full bg-rose-600 px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider text-white shadow-sm">
                SALE
              </span>
            )}
            {soldOut && (
              <span className="absolute right-3.5 top-3.5 z-10 rounded-full bg-slate-900/90 px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider text-white shadow-sm">
                SOLD OUT
              </span>
            )}
            {images[activeImage] ? (
              <img src={images[activeImage]} alt={product.name} className="h-full w-full object-cover transition-transform duration-500 hover:scale-105" />
            ) : (
              <div className="flex h-full w-full items-center justify-center text-slate-300 dark:text-slate-600">
                <ShoppingBag className="h-16 w-16" />
              </div>
            )}
          </div>

          {images.length > 1 && (
            <div className="flex gap-2.5 overflow-x-auto pb-1 scrollbar-none">
              {images.map((img, idx) => (
                <button
                  key={img}
                  type="button"
                  onClick={() => setActiveImage(idx)}
                  className={`h-16 w-16 shrink-0 overflow-hidden rounded-2xl border-2 transition-all ${
                    idx === activeImage
                      ? 'border-slate-900 ring-2 ring-slate-900/20 dark:border-white'
                      : 'border-slate-200 hover:border-slate-400 dark:border-slate-800'
                  }`}
                >
                  <img src={img} alt={`${product.name} ${idx + 1}`} loading="lazy" className="h-full w-full object-cover" />
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Right Column: Product Details & Purchase Form */}
        <form onSubmit={addToCart} className="space-y-6 md:col-span-6">
          <div className="space-y-2">
            <h1 className="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-3xl">{product.name}</h1>
            
            <div className="flex flex-wrap items-baseline gap-2.5">
              <span className="text-2xl font-extrabold text-slate-900 dark:text-white">
                {formatPrice(price, company.currency)}
              </span>
              {product.onSale && product.compareAtPrice != null && (
                <span className="text-sm font-semibold text-slate-400 line-through">
                  {formatPrice(product.compareAtPrice, company.currency)}
                </span>
              )}
            </div>

            {product.isSubscription && product.subscriptionIntervalLabel && (
              <div className="inline-block rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800 border border-emerald-200/80 dark:bg-emerald-950/40 dark:border-emerald-900/50 dark:text-emerald-300">
                Subscription · {product.subscriptionIntervalLabel}
              </div>
            )}

            {product.averageRating != null && (
              <div className="flex items-center gap-1.5 text-xs font-bold text-amber-600">
                <Star className="h-4 w-4 fill-amber-400 text-amber-400" />
                <span>{product.averageRating}</span>
                <span className="text-slate-400">({product.reviewCount || 0} reviews)</span>
              </div>
            )}
          </div>

          {product.description && (
            <p className="text-xs leading-relaxed text-slate-600 dark:text-slate-400">{product.description}</p>
          )}

          {/* Bundle Content Card */}
          {product.bundleItems && product.bundleItems.length > 0 && (
            <div className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs dark:border-slate-800 dark:bg-slate-900">
              <p className="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Bundle Includes</p>
              <ul className="space-y-1.5 text-xs font-medium text-slate-700 dark:text-slate-300">
                {product.bundleItems.map((item) => (
                  <li key={item.productId} className="flex items-center gap-2">
                    <Check className="h-3.5 w-3.5 text-emerald-600" />
                    <span>{item.quantity} × {item.name}</span>
                  </li>
                ))}
              </ul>
            </div>
          )}

          {/* Options / Variants */}
          {product.variants.length > 0 && (
            <div className="space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
              <label className="text-xs font-bold uppercase tracking-wider text-slate-400">Select Option</label>
              <div className="flex flex-wrap gap-2">
                {product.variants.map((v) => (
                  <button
                    key={v.id}
                    type="button"
                    onClick={() => setVariantId(v.id)}
                    className={`rounded-full border px-4 py-2 text-xs font-bold transition-all ${
                      variantId === v.id
                        ? 'border-slate-900 bg-slate-900 text-white shadow-md dark:bg-white dark:text-slate-900'
                        : 'border-slate-200/80 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                    }`}
                  >
                    {v.label}
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Quantity Stepper */}
          <div className="space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
            <div className="flex items-center gap-4">
              <label className="text-xs font-bold uppercase tracking-wider text-slate-400">Quantity</label>
              <div className="flex items-center rounded-xl border border-slate-200 bg-slate-50 p-0.5 dark:border-slate-700 dark:bg-slate-800">
                <button
                  type="button"
                  onClick={() => setQuantity((q) => Math.max(1, q - 1))}
                  className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-600 transition-colors hover:bg-white hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                >
                  <Minus className="h-3.5 w-3.5" />
                </button>
                <span className="w-8 text-center text-xs font-bold text-slate-900 dark:text-white">{quantity}</span>
                <button
                  type="button"
                  onClick={() => setQuantity((q) => Math.min(maxQty, q + 1))}
                  className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-600 transition-colors hover:bg-white hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                >
                  <Plus className="h-3.5 w-3.5" />
                </button>
              </div>

              {product.lowStock && !soldOut && <span className="text-xs font-semibold text-amber-600">Only a few left!</span>}
              {soldOut && <span className="text-xs font-bold text-rose-600">Currently Sold Out</span>}
            </div>
          </div>

          {/* Action Buttons Row */}
          <div className="space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
            <div className="flex flex-wrap gap-2.5">
              <Button
                type="submit"
                disabled={submitting || !!soldOut}
                size="lg"
                className="flex-1 gap-2 rounded-2xl bg-slate-900 py-6 text-sm font-semibold text-white shadow-md transition-all hover:bg-slate-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"
              >
                <ShoppingBag className="h-4 w-4" />
                {soldOut ? 'Sold Out' : submitting ? 'Adding to Cart…' : 'Add to Cart'}
              </Button>

              <Button
                type="button"
                variant="outline"
                onClick={() => void toggleWishlist()}
                size="lg"
                className="rounded-2xl border-slate-200 p-3.5 dark:border-slate-800"
                title="Wishlist"
              >
                <Heart className={`h-4 w-4 ${wished ? 'fill-rose-500 text-rose-500' : ''}`} />
              </Button>

              <Button
                type="button"
                variant="outline"
                onClick={() => void copyShare()}
                size="lg"
                className="rounded-2xl border-slate-200 gap-1.5 px-4 text-xs font-semibold dark:border-slate-800"
              >
                <Share2 className="h-4 w-4" />
                <span>{copied ? 'Copied!' : 'Share'}</span>
              </Button>
            </div>

            {company.whatsappUrl && (
              <a
                href={waUrl}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 transition-colors hover:underline dark:text-emerald-400"
              >
                <MessageCircle className="h-3.5 w-3.5" /> Have questions? Chat with us on WhatsApp
              </a>
            )}
          </div>

          {/* Reviews Section */}
          {product.reviews && product.reviews.length > 0 && (
            <div className="space-y-3 border-t border-slate-100 pt-5 dark:border-slate-800">
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">Customer Reviews</h3>
              <div className="space-y-2.5">
                {product.reviews.map((review) => (
                  <div key={review.id} className="rounded-2xl border border-slate-200/80 bg-white p-3.5 text-xs shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between font-bold text-slate-900 dark:text-white">
                      <span>{review.authorName}</span>
                      <span className="text-amber-500">{'★'.repeat(review.rating)}</span>
                    </div>
                    {review.body && <p className="mt-1 leading-relaxed text-slate-600 dark:text-slate-400">{review.body}</p>}
                  </div>
                ))}
              </div>
            </div>
          )}
        </form>
      </main>

      {/* Related Products Grid */}
      {related.length > 0 && (
        <section className="mx-auto max-w-5xl space-y-4 px-4 pb-16 pt-8 border-t border-slate-200/80 dark:border-slate-800">
          <h2 className="text-sm font-bold tracking-tight text-slate-900 dark:text-white">You May Also Like</h2>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            {related.map((item) => (
              <Link
                key={item.id}
                href={`/s/${slug}/p/${item.slug || item.id}`}
                className="group overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xs transition-all duration-300 hover:-translate-y-1 hover:shadow-xl dark:border-slate-800 dark:bg-slate-900"
              >
                <div className="aspect-square w-full overflow-hidden bg-slate-100 dark:bg-slate-800">
                  {item.image ? (
                    <img src={item.image} alt={item.name} className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" />
                  ) : (
                    <div className="flex h-full w-full items-center justify-center text-slate-300 dark:text-slate-600">
                      <ShoppingBag className="h-8 w-8" />
                    </div>
                  )}
                </div>
                <div className="p-3.5">
                  <p className="line-clamp-1 text-xs font-bold text-slate-900 group-hover:text-emerald-600 transition-colors dark:text-white">{item.name}</p>
                  <p className="mt-1 text-xs font-extrabold text-slate-900 dark:text-white">{formatPrice(item.price, company.currency)}</p>
                </div>
              </Link>
            ))}
          </div>
        </section>
      )}

      <StorefrontAuthModal
        open={authModalOpen}
        onOpenChange={setAuthModalOpen}
        slug={slug}
        companyName={company.name}
      />
    </div>
  )
}
