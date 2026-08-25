import { router, usePage } from '@inertiajs/react'
import { useCallback, useMemo } from 'react'

export function useRouter() {
  return useMemo(
    () => ({
      push: (url: string, _options?: { scroll?: boolean }) => router.visit(url),
      replace: (url: string, _options?: { scroll?: boolean }) => router.visit(url, { replace: true }),
      back: () => window.history.back(),
      refresh: () => router.reload(),
      prefetch: (_url: string) => undefined,
    }),
    [],
  )
}

export function usePathname(): string {
  try {
    const { url } = usePage()
    return new URL(url, window.location.origin).pathname
  } catch {
    return typeof window !== 'undefined' ? window.location.pathname : ''
  }
}

export function useSearchParams() {
  let currentUrl = ''
  try {
    const page = usePage()
    currentUrl = page.url
  } catch {
    currentUrl = typeof window !== 'undefined' ? window.location.href : ''
  }

  return useMemo(() => {
    let params: URLSearchParams
    try {
      params = new URL(currentUrl, window.location.origin).searchParams
    } catch {
      params = new URLSearchParams()
    }

    return {
      get: (key: string) => params.get(key),
      getAll: (key: string) => params.getAll(key),
      has: (key: string) => params.has(key),
      toString: () => params.toString(),
      forEach: (fn: (value: string, key: string) => void) => params.forEach(fn),
    }
  }, [currentUrl])
}

export function useParams<T extends Record<string, string> = Record<string, string>>(): T {
  const pathname = usePathname()
  return useCallback(() => ({} as T), [pathname])()
}
