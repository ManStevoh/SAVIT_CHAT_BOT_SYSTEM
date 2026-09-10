'use client'

import { FormEvent, useEffect, useMemo, useState } from 'react'
import { Link, router } from '@inertiajs/react'
import {
  ArrowRight,
  CalendarCheck,
  Check,
  Copy,
  Eye,
  Heart,
  LogOut,
  MessageCircle,
  Minus,
  Plus,
  RotateCcw,
  Search,
  Share2,
  ShoppingBag,
  SlidersHorizontal,
  Sparkles,
  Star,
  User,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { SeoHead, type SeoPayload } from '@/components/seo/SeoHead'
import { StorefrontAuthModal } from '@/components/store/StorefrontAuthModal'
import { StorefrontFooter } from '@/components/store/StorefrontFooter'
import { getRecentlyViewed } from '@/lib/storefront-recent'
import { resolveStorefrontStyle, type BrandTheme } from '@/lib/theme-utils'

type Variant = { id: string; label: string; price: number; stock: number | null; soldOut?: boolean }

type StoreProduct = {
  id: string
  slug?: string | null
  name: string
  description?: string | null
  price: number
  compareAtPrice?: number | null
  onSale?: boolean
  discountPercent?: number | null
  soldOut?: boolean
  lowStock?: boolean
  category?: string | null
  image?: string | null
  variants?: Variant[]
  productType?: string | null
  businessUnit?: { id: string; name: string; slug: string; type?: string | null } | null
  fulfillmentType?: string | null
  bookable?: boolean
  bookingDurationMinutes?: number | null
  serviceBookingUrl?: string | null
  averageRating?: number | null
  reviewCount?: number
}

type Filters = {
  q?: string | null
  sort?: string | null
  category?: string | null
  business_unit?: string | null
  in_stock?: string | boolean | number | null
  min_price?: string | number | null
  max_price?: string | number | null
  type?: string | null
}

type Section = {
  type: string
  headline?: string
  subhead?: string
  image?: string
  cta_label?: string
  cta_href?: string
  text?: string
  html?: string
  title?: string
  body?: string
  category?: string
  products?: StoreProduct[]
  items?: { quote: string; author: string; rating?: number | null }[]
}

const bnbBannerImages = [
  '/images/bnb/bnb-01.jpeg',
  '/images/bnb/bnb-02.jpeg',
  '/images/bnb/bnb-03.jpeg',
  '/images/bnb/bnb-04.jpeg',
  '/images/bnb/bnb-05.jpeg',
  '/images/bnb/bnb-06.jpeg',
  '/images/bnb/bnb-07.jpeg',
  '/images/bnb/bnb-08.jpeg',
]

type AltCurrency = { code: string; label: string; rate: number }

type Props = {
  slug: string
  company: {
    name: string
    businessUnits?: { id: string; name: string; slug: string; type?: string | null; description?: string | null; logo?: string | null; heroImage?: string | null }[]
    logo?: string | null
    currency: string
    displayCurrency?: string
    displayRate?: number
    altCurrencies?: AltCurrency[]
    supportedLocales?: Record<string, string>
    whatsappUrl?: string | null
    instagramUrl?: string | null
    facebookUrl?: string | null
    tiktokUrl?: string | null
    aboutUrl?: string
    authCustomer?: { id: number; name: string; email: string } | null
    theme?: BrandTheme
    termsUrl?: string
  }
  products: StoreProduct[]
  filters?: Filters
  categories?: string[]
  sections?: Section[]
  cartCount: number
  wishlist?: string[]
  locale?: string
  chrome?: { cart?: string; wishlist?: string; search?: string; trackOrder?: string; checkout?: string }
  seo?: SeoPayload | null
}

function formatPrice(amount: number, currency: string, rate: number = 1): string {
  const converted = amount * (rate || 1)
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(converted)
  } catch {
    return `${currency} ${converted.toFixed(2)}`
  }
}

