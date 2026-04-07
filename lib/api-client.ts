/**
 * API client for Laravel backend.
 * Set NEXT_PUBLIC_API_URL to your Laravel app URL (e.g. http://localhost:8000).
 * Use NEXT_PUBLIC_USE_MOCK_API=false to call Laravel instead of mock data.
 */

declare const process: { env: Record<string, string | undefined> }

const getBaseUrl = (): string => {
  return process.env.NEXT_PUBLIC_API_URL ?? ''
}

/** Use real API when API URL is set, unless mock is explicitly enabled. */
export const useMockApi = (): boolean => {
  const url = process.env.NEXT_PUBLIC_API_URL ?? ''
  const useMock = process.env.NEXT_PUBLIC_USE_MOCK_API
  if (url && useMock !== 'true') return false
  return useMock !== 'false'
}

/** Full URL for an API path (e.g. /api/auth/login -> http://localhost:8000/api/auth/login). */
export function apiUrl(path: string): string {
  const base = getBaseUrl().replace(/\/$/, '')
  const p = path.startsWith('/') ? path : `/${path}`
  return base ? `${base}${p}` : p
}

/**
 * Laravel Storage::url() often returns a path like `/storage/...`. The dashboard is on another
 * origin (e.g. Vercel) so `<img src="/storage/...">` loads from the wrong host. Prefix the API origin.
 */
export function resolveBackendMediaUrl(url: string | null | undefined): string | null {
  if (url == null || String(url).trim() === '') return null
  const u = String(url).trim()
  if (u.startsWith('http://') || u.startsWith('https://')) return u
  if (u.startsWith('//')) {
    if (typeof window !== 'undefined' && window.location?.protocol) {
      return `${window.location.protocol}${u}`
    }
    return `https:${u}`
  }
  const base = getBaseUrl().replace(/\/$/, '')
  if (!base) return u
  const path = u.startsWith('/') ? u : `/${u}`
  return `${base}${path}`
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
    ...customHeaders,
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
