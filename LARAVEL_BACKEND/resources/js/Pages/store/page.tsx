'use client'

import { FormEvent, useMemo, useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import { MessageCircle, ShoppingBag } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

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
    theme?: { primary_color?: string; accent_color?: string; announcement_bar?: string; footer_text?: string }
  }
  products: StoreProduct[]
  filters?: Filters
  sections?: Section[]
  cartCount: number
  chrome?: { cart?: string; search?: string; trackOrder?: string }
  seo?: { title?: string; description?: string }
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
      className="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md"
    >
      {product.onSale && (
        <span className="absolute left-2 top-2 z-10 rounded bg-rose-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
          Sale
        </span>
      )}
      {product.soldOut && (
        <span className="absolute right-2 top-2 z-10 rounded bg-slate-900/80 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
          Sold out
        </span>
      )}
      <div className="aspect-square w-full overflow-hidden bg-slate-100">
        {product.image ? (
          <img
            src={product.image}
            alt={product.name}
            loading="lazy"
            className="h-full w-full object-cover transition group-hover:scale-105"
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center text-slate-300">
            <ShoppingBag className="h-10 w-10" />
          </div>
        )}
      </div>
      <div className="space-y-1 p-3">
        <p className="line-clamp-1 text-sm font-medium">{product.name}</p>
        <div className="flex flex-wrap items-baseline gap-2">
          <p className="text-sm font-semibold" style={{ color: 'var(--sf-primary, inherit)' }}>
            {formatPrice(product.price, currency)}
          </p>
          {product.onSale && product.compareAtPrice != null && (
            <p className="text-xs text-slate-400 line-through">{formatPrice(product.compareAtPrice, currency)}</p>
          )}
        </div>
        {product.lowStock && !product.soldOut && <p className="text-[11px] text-amber-600">Only a few left</p>}
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
  chrome,
  seo,
}: Props) {
  const [q, setQ] = useState(String(filters.q ?? ''))
  const [sort, setSort] = useState(String(filters.sort ?? 'name_asc'))
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
    <div className="min-h-screen bg-white text-slate-900" style={style}>
      <Head>
        <title>{seo?.title || `${company.name} — Shop`}</title>
        {seo?.description ? <meta head-key="description" name="description" content={seo.description} /> : null}
      </Head>

      {theme.announcement_bar ? (
        <div className="bg-[var(--sf-accent)] px-4 py-2 text-center text-xs font-medium text-white">
          {theme.announcement_bar}
        </div>
      ) : null}

      <header className="sticky top-0 z-10 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-4">
          <div className="flex items-center gap-3">
            {company.logo ? (
              <img src={company.logo} alt={company.name} className="h-9 w-9 rounded-full object-cover" />
            ) : (
              <div
                className="flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold text-white"
                style={{ background: 'var(--sf-primary)' }}
              >
                {company.name.charAt(0).toUpperCase()}
              </div>
            )}
            <h1 className="text-lg font-semibold tracking-tight">{company.name}</h1>
          </div>
          <div className="flex items-center gap-2">
            <Link href={`/s/${slug}/track`} className="hidden text-xs text-slate-500 hover:text-slate-900 sm:inline">
              {chrome?.trackOrder || 'Track order'}
            </Link>
            <Link href={`/s/${slug}/cart`}>
              <Button variant="outline" className="gap-2" style={{ borderColor: 'var(--sf-primary)', color: 'var(--sf-primary)' }}>
                <ShoppingBag className="h-4 w-4" />
                {chrome?.cart || 'Cart'}
                {cartCount > 0 ? ` (${cartCount})` : ''}
              </Button>
            </Link>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-5xl space-y-8 px-4 py-8">
        <form onSubmit={applyFilters} className="flex flex-col gap-3 sm:flex-row">
          <Input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder={chrome?.search || 'Search products'}
            className="flex-1"
          />
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
            className="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm"
          >
            <option value="name_asc">Name A–Z</option>
            <option value="price_asc">Price: low to high</option>
            <option value="price_desc">Price: high to low</option>
            <option value="newest">Newest</option>
          </select>
          <Button type="submit" style={{ background: 'var(--sf-primary)' }}>
            {chrome?.search || 'Search'}
          </Button>
        </form>

        {allCategories.length > 0 && (
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              onClick={() => setCategory(null)}
              className={`rounded-full border px-3 py-1 text-xs ${!filters.category ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-600'}`}
            >
              All
            </button>
            {allCategories.map((c) => (
              <button
                key={c}
                type="button"
                onClick={() => setCategory(c)}
                className={`rounded-full border px-3 py-1 text-xs ${filters.category === c ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-600'}`}
              >
                {c}
              </button>
            ))}
          </div>
        )}

        {resolvedSections.map((section, idx) => {
          if (section.type === 'hero') {
            return (
              <section key={idx} className="overflow-hidden rounded-3xl bg-slate-900 text-white">
                <div className="grid gap-6 p-8 md:grid-cols-2 md:items-center">
                  <div className="space-y-3">
                    <h2 className="text-3xl font-semibold tracking-tight">{section.headline || company.name}</h2>
                    {section.subhead ? <p className="text-slate-300">{section.subhead}</p> : null}
                    {section.cta_label ? (
                      <a href={section.cta_href || `#catalog`} className="inline-block rounded-full bg-white px-4 py-2 text-sm font-medium text-slate-900">
                        {section.cta_label}
                      </a>
                    ) : null}
                  </div>
                  {section.image ? <img src={section.image} alt="" className="h-48 w-full rounded-2xl object-cover md:h-64" /> : null}
                </div>
              </section>
            )
          }
          if (section.type === 'announcement') {
            return (
              <p key={idx} className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                {section.text}
              </p>
            )
          }
          if (section.type === 'rich_text') {
            return (
              <div key={idx} className="prose prose-sm max-w-none text-slate-700" dangerouslySetInnerHTML={{ __html: section.html || '' }} />
            )
          }
          if (section.type === 'featured_products') {
            const featured = section.products || []
            if (featured.length === 0) return null
            return (
              <section key={idx} className="space-y-4">
                <h2 className="text-lg font-semibold">Featured</h2>
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                  {featured.map((product) => (
                    <ProductCard key={product.id} slug={slug} product={product} currency={company.currency} />
                  ))}
                </div>
              </section>
            )
          }
          // catalog (default)
          return (
            <section key={idx} id="catalog" className="space-y-4">
              {products.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-slate-200 p-12 text-center text-slate-500">
                  No products match your search. Try a different keyword or clear filters.
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

      {company.whatsappUrl ? (
        <a
          href={company.whatsappUrl}
          target="_blank"
          rel="noreferrer"
          className="fixed bottom-5 right-5 z-20 inline-flex items-center gap-2 rounded-full px-4 py-3 text-sm font-medium text-white shadow-lg"
          style={{ background: '#128C7E' }}
        >
          <MessageCircle className="h-4 w-4" />
          Chat on WhatsApp
        </a>
      ) : null}

      <footer className="border-t border-slate-100 py-8 text-center text-xs text-slate-400">
        {theme.footer_text || 'Powered by RelayIQ'}
      </footer>
    </div>
  )
}
