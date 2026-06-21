/**
 * API client for Laravel backend (Inertia same-origin).
 * Uses relative /api/* paths. Set VITE_USE_MOCK_API=true to use mock data in dev.
 */

const getBaseUrl = (): string => {
  const configured = import.meta.env.VITE_API_URL as string | undefined
  return configured?.replace(/\/$/, '') ?? ''
}

/** Use real API unless mock is explicitly enabled. */
export const useMockApi = (): boolean => {
  return import.meta.env.VITE_USE_MOCK_API === 'true'
}

/** Full URL for an API path (e.g. /api/auth/login -> http://localhost:8000/api/auth/login). */
export function apiUrl(path: string): string {
  const base = getBaseUrl().replace(/\/$/, '')
  const p = path.startsWith('/') ? path : `/${path}`
  return base ? `${base}${p}` : p
}

/**
 * Normalize Laravel public-disk URLs so `<img>` loads from NEXT_PUBLIC_API_URL.
 * Only rewrites paths under `/storage` (wrong host from APP_URL e.g. localhost is fixed).
 * Other absolute URLs (CDN, etc.) are left unchanged.
 */
export function resolveBackendMediaUrl(url: string | null | undefined): string | null {
  if (url == null || String(url).trim() === '') return null
  const raw = String(url).trim()
  const base = getBaseUrl().replace(/\/$/, '')

  let pathnameWithQuery: string
  try {
    if (raw.startsWith('http://') || raw.startsWith('https://')) {
      const p = new URL(raw)
      pathnameWithQuery = `${p.pathname}${p.search}`
    } else if (raw.startsWith('//')) {
      const p = new URL(`https:${raw}`)
      pathnameWithQuery = `${p.pathname}${p.search}`
    } else {
      pathnameWithQuery = raw.startsWith('/') ? raw : `/${raw}`
    }
  } catch {
    return null
  }

  const storageIdx = pathnameWithQuery.indexOf('/storage/')
  const storagePath =
    storageIdx !== -1
      ? pathnameWithQuery.slice(storageIdx)
      : pathnameWithQuery.startsWith('/storage')
        ? pathnameWithQuery
        : null

  if (storagePath) {
    if (!base) return storagePath
    return `${base}${storagePath.startsWith('/') ? storagePath : `/${storagePath}`}`
  }

  if (raw.startsWith('http://') || raw.startsWith('https://')) {
    return raw
  }
  if (raw.startsWith('//')) {
    if (typeof window !== 'undefined' && window.location?.protocol) {
      return `${window.location.protocol}${raw}`
    }
    return `https:${raw}`
  }

  if (!base) {
    return pathnameWithQuery
  }
  return `${base}${pathnameWithQuery.startsWith('/') ? pathnameWithQuery : `/${pathnameWithQuery}`}`
}

/** Get auth token for Laravel Sanctum (Bearer). Override if you store token elsewhere. */
export function getAuthToken(): string | null {
  if (typeof window === 'undefined') return null
  // Laravel Sanctum SPA: token often in cookie or localStorage
  return localStorage.getItem('auth_token') ?? sessionStorage.getItem('auth_token')
}

export interface ApiRequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: object | FormData
  headers?: Record<string, string>
}

/**
 * Call Laravel API. Uses JSON by default; send FormData for file uploads.
 * Credentials: 'include' so cookies (e.g. Sanctum) are sent.
 */
export async function apiRequest<T>(path: string, options: ApiRequestOptions = {}): Promise<T> {
  const { method = 'GET', body, headers: customHeaders = {} } = options
  const url = apiUrl(path)

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...customHeaders,
  }

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (csrf) {
    headers['X-CSRF-TOKEN'] = csrf
  }

  const token = getAuthToken()
  if (token) {
    headers['Authorization'] = `Bearer ${token}`
  }

  const isFormData = body instanceof FormData
  if (!isFormData) {
    headers['Content-Type'] = 'application/json'
  }

  const fetchOptions: RequestInit = {
    method,
    headers,
    credentials: 'include',
  }

  if (body !== undefined) {
    fetchOptions.body = isFormData ? (body as FormData) : JSON.stringify(body)
  }

  const response = await fetch(url, fetchOptions)
  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    const code = (data as { code?: string })?.code
    if (response.status === 403 && code === 'subscription_expired' && typeof window !== 'undefined') {
      window.location.href = '/dashboard/subscription?expired=1'
      return new Promise(() => {}) as T
    }
    const message = (data as { message?: string })?.message ?? data?.errors ?? response.statusText
    const err = new Error(typeof message === 'string' ? message : JSON.stringify(message)) as Error & { code?: string; responseData?: unknown }
    err.code = code
    err.responseData = data
    throw err
  }

  return data as T
}

/**
 * Download a file from the API (e.g. export). Uses auth token. Triggers browser download.
 */
export async function downloadFile(
  pathOrUrl: string,
  filename: string
): Promise<void> {
  const url = pathOrUrl.startsWith('http') ? pathOrUrl : apiUrl(pathOrUrl)
  const token = getAuthToken()
  const headers: Record<string, string> = {}
  if (token) headers['Authorization'] = `Bearer ${token}`
  const res = await fetch(url, { credentials: 'include', headers })
  if (!res.ok) {
    const text = await res.text()
    let msg: string
    try {
      const j = JSON.parse(text)
      msg = (j as { message?: string }).message ?? text
    } catch {
      msg = text || res.statusText
    }
    throw new Error(msg || 'Download failed')
  }
  const blob = await res.blob()
  const blobUrl = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = blobUrl
  a.download = filename || 'export'
  a.click()
  URL.revokeObjectURL(blobUrl)
}