function QuickVariantModal({
  open,
  onOpenChange,
  product,
  slug,
  currency,
  rate,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  product: StoreProduct | null
  slug: string
  currency: string
  rate?: number
}) {
  const variants = product?.variants || []
  const [selectedVariantId, setSelectedVariantId] = useState(variants[0]?.id || '')
  const [quantity, setQuantity] = useState(1)
  const [adding, setAdding] = useState(false)
  const [added, setAdded] = useState(false)

  if (!product) return null

  const selectedVariant = variants.find((v) => v.id === selectedVariantId) || variants[0]
  const currentPrice = selectedVariant?.price ?? product.price
  const isSoldOut = selectedVariant?.soldOut || product.soldOut

  const handleAddToCart = () => {
    if (isSoldOut) return
    setAdding(true)
    router.post(
      `/s/${slug}/cart`,
      {
        productId: product.id,
        productVariantId: selectedVariant?.id || null,
        quantity,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setAdded(true)
          setTimeout(() => {
            setAdded(false)
            onOpenChange(false)
          }, 1200)
        },
        onFinish: () => setAdding(false),
      }
    )
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md rounded-3xl p-6">
        <DialogHeader>
          <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Quick Add to Cart</span>
          <DialogTitle className="text-lg font-bold tracking-tight">{product.name}</DialogTitle>
          <DialogDescription className="text-xs text-slate-500">
            Choose options and quantity below.
          </DialogDescription>
        </DialogHeader>

        <div className="flex items-center gap-4 py-2 border-y border-slate-100 dark:border-slate-800">
          <div className="h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-slate-100 dark:bg-slate-800">
            {product.image ? (
              <img src={product.image} alt={product.name} className="h-full w-full object-cover" />
            ) : (
              <div className="flex h-full w-full items-center justify-center text-slate-300">
                <ShoppingBag className="h-6 w-6" />
              </div>
            )}
          </div>
          <div>
            <p className="text-base font-extrabold text-slate-900 dark:text-white">
              {formatPrice(currentPrice, currency, rate)}
            </p>
            {product.onSale && product.compareAtPrice && (
              <p className="text-xs text-slate-400 line-through">
                {formatPrice(product.compareAtPrice, currency, rate)}
              </p>
            )}
          </div>
        </div>

        {variants.length > 0 && (
          <div className="space-y-2">
            <label className="text-xs font-bold uppercase tracking-wider text-slate-400">Option</label>
            <div className="flex flex-wrap gap-2">
              {variants.map((v) => (
                <button
                  key={v.id}
                  type="button"
                  onClick={() => setSelectedVariantId(v.id)}
                  className={`rounded-full border px-3.5 py-1.5 text-xs font-semibold transition-all ${
                    (selectedVariantId || variants[0]?.id) === v.id
                      ? 'border-slate-900 bg-slate-900 text-white shadow-sm dark:bg-white dark:text-slate-900'
                      : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                  }`}
                >
                  {v.label}
                </button>
              ))}
            </div>
          </div>
        )}

        <div className="flex items-center justify-between pt-2">
          <label className="text-xs font-bold uppercase tracking-wider text-slate-400">Quantity</label>
          <div className="flex items-center rounded-xl border border-slate-200 bg-slate-50 p-0.5 dark:border-slate-700 dark:bg-slate-800">
            <button
              type="button"
              onClick={() => setQuantity((q) => Math.max(1, q - 1))}
              className="flex h-7 w-7 items-center justify-center rounded-lg text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-700"
            >
              <Minus className="h-3 w-3" />
            </button>
            <span className="w-8 text-center text-xs font-bold text-slate-900 dark:text-white">{quantity}</span>
            <button
              type="button"
              onClick={() => setQuantity((q) => q + 1)}
              className="flex h-7 w-7 items-center justify-center rounded-lg text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-700"
            >
              <Plus className="h-3 w-3" />
            </button>
          </div>
        </div>

        <Button
          type="button"
          onClick={handleAddToCart}
          disabled={adding || isSoldOut}
          className="w-full gap-2 rounded-2xl bg-slate-900 py-5 text-xs font-bold text-white shadow-md hover:bg-slate-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"
        >
          {added ? (
            <>
              <Check className="h-4 w-4 text-emerald-400" /> Added to Cart!
            </>
          ) : adding ? (
            'Adding…'
          ) : isSoldOut ? (
            'Sold Out'
          ) : (
            <>
              <ShoppingBag className="h-4 w-4" /> Add to Cart · {formatPrice(currentPrice * quantity, currency, rate)}
            </>
          )}
        </Button>
      </DialogContent>
    </Dialog>
  )
}

