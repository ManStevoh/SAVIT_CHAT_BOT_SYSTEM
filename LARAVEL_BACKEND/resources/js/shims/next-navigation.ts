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
  const { url } = usePage()
  try {
    return new URL(url, window.location.origin).pathname
  } catch {
    return url
  }
}

export function useSearchParams() {
  const { url } = usePage()

  return useMemo(() => {
    let params: URLSearchParams
    try {
      params = new URL(url, window.location.origin).searchParams
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
  }, [url])
}

export function useParams<T extends Record<string, string> = Record<string, string>>(): T {
  const pathname = usePathname()
  return useCallback(() => ({} as T), [pathname])()
}
