'use client'

import { FormEvent, useMemo, useState } from 'react'
import { Link, router } from '@inertiajs/react'
import {
  ArrowRight,
  Heart,
  LogOut,
  MessageCircle,
  Search,
  ShoppingBag,
  SlidersHorizontal,
  User,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { SeoHead, type SeoPayload } from '@/components/seo/SeoHead'
import { StorefrontAuthModal } from '@/components/store/StorefrontAuthModal'

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
}

type Filters = {
  q?: string | null
  sort?: string | null
  category?: string | null
  in_stock?: string | boolean | null
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
  products?: StoreProduct[]
}

type Props = {
  slug: string
  company: {
    name: string
    logo?: string | null
    currency: string
    whatsappUrl?: string | null
    authCustomer?: { id: number; name: string; email: string } | null
    theme?: { primary_color?: string; accent_color?: string; announcement_bar?: string; footer_text?: string }
  }
  products: StoreProduct[]
  filters?: Filters
  sections?: Section[]
  cartCount: number
  wishlist?: string[]
  chrome?: { cart?: string; wishlist?: string; search?: string; trackOrder?: string }
  seo?: SeoPayload | null
}

function formatPrice(amount: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount)
  } catch {
    return `${currency} ${amount.toFixed(2)}`
  }
}

