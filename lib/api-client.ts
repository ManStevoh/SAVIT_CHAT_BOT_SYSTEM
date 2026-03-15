/**
 * API client for Laravel backend.
 * Set NEXT_PUBLIC_API_URL to your Laravel app URL (e.g. http://localhost:8000).
 * Use NEXT_PUBLIC_USE_MOCK_API=false to call Laravel instead of mock data.
 */

declare const process: { env: Record<string, string | undefined> }

const getBaseUrl = (): string => {
  return process.env.NEXT_PUBLIC_API_URL ?? ''
}

/** Whether to use mock responses (no real HTTP calls). Default true until Laravel is ready. */
export const useMockApi = (): boolean =>
  process.env.NEXT_PUBLIC_USE_MOCK_API !== 'false'

/** Full URL for an API path (e.g. /api/auth/login -> http://localhost:8000/api/auth/login). */
export function apiUrl(path: string): string {
  const base = getBaseUrl().replace(/\/$/, '')
  const p = path.startsWith('/') ? path : `/${path}`
  return base ? `${base}${p}` : p
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
    const message = (data as { message?: string })?.message ?? data?.errors ?? response.statusText
    throw new Error(typeof message === 'string' ? message : JSON.stringify(message))
  }

  return data as T
}
