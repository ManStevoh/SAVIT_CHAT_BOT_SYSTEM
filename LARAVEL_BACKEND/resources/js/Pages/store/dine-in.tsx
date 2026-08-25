'use client'

import { useMemo, useState } from 'react'
import { Link } from '@inertiajs/react'
import { ArrowRight, Search, ShoppingBag, UtensilsCrossed } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { resolveStorefrontStyle, type BrandTheme } from '@/lib/theme-utils'
import { SeoHead, type SeoPayload } from '@/components/seo/SeoHead'

type Product = {
  id: string
  slug?: string | null
  name: string
  description?: string | null
  price: number
  category?: string | null
  image?: string | null
  onSale?: boolean
  compareAtPrice?: number | null
}

type Props = {
  company: { name: string; logo?: string | null; currency?: string; theme?: BrandTheme }
  table: { id: string; name: string; qrToken: string }
  products: Product[]
  slug?: string | null
  seo?: SeoPayload | null
}

function formatPrice(amount: number, currency: string = 'USD'): string {
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount)
  } catch {
    return `${currency} ${amount.toFixed(2)}`
  }
}

export default function DineInPage({ company, table, products, slug, seo }: Props) {
  const [q, setQ] = useState('')
  const [selectedCategory, setSelectedCategory] = useState<string | null>(null)
  const currency = company.currency || 'USD'

  const allCategories = useMemo(
    () => Array.from(new Set(products.map((p) => p.category).filter(Boolean))) as string[],
    [products]
  )

  const filteredProducts = useMemo(() => {
    return products.filter((product) => {
      const matchSearch =
        !q ||
        product.name.toLowerCase().includes(q.toLowerCase()) ||
        (product.description && product.description.toLowerCase().includes(q.toLowerCase()))
      const matchCategory = !selectedCategory || product.category === selectedCategory
      return matchSearch && matchCategory
    })
  }, [products, q, selectedCategory])

  const checkoutUrl = slug
    ? `/s/${slug}/checkout?table=${encodeURIComponent(table.qrToken)}`
    : '#'

  const style = resolveStorefrontStyle(company.theme)

  return (
    <>
      <SeoHead seo={seo} fallbackTitle={`Dine-In Menu — Table ${table.name} — ${company.name}`} />
      <div
        className="min-h-screen bg-slate-50/80 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100"
        style={style}
      >
      {/* Header */}
      <header className="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90">
        <div className="mx-auto flex max-w-2xl items-center justify-between px-4 py-3.5">
          <div className="flex items-center gap-3">
            {company.logo ? (
              <img src={company.logo} alt={company.name} className="h-9 w-9 rounded-2xl object-cover shadow-xs" />
            ) : (
              <div className="flex h-9 w-9 items-center justify-center rounded-2xl bg-slate-900 text-sm font-extrabold text-white dark:bg-white dark:text-slate-900">
                {company.name.charAt(0).toUpperCase()}
              </div>
            )}
            <div>
              <h1 className="text-base font-extrabold tracking-tight text-slate-900 dark:text-white">{company.name}</h1>
              <p className="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">Dine-In Menu</p>
            </div>
          </div>

          <div className="flex items-center gap-1.5 rounded-2xl bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
            <UtensilsCrossed className="h-3.5 w-3.5 text-slate-500" />
            <span>Table {table.name}</span>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-2xl space-y-6 px-4 py-6 pb-28">
        {/* Table Banner */}
        <div className="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
          <div className="flex items-center justify-between">
            <div>
              <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Ordering At</span>
              <h2 className="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">Table {table.name}</h2>
              <p className="mt-0.5 text-xs text-slate-500">Orders placed here will be delivered right to your table.</p>
            </div>
            {slug && (
              <Button asChild size="sm" className="rounded-2xl bg-slate-900 text-xs font-bold text-white shadow-md hover:bg-slate-800 dark:bg-emerald-600">
                <a href={checkoutUrl}>
                  View Full Menu & Cart <ArrowRight className="h-3.5 w-3.5 ml-1" />
                </a>
              </Button>
            )}
          </div>
        </div>

        {/* Search Input */}
        <div className="relative">
          <Search className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
          <Input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Search menu items..."
            className="pl-10 rounded-2xl border-slate-200/80 bg-white text-xs dark:border-slate-800 dark:bg-slate-900"
          />
        </div>

        {/* Category Pills */}
        {allCategories.length > 0 && (
          <div className="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
            <button
              type="button"
              onClick={() => setSelectedCategory(null)}
              className={`rounded-full px-4 py-1.5 text-xs font-bold transition-all ${
                selectedCategory === null
                  ? 'bg-slate-900 text-white shadow-md dark:bg-white dark:text-slate-900'
                  : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200/80 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-400'
              }`}
            >
              All Items
            </button>
            {allCategories.map((c) => (
              <button
                key={c}
                type="button"
                onClick={() => setSelectedCategory(c)}
                className={`rounded-full px-4 py-1.5 text-xs font-bold whitespace-nowrap transition-all ${
                  selectedCategory === c
                    ? 'bg-slate-900 text-white shadow-md dark:bg-white dark:text-slate-900'
                    : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200/80 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-400'
                }`}
              >
                {c}
              </button>
            ))}
          </div>
        )}

        {/* Menu Items List */}
        {filteredProducts.length === 0 ? (
          <div className="rounded-3xl border border-dashed border-slate-200/80 bg-white p-12 text-center text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900">
            No menu items found.
          </div>
        ) : (
          <div className="space-y-3">
            {filteredProducts.map((product) => {
              const productUrl = slug ? `/s/${slug}/p/${product.slug || product.id}` : '#'
              return (
                <div
                  key={product.id}
                  className="flex items-center justify-between gap-4 rounded-3xl border border-slate-200/80 bg-white p-4 shadow-xs transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                >
                  <div className="flex items-center gap-3.5">
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
                      {product.category && (
                        <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">{product.category}</span>
                      )}
                      <h3 className="text-xs font-bold text-slate-900 dark:text-white">{product.name}</h3>
                      {product.description && (
                        <p className="line-clamp-1 text-[11px] text-slate-500 dark:text-slate-400">{product.description}</p>
                      )}
                      <p className="mt-1 text-xs font-extrabold text-slate-900 dark:text-white">{formatPrice(product.price, currency)}</p>
                    </div>
                  </div>

                  {slug && (
                    <Button asChild size="sm" className="rounded-xl bg-slate-900 px-3 text-xs font-bold text-white hover:bg-slate-800 dark:bg-emerald-600">
                      <a href={productUrl}>Select</a>
                    </Button>
                  )}
                </div>
              )
            })}
          </div>
        )}
      </main>

      {/* Floating Bottom Sticky Bar */}
      {slug && (
        <div className="fixed bottom-4 left-4 right-4 z-30 max-w-2xl mx-auto">
          <a
            href={checkoutUrl}
            className="flex items-center justify-between rounded-2xl bg-slate-900 p-4 text-white shadow-2xl transition-transform active:scale-98 dark:bg-emerald-600"
          >
            <div className="flex items-center gap-2">
              <UtensilsCrossed className="h-4 w-4 text-emerald-400" />
              <span className="text-xs font-bold">Order for Table {table.name}</span>
            </div>
            <div className="flex items-center gap-1.5 text-xs font-bold">
              <span>Go to Checkout</span>
              <ArrowRight className="h-4 w-4" />
            </div>
          </a>
        </div>
      )}
    </div>
    </>
  )
}

