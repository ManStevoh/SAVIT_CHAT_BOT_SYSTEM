'use client'

import { Link } from '@inertiajs/react'
import { ShoppingBag } from 'lucide-react'
import { Button } from '@/components/ui/button'

type StoreProduct = {
  id: string
  name: string
  description?: string | null
  price: number
  category?: string | null
  image?: string | null
}

type Props = {
  slug: string
  company: { name: string; logo?: string | null; currency: string }
  products: StoreProduct[]
  cartCount: number
}

function formatPrice(amount: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount)
  } catch {
    return `${currency} ${amount.toFixed(2)}`
  }
}

export default function StorePage({ slug, company, products, cartCount }: Props) {
  const categories = Array.from(new Set(products.map((p) => p.category).filter(Boolean))) as string[]

  return (
    <div className="min-h-screen bg-white text-slate-900">
      <header className="sticky top-0 z-10 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
          <div className="flex items-center gap-3">
            {company.logo ? (
              <img src={company.logo} alt={company.name} className="h-9 w-9 rounded-full object-cover" />
            ) : (
              <div className="flex h-9 w-9 items-center justify-center rounded-full bg-slate-900 text-sm font-semibold text-white">
                {company.name.charAt(0).toUpperCase()}
              </div>
            )}
            <h1 className="text-lg font-semibold tracking-tight">{company.name}</h1>
          </div>
          <Link href={`/s/${slug}/cart`}>
            <Button variant="outline" className="gap-2">
              <ShoppingBag className="h-4 w-4" />
              Cart{cartCount > 0 ? ` (${cartCount})` : ''}
            </Button>
          </Link>
        </div>
      </header>

      <main className="mx-auto max-w-5xl px-4 py-8">
        {categories.length > 0 && (
          <div className="mb-6 flex flex-wrap gap-2">
            {categories.map((c) => (
              <span key={c} className="rounded-full border border-slate-200 px-3 py-1 text-xs text-slate-600">
                {c}
              </span>
            ))}
          </div>
        )}

        {products.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-slate-200 p-12 text-center text-slate-500">
            No products are available right now. Please check back later.
          </div>
        ) : (
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            {products.map((product) => (
              <Link
                key={product.id}
                href={`/s/${slug}/p/${product.id}`}
                className="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md"
              >
                <div className="aspect-square w-full overflow-hidden bg-slate-100">
                  {product.image ? (
                    <img
                      src={product.image}
                      alt={product.name}
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
                  <p className="text-sm text-slate-500">{formatPrice(product.price, company.currency)}</p>
                </div>
              </Link>
            ))}
          </div>
        )}
      </main>

      <footer className="border-t border-slate-100 py-8 text-center text-xs text-slate-400">
        Powered by SAVIT
      </footer>
    </div>
  )
}