function ProductCard({
  slug,
  product,
  currency,
  rate = 1,
  isWished,
  onWishlistToggle,
  onQuickAdd,
}: {
  slug: string
  product: StoreProduct
  currency: string
  rate?: number
  isWished: boolean
  onWishlistToggle: (productId: string) => void
  onQuickAdd: (product: StoreProduct) => void
}) {
  const href = `/s/${slug}/p/${product.slug || product.id}`

  return (
    <div
      className="group relative flex flex-col overflow-hidden border border-slate-200/80 bg-white shadow-xs transition-all duration-300 hover:-translate-y-1 hover:shadow-xl dark:border-slate-800 dark:bg-slate-900"
      style={{ borderRadius: 'var(--sf-radius, 1.5rem)' }}
    >
      {/* Badges */}
      <div className="absolute left-3 top-3 z-10 flex flex-col gap-1">
        {product.onSale && (
          <span className="rounded-full bg-rose-600 px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-white shadow-xs">
            {product.discountPercent ? `-${product.discountPercent}%` : 'SALE'}
          </span>
        )}
        {product.lowStock && !product.soldOut && (
          <span className="rounded-full bg-amber-500 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white shadow-xs">
            LOW STOCK
          </span>
        )}
      </div>

      {/* Wishlist Button */}
      <button
        type="button"
        onClick={(e) => {
          e.preventDefault()
          e.stopPropagation()
          onWishlistToggle(product.id)
        }}
        className="absolute right-3 top-3 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white/90 shadow-sm backdrop-blur-xs transition-transform hover:scale-110 active:scale-95 dark:bg-slate-900/90"
        title="Save to Wishlist"
      >
        <Heart className={`h-4 w-4 ${isWished ? 'fill-rose-500 text-rose-500' : 'text-slate-400 hover:text-slate-600'}`} />
      </button>

      {product.soldOut && (
        <span className="absolute left-3 bottom-3 z-10 rounded-full bg-slate-900/90 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white shadow-xs backdrop-blur-xs">
          SOLD OUT
        </span>
      )}

      {/* Product Image Container */}
      <Link href={href} className="relative aspect-square w-full overflow-hidden bg-slate-100 dark:bg-slate-800">
        {product.image ? (
          <img
            src={product.image}
            alt={product.name}
            loading="lazy"
            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center text-slate-300 dark:text-slate-600">
            <ShoppingBag className="h-10 w-10" />
          </div>
        )}
      </Link>

      {/* Card Content */}
      <div className="flex flex-1 flex-col justify-between p-3 sm:p-4">
        <div>
          <div className="flex items-center justify-between gap-1">
            <div className="flex min-w-0 items-center gap-1.5">
              {product.businessUnit && (
                <span className="truncate text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">{product.businessUnit.name}</span>
              )}
              {product.category && (
                <span className="truncate text-[10px] font-bold uppercase tracking-wider text-slate-400">{product.category}</span>
              )}
            </div>
            {product.averageRating != null && product.averageRating > 0 && (
              <span className="flex items-center gap-0.5 text-[10px] font-bold text-amber-500">
                <Star className="h-3 w-3 fill-amber-400 text-amber-400" />
                {product.averageRating}
              </span>
            )}
          </div>
          <Link href={href}>
            <h3 className="line-clamp-2 mt-1 text-xs font-bold tracking-tight text-slate-900 transition-colors hover:text-emerald-600 dark:text-white">
              {product.name}
            </h3>
          </Link>
        </div>

        <div className="mt-3 flex flex-col gap-2 border-t border-slate-100 pt-3 dark:border-slate-800 sm:flex-row sm:items-end sm:justify-between sm:gap-2">
          <div className="min-w-0">
            <span className="block text-sm font-extrabold leading-tight tracking-tight text-slate-900 tabular-nums dark:text-white">
              {formatPrice(product.price, currency, rate)}
            </span>
            {product.onSale && product.compareAtPrice != null && (
              <span className="text-[11px] font-medium text-slate-400 line-through">
                {formatPrice(product.compareAtPrice, currency, rate)}
              </span>
            )}
          </div>

          {product.productType === 'service' && product.bookable && product.serviceBookingUrl ? (
            <Button
              asChild
              size="sm"
              className="h-9 w-full shrink-0 px-3 text-[11px] font-bold text-white shadow-xs sm:h-8 sm:w-auto"
              style={{ background: 'var(--sf-primary, #0f172a)', borderRadius: 'var(--sf-radius, 0.75rem)' }}
            >
              <a href={product.serviceBookingUrl}>Book now</a>
            </Button>
          ) : (
          <Button
            type="button"
            size="sm"
            disabled={!!product.soldOut}
            onClick={(e) => {
              e.preventDefault()
              onQuickAdd(product)
            }}
            className="h-9 w-full shrink-0 px-3 text-[11px] font-bold text-white shadow-xs transition-all hover:scale-105 hover:opacity-90 active:scale-[0.98] sm:h-8 sm:w-auto"
            style={{
              background: 'var(--sf-primary, #0f172a)',
              borderRadius: 'var(--sf-radius, 0.75rem)',
            }}
          >
            <Plus className="mr-1 h-3.5 w-3.5 shrink-0" />
            {product.soldOut ? 'Sold out' : 'Add to cart'}
          </Button>
          )}
        </div>
      </div>
    </div>
  )
}

