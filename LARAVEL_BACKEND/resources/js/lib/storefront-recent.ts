export type RecentStoreProduct = {
  id: string
  slug?: string | null
  name: string
  image?: string | null
  price: number
  compareAtPrice?: number | null
  onSale?: boolean
  category?: string | null
  productType?: string | null
  averageRating?: number | null
  reviewCount?: number
  soldOut?: boolean
}

const MAX_RECENT = 8

function storageKey(slug: string): string {
  return `sf-viewed-${slug}`
}

export function recordRecentlyViewed(slug: string, product: RecentStoreProduct): void {
  if (typeof window === 'undefined' || !product.id) {
    return
  }
  try {
    const existing = getRecentlyViewed(slug).filter((item) => item.id !== product.id)
    const next = [product, ...existing].slice(0, MAX_RECENT)
    window.localStorage.setItem(storageKey(slug), JSON.stringify(next))
  } catch {
    // ignore quota / private mode
  }
}

export function getRecentlyViewed(slug: string, excludeId?: string): RecentStoreProduct[] {
  if (typeof window === 'undefined') {
    return []
  }
  try {
    const raw = window.localStorage.getItem(storageKey(slug))
    if (!raw) {
      return []
    }
    const parsed = JSON.parse(raw)
    if (!Array.isArray(parsed)) {
      return []
    }
    return parsed
      .filter((item): item is RecentStoreProduct => item && typeof item.id === 'string' && typeof item.name === 'string')
      .filter((item) => !excludeId || item.id !== excludeId)
      .slice(0, MAX_RECENT)
  } catch {
    return []
  }
}
