'use client'

import { FormEvent, useMemo, useState } from 'react'
import { Link, router } from '@inertiajs/react'
import { ArrowLeft, Minus, Plus, ShoppingBag } from 'lucide-react'
import { Button } from '@/components/ui/button'

type Variant = { id: string; label: string; price: number; stock: number | null }
type StoreProduct = {
  id: string
  name: string
  description?: string | null
  price: number
  images: string[]
  image?: string | null
  variants: Variant[]
  stock: number | null
  trackInventory: boolean
}

type Props = {
  slug: string
  company: { name: string; currency: string }
  product: StoreProduct
  cartCount: number
}

function formatPrice(amount: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount)
  } catch {
    return `${currency} ${amount.toFixed(2)}`
  }
}

export default function StoreProductPage({ slug, company, product, cartCount }: Props) {
  const [variantId, setVariantId] = useState(product.variants[0]?.id || '')
  const [quantity, setQuantity] = useState(1)
  const [activeImage, setActiveImage] = useState(0)
  const [submitting, setSubmitting] = useState(false)

  const selectedVariant = useMemo(
    () => product.variants.find((v) => v.id === variantId) || null,
    [product.variants, variantId]
  )
  const price = selectedVariant?.price ?? product.price
  const images = product.images.length > 0 ? product.images : product.image ? [product.image] : []

  const addToCart = (e: FormEvent) => {
    e.preventDefault()
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

  return (
    <div className="min-h-screen bg-white text-slate-900">
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
          <div className="aspect-square overflow-hidden rounded-2xl bg-slate-100">
            {images[activeImage] ? (
              <img src={images[activeImage]} alt={product.name} className="h-full w-full object-cover" />
            ) : (
              <div className="flex h-full w-full items-center justify-center text-slate-300">
                <ShoppingBag className="h-16 w-16" />
              </div>
            )}
          </div>
          {images.length > 1 && (
            <div className="flex gap-2">
              {images.map((img, idx) => (
                <button
                  key={img}
                  type="button"
                  onClick={() => setActiveImage(idx)}
                  className={`h-16 w-16 overflow-hidden rounded-lg border ${
                    idx === activeImage ? 'border-slate-900' : 'border-slate-200'
                  }`}
                >
                  <img src={img} alt="" className="h-full w-full object-cover" />
                </button>
              ))}
            </div>
          )}
        </div>

        <form onSubmit={addToCart} className="space-y-5">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight">{product.name}</h1>
            <p className="mt-1 text-xl text-slate-700">{formatPrice(price, company.currency)}</p>
          </div>

          {product.description && <p className="text-sm leading-relaxed text-slate-600">{product.description}</p>}

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
                onClick={() => setQuantity((q) => q + 1)}
                className="p-2 text-slate-600 hover:text-slate-900"
              >
                <Plus className="h-4 w-4" />
              </button>
            </div>
          </div>

          <Button type="submit" disabled={submitting} className="w-full sm:w-auto">
            {submitting ? 'Adding…' : 'Add to cart'}
          </Button>
        </form>
      </main>
    </div>
  )
}