export default function StorePage({
  slug,
  company,
  products,
  filters = {},
  sections,
  cartCount,
  wishlist = [],
  locale = 'en',
  chrome,
  seo,
}: Props) {
  const [q, setQ] = useState(String(filters.q ?? ''))
  const [sort, setSort] = useState(String(filters.sort ?? 'name_asc'))
  const [inStockOnly, setInStockOnly] = useState(Boolean(filters.in_stock))
  const [minPrice, setMinPrice] = useState(String(filters.min_price ?? ''))
  const [maxPrice, setMaxPrice] = useState(String(filters.max_price ?? ''))
  const [showFilterDrawer, setShowFilterDrawer] = useState(false)
  const [authModalOpen, setAuthModalOpen] = useState(false)
  const [wishlistIds, setWishlistIds] = useState<string[]>(wishlist)
  const [quickAddProduct, setQuickAddProduct] = useState<StoreProduct | null>(null)
  const [quickAddModalOpen, setQuickAddModalOpen] = useState(false)

  const theme = company.theme ?? {}
  const style = resolveStorefrontStyle(theme)
  const bnbProducts = products.filter((product) => product.category?.toLowerCase() === 'bnb stays')
  const visibleProducts = products.filter((product) => product.category?.toLowerCase() !== 'bnb stays')

  const allCategories = useMemo(
    () => Array.from(new Set(visibleProducts.map((p) => p.category).filter(Boolean))) as string[],
    [visibleProducts]
  )
  const businessUnits = company.businessUnits || []

  const resolvedSections = useMemo(() => {
    const list = sections && sections.length > 0 ? [...sections] : [{ type: 'catalog' }]
    if (!list.some((s) => s.type === 'catalog')) {
      list.push({ type: 'catalog' })
    }
    let foundCatalog = false
    return list.filter((s) => {
      if (s.type === 'catalog') {
        if (foundCatalog) return false
        foundCatalog = true
      }
      return true
    })
  }, [sections])
  const displayCurrency = company.displayCurrency || company.currency
  const displayRate = company.displayRate || 1.0
  const altCurrencies = company.altCurrencies || []
  const supportedLocales = company.supportedLocales || { en: 'English', sw: 'Kiswahili' }

  const applyFilters = (customFilters?: Partial<Filters>) => {
    const nextFilters = {
      q: q || undefined,
      sort: sort || undefined,
      category: filters.category || undefined,
      business: filters.business_unit || undefined,
      in_stock: inStockOnly ? 1 : undefined,
      min_price: minPrice || undefined,
      max_price: maxPrice || undefined,
      ...customFilters,
    }
    router.get(`/s/${slug}`, nextFilters, { preserveState: true, replace: true })
  }

  const handleSearchSubmit = (e?: FormEvent) => {
    e?.preventDefault()
    applyFilters()
  }

  const setCategory = (category: string | null) => {
    applyFilters({ category: category || undefined })
  }

  const toggleInStock = () => {
    const next = !inStockOnly
    setInStockOnly(next)
    applyFilters({ in_stock: next ? 1 : undefined })
  }

  const resetAllFilters = () => {
    setQ('')
    setSort('name_asc')
    setInStockOnly(false)
    setMinPrice('')
    setMaxPrice('')
    router.get(`/s/${slug}`, {}, { preserveState: true, replace: true })
  }

  const switchCurrency = (curr: string) => {
    const url = new URL(window.location.href)
    url.searchParams.set('currency', curr)
    router.visit(url.pathname + url.search, { preserveScroll: true })
  }

  const switchLanguage = (lang: string) => {
    const url = new URL(window.location.href)
    url.searchParams.set('lang', lang)
    router.visit(url.pathname + url.search, { preserveScroll: true })
  }

  const toggleWishlist = async (productId: string) => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    try {
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
        const data = await res.json()
        setWishlistIds(data.wishlist || [])
      }
    } catch {
      // ignore
    }
  }

  const handleQuickAdd = (product: StoreProduct) => {
    if (product.variants && product.variants.length > 0) {
      setQuickAddProduct(product)
      setQuickAddModalOpen(true)
    } else {
      router.post(
        `/s/${slug}/cart`,
        {
          productId: product.id,
          quantity: 1,
        },
        { preserveScroll: true }
      )
    }
  }

  const hasActiveFilters = Boolean(filters.q || filters.category || filters.business_unit || filters.in_stock || filters.min_price || filters.max_price || (filters.sort && filters.sort !== 'name_asc'))
  const integratedCatalog = businessUnits.length > 0 && !filters.business_unit
  const primaryProducts = visibleProducts.filter((product) => !product.businessUnit || product.businessUnit.type !== 'hospitality')
  const secondaryProducts = visibleProducts.filter((product) => product.businessUnit?.type === 'hospitality')
  const hospitalityUnit = businessUnits.find((unit) => unit.type === 'hospitality')

  const renderProductGrid = (items: StoreProduct[]) => (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
      {items.map((product) => (
        <ProductCard
          key={product.id}
          slug={slug}
          product={product}
          currency={displayCurrency}
          rate={displayRate}
          isWished={wishlistIds.includes(product.id)}
          onWishlistToggle={toggleWishlist}
          onQuickAdd={handleQuickAdd}
        />
      ))}
    </div>
  )

  return (
    <div className="min-h-screen bg-slate-50/80 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100" style={style}>
      <SeoHead seo={seo} fallbackTitle={`${company.name} — Shop`} />

      {theme.announcement_bar ? (
        <div
          className="px-4 py-2 text-center text-xs font-semibold shadow-xs"
          style={{
            background: 'var(--sf-announcement-bg, var(--sf-accent, #2563eb))',
            color: 'var(--sf-announcement-text, #ffffff)',
          }}
        >
          {theme.announcement_bar}
        </div>
      ) : null}

      {/* Top Navbar */}
      <header className="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-2 px-3 py-2.5 sm:gap-3 sm:px-4 sm:py-3.5">
          <Link href={`/s/${slug}`} className="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
            {company.logo ? (
              <div className="flex h-8 sm:h-9 shrink-0 items-center justify-center overflow-hidden rounded-xl">
                <img
                  src={company.logo}
                  alt={company.name}
                  className="h-8 sm:h-9 w-auto max-w-[90px] sm:max-w-[160px] object-contain"
                />
              </div>
            ) : (
              <div
                className="flex h-8 sm:h-9 w-8 sm:w-9 shrink-0 items-center justify-center rounded-2xl text-xs sm:text-sm font-extrabold text-white shadow-xs"
                style={{ background: 'var(--sf-primary)' }}
              >
                {company.name.charAt(0).toUpperCase()}
              </div>
            )}
            <h1 className="min-w-0 truncate text-sm font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-base">
              {company.name}
            </h1>
          </Link>

          <div className="flex shrink-0 items-center gap-1.5 sm:gap-2">
            {/* Currency Switcher */}
            {altCurrencies.length > 0 && (
              <select
                value={displayCurrency}
                onChange={(e) => switchCurrency(e.target.value)}
                className="hidden h-8 rounded-xl border border-slate-200 bg-slate-50 px-2 text-xs font-bold text-slate-700 outline-hidden dark:border-slate-800 dark:bg-slate-800 dark:text-slate-300 md:inline-block"
                title="Select Currency"
              >
                <option value={company.currency}>{company.currency}</option>
                {altCurrencies.map((alt) => (
                  <option key={alt.code} value={alt.code}>
                    {alt.code}
                  </option>
                ))}
              </select>
            )}

            {/* Language Switcher */}
            {Object.keys(supportedLocales).length > 1 && (
              <select
                value={locale}
                onChange={(e) => switchLanguage(e.target.value)}
                className="hidden h-8 rounded-xl border border-slate-200 bg-slate-50 px-2 text-xs font-bold text-slate-700 outline-hidden dark:border-slate-800 dark:bg-slate-800 dark:text-slate-300 md:inline-block"
                title="Select Language"
              >
                {Object.entries(supportedLocales).map(([code, label]) => (
                  <option key={code} value={code}>
                    {label}
                  </option>
                ))}
              </select>
            )}

            <Link href={`/s/${slug}/track`} className="hidden text-xs font-semibold text-slate-500 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-white sm:inline">
              {chrome?.trackOrder || 'Track order'}
            </Link>

            {company.authCustomer ? (
              <div className="flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200 sm:gap-1.5 sm:px-3">
                <User className="h-3.5 w-3.5" />
                <span className="max-w-[70px] truncate sm:max-w-none">{company.authCustomer.name.split(' ')[0]}</span>
                <button
                  type="button"
                  onClick={() => router.post(`/s/${slug}/account/logout`)}
                  className="ml-0.5 text-slate-400 hover:text-slate-900 dark:hover:text-white sm:ml-1"
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
                className="gap-1 px-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 sm:gap-1.5 sm:px-3"
                title="Sign In"
              >
                <User className="h-3.5 w-3.5" />
                <span className="hidden sm:inline">Sign In</span>
              </Button>
            )}

            <Link href={`/s/${slug}/wishlist`}>
              <Button variant="outline" size="sm" className="gap-1 px-2.5 rounded-xl border-slate-200 text-xs font-semibold dark:border-slate-800 sm:gap-1.5 sm:px-3" title="Favorites">
                <Heart className={`h-3.5 w-3.5 ${wishlistIds.length > 0 ? 'fill-rose-500 text-rose-500' : ''}`} />
                <span>{chrome?.wishlist || 'Favorites'}</span>
                {wishlistIds.length > 0 ? ` (${wishlistIds.length})` : ''}
              </Button>
            </Link>

            <Link href={`/s/${slug}/cart`}>
              <Button size="sm" className="gap-1.5 px-2.5 rounded-xl bg-slate-900 text-xs font-semibold text-white shadow-xs hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 sm:gap-2 sm:px-3">
                <ShoppingBag className="h-3.5 w-3.5" />
                <span className="hidden sm:inline">{chrome?.cart || 'Cart'}</span>
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

      {/* Main Catalog Container */}
      <main className="mx-auto max-w-5xl space-y-6 px-4 py-8 pb-24">
        
        {/* Search & Filter Bar */}
        <div className="space-y-3">
          <form onSubmit={handleSearchSubmit} className="flex flex-col gap-2 rounded-3xl border border-slate-200/80 bg-white p-3 shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none sm:flex-row">
            <div className="relative flex-1">
              <Search className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
              <Input
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder={chrome?.search || 'Search products by name, description or category...'}
                className="pl-10 rounded-2xl border-slate-200/80 bg-slate-50/50 text-xs dark:border-slate-800 dark:bg-slate-800/50"
              />
            </div>

            <div className="flex gap-2">
              <select
                value={sort}
                onChange={(e) => {
                  setSort(e.target.value)
                  applyFilters({ sort: e.target.value })
                }}
                className="h-10 rounded-2xl border border-slate-200/80 bg-slate-50/50 px-3 text-xs font-semibold text-slate-700 outline-hidden dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300"
              >
                <option value="name_asc">Sort A–Z</option>
                <option value="price_asc">Price: Low to High</option>
                <option value="price_desc">Price: High to Low</option>
                <option value="newest">Newest First</option>
              </select>

              <Button
                type="button"
                variant="outline"
                onClick={() => setShowFilterDrawer(!showFilterDrawer)}
                className={`gap-1.5 rounded-2xl border-slate-200/80 text-xs font-semibold dark:border-slate-800 ${
                  showFilterDrawer || inStockOnly || minPrice || maxPrice ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white' : ''
                }`}
              >
                <SlidersHorizontal className="h-3.5 w-3.5" />
                <span className="hidden sm:inline">Filters</span>
              </Button>

              <Button
                type="submit"
                size="default"
                className="px-5 text-xs font-bold text-white shadow-xs transition-opacity hover:opacity-90"
                style={{
                  background: 'var(--sf-primary, #0f172a)',
                  borderRadius: 'var(--sf-radius, 1rem)',
                }}
              >
                <Search className="h-3.5 w-3.5 mr-1" /> Search
              </Button>
            </div>
          </form>

          {/* Collapsible Filter Expansion */}
          {showFilterDrawer && (
            <div className="rounded-3xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
              <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex flex-wrap items-center gap-3">
                  {/* In Stock Toggle */}
                  <button
                    type="button"
                    onClick={toggleInStock}
                    className={`flex items-center gap-2 rounded-xl border px-3 py-1.5 text-xs font-semibold transition-all ${
                      inStockOnly
                        ? 'border-emerald-600 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                        : 'border-slate-200 bg-white text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400'
                    }`}
                  >
                    <div className={`h-2 w-2 rounded-full ${inStockOnly ? 'bg-emerald-600' : 'bg-slate-300'}`} />
                    In Stock Only
                  </button>

                  {/* Price Range Filter Inputs */}
                  <div className="flex items-center gap-1.5 text-xs">
                    <span className="text-slate-400 font-semibold">Price:</span>
                    <Input
                      type="number"
                      placeholder="Min"
                      value={minPrice}
                      onChange={(e) => setMinPrice(e.target.value)}
                      className="h-8 w-20 rounded-xl text-xs"
                    />
                    <span className="text-slate-300">-</span>
                    <Input
                      type="number"
                      placeholder="Max"
                      value={maxPrice}
                      onChange={(e) => setMaxPrice(e.target.value)}
                      className="h-8 w-20 rounded-xl text-xs"
                    />
                    <Button
                      type="button"
                      size="sm"
                      onClick={() => applyFilters()}
                      className="h-8 rounded-xl px-3 text-xs"
                    >
                      Apply
                    </Button>
                  </div>
                </div>

                {hasActiveFilters && (
                  <button
                    type="button"
                    onClick={resetAllFilters}
                    className="flex items-center gap-1 text-xs font-semibold text-rose-600 hover:underline dark:text-rose-400"
                  >
                    <RotateCcw className="h-3 w-3" /> Clear All Filters
                  </button>
                )}
              </div>
            </div>
          )}
        </div>

        {businessUnits.length > 0 && (
          <div className="rounded-3xl border border-slate-200/80 bg-white px-4 py-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Primary storefront</p>
            <div className="mt-2 flex flex-wrap items-end justify-between gap-4">
              <div>
                <p className="text-base font-extrabold text-slate-900 dark:text-white">Books & Publications</p>
                <p className="max-w-xl text-xs text-slate-500 dark:text-slate-400">Browse Jostinah's books first. Her BnB stays are available below as part of the same brand.</p>
              </div>
              {hospitalityUnit ? (
                <div className="border-l border-slate-200 pl-4 dark:border-slate-700">
                  <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-400">Also available</p>
                  <p className="text-sm font-extrabold text-slate-900 dark:text-white">{hospitalityUnit.name}</p>
                </div>
              ) : null}
            </div>
          </div>
        )}

        {bnbProducts.length > 0 && (
          <section className="overflow-hidden rounded-3xl border border-emerald-200/80 bg-emerald-50 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/30">
            <div className="grid grid-cols-4 gap-1.5 bg-emerald-100/70 p-1.5 dark:bg-emerald-950/50 sm:grid-cols-8">
              {bnbBannerImages.map((image, index) => (
                <img
                  key={image}
                  src={image}
                  alt={`BnB stay ${index + 1}`}
                  className="aspect-[4/3] w-full rounded-xl object-cover"
                  loading={index > 3 ? 'lazy' : 'eager'}
                />
              ))}
            </div>
            <div className="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
              <div className="flex items-start gap-3">
                <div className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-sm">
                  <CalendarCheck className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-400">Stay with us</p>
                  <h2 className="mt-1 text-base font-extrabold tracking-tight text-slate-900 dark:text-white">
                    {bnbProducts.length === 1 ? bnbProducts[0].name : 'Book a BnB stay'}
                  </h2>
                  <p className="mt-1 max-w-2xl text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                    Check availability and request your stay online.
                  </p>
                </div>
              </div>
              <a
                href={`/book/${slug}`}
                className="inline-flex shrink-0 items-center justify-center gap-2 rounded-2xl bg-emerald-700 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition-opacity hover:opacity-90"
              >
                Book your stay <ArrowRight className="h-3.5 w-3.5" />
              </a>
            </div>
          </section>
        )}

        {/* Category Pills Bar */}
        {allCategories.length > 0 && (
          <div className="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
            <button
              type="button"
              onClick={() => setCategory(null)}
              className={`rounded-full px-4 py-2 text-xs font-bold transition-all ${
                !filters.category
                  ? 'text-white shadow-md'
                  : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200/80 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-400'
              }`}
              style={!filters.category ? { background: 'var(--sf-primary, #0f172a)' } : undefined}
            >
              All Categories
            </button>
            {allCategories.map((c) => {
              const isActive = filters.category === c
              return (
                <button
                  key={c}
                  type="button"
                  onClick={() => setCategory(c)}
                  className={`rounded-full px-4 py-2 text-xs font-bold whitespace-nowrap transition-all ${
                    isActive
                      ? 'text-white shadow-md'
                      : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200/80 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-400'
                  }`}
                  style={isActive ? { background: 'var(--sf-primary, #0f172a)' } : undefined}
                >
                  {c}
                </button>
              )
            })}
          </div>
        )}

        {/* Sections & Catalog Grid */}
        {resolvedSections.map((section, idx) => {
          if (section.type === 'hero') {
            return (
              <section key={idx} className="overflow-hidden rounded-3xl bg-slate-900 text-white shadow-xl">
                <div className="grid gap-6 p-8 md:grid-cols-2 md:items-center">
                  <div className="space-y-3">
                    <h2 className="text-3xl font-extrabold tracking-tight">{section.headline || company.name}</h2>
                    {section.subhead && <p className="text-xs text-slate-300 leading-relaxed">{section.subhead}</p>}
                    {section.cta_label && (
                      <a
                        href={section.cta_href || `#catalog`}
                        className="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-2.5 text-xs font-bold text-slate-900 shadow-md transition-transform hover:scale-105"
                      >
                        {section.cta_label} <ArrowRight className="h-3.5 w-3.5" />
                      </a>
                    )}
                  </div>
                  {section.image && (
                    <div className="flex items-center justify-center md:justify-end">
                      <div className="inline-flex max-w-full items-center justify-center overflow-hidden rounded-2xl shadow-md">
                        <img
                          src={section.image}
                          alt={section.headline || company.name}
                          className="h-auto max-h-52 w-auto max-w-full object-contain md:max-h-64"
                        />
                      </div>
                    </div>
                  )}
                </div>
              </section>
            )
          }
          if (section.type === 'announcement') {
            return (
              <p key={idx} className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-medium text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300">
                {section.text}
              </p>
            )
          }
          if (section.type === 'rich_text') {
            return (
              <div key={idx} className="prose prose-sm max-w-none text-slate-700 dark:text-slate-300" dangerouslySetInnerHTML={{ __html: section.html || '' }} />
            )
          }
          if (section.type === 'featured_products') {
            const featured = (section.products || []).filter((product) => product.category?.toLowerCase() !== 'bnb stays')
            if (featured.length === 0) return null
            return (
              <section key={idx} className="space-y-4">
                <div className="flex items-center justify-between">
                  <h2 className="text-sm font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-1.5">
                    <Sparkles className="h-4 w-4 text-amber-500" /> {section.headline || 'Featured Products'}
                  </h2>
                </div>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                  {featured.map((product) => (
                    <ProductCard
                      key={product.id}
                      slug={slug}
                      product={product}
                      currency={displayCurrency}
                      rate={displayRate}
                      isWished={wishlistIds.includes(product.id)}
                      onWishlistToggle={toggleWishlist}
                      onQuickAdd={handleQuickAdd}
                    />
                  ))}
                </div>
              </section>
            )
          }
          if (section.type === 'collection_shelf') {
            const shelfProducts = (section.products || []).filter((product) => product.category?.toLowerCase() !== 'bnb stays')
            if (shelfProducts.length === 0) return null
            return (
              <section key={idx} className="space-y-4">
                <div className="flex items-center justify-between px-1">
                  <h2 className="text-sm font-bold tracking-tight text-slate-900 dark:text-white">
                    {section.headline || section.category || 'Collection'}
                  </h2>
                </div>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                  {shelfProducts.map((product) => (
                    <ProductCard
                      key={product.id}
                      slug={slug}
                      product={product}
                      currency={displayCurrency}
                      rate={displayRate}
                      isWished={wishlistIds.includes(product.id)}
                      onWishlistToggle={toggleWishlist}
                      onQuickAdd={handleQuickAdd}
                    />
                  ))}
                </div>
              </section>
            )
          }
          if (section.type === 'about') {
            if (!section.body && !section.title) return null
            return (
              <section key={idx} className="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 md:p-8">
                <div className="max-w-2xl space-y-3">
                  <h3 className="text-base font-bold text-slate-900 dark:text-white">{section.title || `About ${company.name}`}</h3>
                  {section.body && <p className="text-xs text-slate-600 dark:text-slate-300 leading-relaxed whitespace-pre-line">{section.body}</p>}
                  {section.cta_href && (
                    <Link
                      href={section.cta_href}
                      className="inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:underline"
                    >
                      {section.cta_label || 'Read more'} <ArrowRight className="h-3.5 w-3.5" />
                    </Link>
                  )}
                </div>
              </section>
            )
          }
          if (section.type === 'testimonials') {
            const items = section.items || []
            if (items.length === 0) return null
            return (
              <section key={idx} className="space-y-4">
                <div className="flex items-center justify-between px-1">
                  <h2 className="text-sm font-bold tracking-tight text-slate-900 dark:text-white">
                    {section.headline || 'What Shoppers Say'}
                  </h2>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                  {items.map((item, qIdx) => (
                    <div key={qIdx} className="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                      <p className="text-xs italic text-slate-600 dark:text-slate-300">"{item.quote}"</p>
                      <p className="mt-2 text-[11px] font-semibold text-slate-900 dark:text-white">— {item.author}</p>
                    </div>
                  ))}
                </div>
              </section>
            )
          }
          if (section.type === 'catalog') {
            return (
              <section key={idx} id="catalog" className="space-y-4">
                <div className="flex items-center justify-between px-1">
                  <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
                    {visibleProducts.length} {visibleProducts.length === 1 ? 'Product' : 'Products'} Available
                  </p>
                </div>

                {visibleProducts.length === 0 ? (
                  <div className="rounded-3xl border border-dashed border-slate-200/80 bg-white p-12 text-center text-xs text-slate-500 shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
                    <p className="font-semibold text-slate-700 dark:text-slate-300 mb-2">No products match your search keyword or filter.</p>
                    {hasActiveFilters && (
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={resetAllFilters}
                        className="mt-2 rounded-xl text-xs"
                      >
                        Clear All Filters
                      </Button>
                    )}
                  </div>
                ) : (
                  integratedCatalog ? (
                      <div className="space-y-8">
                        {primaryProducts.length > 0 ? (
                          <section className="space-y-4">
                            <div className="flex items-end justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                              <div>
                                <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Main collection</p>
                                <h2 className="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">Books & Publications</h2>
                              </div>
                              <span className="text-xs font-semibold text-slate-400">{primaryProducts.length} items</span>
                            </div>
                            {renderProductGrid(primaryProducts)}
                          </section>
                        ) : null}
                        {secondaryProducts.length > 0 ? (
                          <section className="space-y-4 border-t-2 border-emerald-100 pt-8 dark:border-emerald-950">
                            <div className="flex items-end justify-between border-b border-emerald-100 pb-3 dark:border-emerald-950">
                              <div>
                                <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-400">Hospitality</p>
                                <h2 className="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">{hospitalityUnit?.name || 'BnB Stays'}</h2>
                              </div>
                              <span className="text-xs font-semibold text-slate-400">{secondaryProducts.length} stays</span>
                            </div>
                            {renderProductGrid(secondaryProducts)}
                          </section>
                        ) : null}
                      </div>
                    ) : renderProductGrid(visibleProducts)
                )}
              </section>
            )
          }
          return null
        })}
      </main>

      {/* Floating WhatsApp Chat Pill */}
      {company.whatsappUrl && (
        <a
          href={company.whatsappUrl}
          target="_blank"
          rel="noreferrer"
          className="fixed bottom-6 right-5 z-20 inline-flex items-center gap-2 rounded-full px-4 py-3 text-xs font-bold text-white shadow-xl transition-transform hover:scale-105"
          style={{ background: '#128C7E' }}
        >
          <MessageCircle className="h-4 w-4" />
          {theme.whatsapp_btn_text || 'Chat on WhatsApp'}
        </a>
      )}

      {/* Mobile Sticky Floating Cart Bar */}
      {cartCount > 0 && (
        <div className="fixed bottom-4 left-4 right-4 z-30 sm:hidden">
          <Link
            href={`/s/${slug}/cart`}
            className="flex items-center justify-between p-3.5 text-white shadow-2xl transition-transform active:scale-98"
            style={{
              background: 'var(--sf-primary, #0f172a)',
              borderRadius: 'var(--sf-radius, 1rem)',
            }}
          >
            <div className="flex items-center gap-2.5">
              <span className="flex h-7 w-7 items-center justify-center rounded-xl bg-white/20 text-xs font-extrabold">
                {cartCount}
              </span>
              <span className="text-xs font-bold">{chrome?.cart || 'View Shopping Cart'}</span>
            </div>
            <div className="flex items-center gap-1.5 text-xs font-bold">
              <span>{chrome?.checkout || 'Checkout'}</span>
              <ArrowRight className="h-4 w-4" />
            </div>
          </Link>
        </div>
      )}

      <footer className="border-t border-slate-200/80 py-8 text-center text-xs font-medium text-slate-400 dark:border-slate-800">
        <div className="mb-2">
          <Link href={`/s/${slug}/terms`} className="underline underline-offset-2 hover:text-slate-600 dark:hover:text-slate-200">
            Terms and conditions
          </Link>
        </div>
        {theme.footer_text || 'Powered by RelayIQ'}
      </footer>

      {/* Modals */}
      <QuickVariantModal
        open={quickAddModalOpen}
        onOpenChange={setQuickAddModalOpen}
        product={quickAddProduct}
        slug={slug}
        currency={displayCurrency}
        rate={displayRate}
      />

      <StorefrontAuthModal
        open={authModalOpen}
        onOpenChange={setAuthModalOpen}
        slug={slug}
        companyName={company.name}
        termsUrl={company.termsUrl}
      />
    </div>
  )
}