function ProductCard({ slug, product, currency }: { slug: string; product: StoreProduct; currency: string }) {
  const href = `/s/${slug}/p/${product.slug || product.id}`
  return (
    <Link
      href={href}
      className="group relative flex flex-col overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xs transition-all duration-300 hover:-translate-y-1 hover:shadow-xl dark:border-slate-800 dark:bg-slate-900"
    >
      {/* Badges */}
      <div className="absolute left-3 top-3 z-10 flex flex-col gap-1">
        {product.onSale && (
          <span className="rounded-full bg-rose-600 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white shadow-sm">
            {product.discountPercent ? `-${product.discountPercent}% OFF` : 'SALE'}
          </span>
        )}
        {product.lowStock && !product.soldOut && (
          <span className="rounded-full bg-amber-500 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white shadow-sm">
            LOW STOCK
          </span>
        )}
      </div>

      {product.soldOut && (
        <span className="absolute right-3 top-3 z-10 rounded-full bg-slate-900/90 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white shadow-sm backdrop-blur-xs">
          SOLD OUT
        </span>
      )}

      {/* Product Image Container */}
      <div className="relative aspect-square w-full overflow-hidden bg-slate-100 dark:bg-slate-800">
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

        {/* Hover overlay pill */}
        <div className="absolute inset-0 flex items-center justify-center bg-slate-900/20 opacity-0 transition-opacity duration-300 group-hover:opacity-100">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-white px-4 py-2 text-xs font-bold text-slate-900 shadow-md transform translate-y-2 transition-transform duration-300 group-hover:translate-y-0">
            View Details <ArrowRight className="h-3.5 w-3.5" />
          </span>
        </div>
      </div>

      {/* Card Content */}
      <div className="flex flex-1 flex-col justify-between p-4">
        <div>
          {product.category && (
            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">{product.category}</span>
          )}
          <h3 className="line-clamp-1 text-xs font-bold tracking-tight text-slate-900 dark:text-white group-hover:text-emerald-600 transition-colors">
            {product.name}
          </h3>
        </div>

        <div className="mt-2.5 flex items-baseline justify-between">
          <div className="flex items-baseline gap-1.5">
            <span className="text-sm font-extrabold text-slate-900 dark:text-white" style={{ color: 'var(--sf-primary, inherit)' }}>
              {formatPrice(product.price, currency)}
            </span>
            {product.onSale && product.compareAtPrice != null && (
              <span className="text-[11px] font-medium text-slate-400 line-through">
                {formatPrice(product.compareAtPrice, currency)}
              </span>
            )}
          </div>
        </div>
      </div>
    </Link>
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
  chrome,
  seo,
}: Props) {
  const [q, setQ] = useState(String(filters.q ?? ''))
  const [sort, setSort] = useState(String(filters.sort ?? 'name_asc'))
  const [authModalOpen, setAuthModalOpen] = useState(false)
  const theme = company.theme ?? {}
  const style = {
    ['--sf-primary' as string]: theme.primary_color || '#0f172a',
    ['--sf-accent' as string]: theme.accent_color || '#0f172a',
  }
  const allCategories = useMemo(
    () => Array.from(new Set(products.map((p) => p.category).filter(Boolean))) as string[],
    [products]
  )
  const resolvedSections = sections && sections.length > 0 ? sections : [{ type: 'catalog' }]

  const applyFilters = (e?: FormEvent) => {
    e?.preventDefault()
    router.get(
      `/s/${slug}`,
      {
        q: q || undefined,
        sort: sort || undefined,
        category: filters.category || undefined,
        in_stock: filters.in_stock ? 1 : undefined,
      },
      { preserveState: true, replace: true }
    )
  }

  const setCategory = (category: string | null) => {
    router.get(
      `/s/${slug}`,
      {
        q: q || undefined,
        sort: sort || undefined,
        category: category || undefined,
        in_stock: filters.in_stock ? 1 : undefined,
      },
      { preserveState: true, replace: true }
    )
  }

  return (
    <div className="min-h-screen bg-slate-50/80 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100" style={style}>
      <SeoHead seo={seo} fallbackTitle={`${company.name} — Shop`} />

      {theme.announcement_bar ? (
        <div className="bg-[var(--sf-accent)] px-4 py-2 text-center text-xs font-semibold text-white shadow-xs">
          {theme.announcement_bar}
        </div>
      ) : null}

      {/* Top Navbar */}
      <header className="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3.5">
          <div className="flex items-center gap-3">
            {company.logo ? (
              <img src={company.logo} alt={company.name} className="h-9 w-9 rounded-2xl object-cover shadow-xs" />
            ) : (
              <div
                className="flex h-9 w-9 items-center justify-center rounded-2xl text-sm font-extrabold text-white shadow-xs"
                style={{ background: 'var(--sf-primary)' }}
              >
                {company.name.charAt(0).toUpperCase()}
              </div>
            )}
            <h1 className="text-base font-extrabold tracking-tight text-slate-900 dark:text-white">{company.name}</h1>
          </div>

          <div className="flex items-center gap-2">
            <Link href={`/s/${slug}/track`} className="hidden text-xs font-semibold text-slate-500 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-white sm:inline">
              {chrome?.trackOrder || 'Track order'}
            </Link>

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
                <Heart className={`h-3.5 w-3.5 ${wishlist.length > 0 ? 'fill-rose-500 text-rose-500' : ''}`} />
                <span className="hidden sm:inline">{chrome?.wishlist || 'Wishlist'}</span>
                {wishlist.length > 0 ? ` (${wishlist.length})` : ''}
              </Button>
            </Link>

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

      {/* Main Catalog Container */}
      <main className="mx-auto max-w-5xl space-y-7 px-4 py-8">
        
        {/* Search & Filter Bar */}
        <form onSubmit={applyFilters} className="flex flex-col gap-3 rounded-3xl border border-slate-200/80 bg-white p-3.5 shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none sm:flex-row">
          <div className="relative flex-1">
            <Search className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
            <Input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder={chrome?.search || 'Search products by name or category...'}
              className="pl-10 rounded-2xl border-slate-200/80 bg-slate-50/50 text-xs dark:border-slate-800 dark:bg-slate-800/50"
            />
          </div>

          <div className="flex gap-2">
            <select
              value={sort}
              onChange={(e) => {
                setSort(e.target.value)
                router.get(
                  `/s/${slug}`,
                  { q: q || undefined, sort: e.target.value, category: filters.category || undefined },
                  { preserveState: true, replace: true }
                )
              }}
              className="h-10 rounded-2xl border border-slate-200/80 bg-slate-50/50 px-3.5 text-xs font-semibold text-slate-700 outline-hidden dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300"
            >
              <option value="name_asc">Sort A–Z</option>
              <option value="price_asc">Price: Low to High</option>
              <option value="price_desc">Price: High to Low</option>
              <option value="newest">Newest First</option>
            </select>

            <Button type="submit" size="default" className="rounded-2xl bg-slate-900 px-5 text-xs font-bold text-white shadow-xs hover:bg-slate-800 dark:bg-emerald-600">
              <Search className="h-3.5 w-3.5" /> Search
            </Button>
          </div>
        </form>

        {/* Category Pills Bar */}
        {allCategories.length > 0 && (
          <div className="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
            <button
              type="button"
              onClick={() => setCategory(null)}
              className={`rounded-full px-4 py-2 text-xs font-bold transition-all ${
                !filters.category
                  ? 'bg-slate-900 text-white shadow-md dark:bg-white dark:text-slate-900'
                  : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200/80 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-400'
              }`}
            >
              All Categories
            </button>
            {allCategories.map((c) => (
              <button
                key={c}
                type="button"
                onClick={() => setCategory(c)}
                className={`rounded-full px-4 py-2 text-xs font-bold whitespace-nowrap transition-all ${
                  filters.category === c
                    ? 'bg-slate-900 text-white shadow-md dark:bg-white dark:text-slate-900'
                    : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200/80 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-400'
                }`}
              >
                {c}
              </button>
            ))}
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
                      <a href={section.cta_href || `#catalog`} className="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-2.5 text-xs font-bold text-slate-900 shadow-md transition-transform hover:scale-105">
                        {section.cta_label} <ArrowRight className="h-3.5 w-3.5" />
                      </a>
                    )}
                  </div>
                  {section.image && <img src={section.image} alt="" className="h-48 w-full rounded-2xl object-cover md:h-64" />}
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
            const featured = section.products || []
            if (featured.length === 0) return null
            return (
              <section key={idx} className="space-y-4">
                <h2 className="text-sm font-bold tracking-tight text-slate-900 dark:text-white">Featured Products</h2>
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                  {featured.map((product) => (
                    <ProductCard key={product.id} slug={slug} product={product} currency={company.currency} />
                  ))}
                </div>
              </section>
            )
          }
          // Catalog (Default)
          return (
            <section key={idx} id="catalog" className="space-y-4">
              {products.length === 0 ? (
                <div className="rounded-3xl border border-dashed border-slate-200/80 bg-white p-12 text-center text-xs text-slate-500 shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
                  No products match your search keyword or filter.
                </div>
              ) : (
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                  {products.map((product) => (
                    <ProductCard key={product.id} slug={slug} product={product} currency={company.currency} />
                  ))}
                </div>
              )}
            </section>
          )
        })}
      </main>

      {/* Floating WhatsApp Chat Pill */}
      {company.whatsappUrl && (
        <a
          href={company.whatsappUrl}
          target="_blank"
          rel="noreferrer"
          className="fixed bottom-5 right-5 z-20 inline-flex items-center gap-2 rounded-full px-4 py-3 text-xs font-bold text-white shadow-xl transition-transform hover:scale-105"
          style={{ background: '#128C7E' }}
        >
          <MessageCircle className="h-4 w-4" />
          Chat on WhatsApp
        </a>
      )}

      <footer className="border-t border-slate-200/80 py-8 text-center text-xs font-medium text-slate-400 dark:border-slate-800">
        {theme.footer_text || 'Powered by RelayIQ'}
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
