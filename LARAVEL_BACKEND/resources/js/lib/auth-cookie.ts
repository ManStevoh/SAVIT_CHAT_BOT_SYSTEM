/**
 * Auth cookies for Next.js middleware (server/edge).
 * Set on login, cleared on logout. Middleware uses these to protect /admin and /dashboard.
 */

const COOKIE_TOKEN = 'essem_token'
const COOKIE_ROLE = 'essem_role'
const COOKIE_PATH = '/'
const REMEMBER_ME_DAYS = 7

function cookieOptions(rememberMe: boolean): string {
  const maxAge = rememberMe ? REMEMBER_ME_DAYS * 24 * 60 * 60 : undefined
  const parts = [`path=${COOKIE_PATH}`, 'SameSite=Lax']
  if (maxAge !== undefined) parts.push(`max-age=${maxAge}`)
  return parts.join('; ')
}

/** Set auth cookies after login so middleware can protect routes. Call from client only. */
export function setAuthCookie(role: string, rememberMe: boolean): void {
  if (typeof document === 'undefined') return
  document.cookie = `${COOKIE_TOKEN}=1; ${cookieOptions(rememberMe)}`
  document.cookie = `${COOKIE_ROLE}=${encodeURIComponent(role)}; ${cookieOptions(rememberMe)}`
}

/** Clear auth cookies on logout. Call from client only. */
export function clearAuthCookie(): void {
  if (typeof document === 'undefined') return
  const expire = 'max-age=0'
  document.cookie = `${COOKIE_TOKEN}=; path=${COOKIE_PATH}; ${expire}`
  document.cookie = `${COOKIE_ROLE}=; path=${COOKIE_PATH}; ${expire}`
}

/** Cookie names for middleware (so we don't duplicate strings). */
export const AUTH_COOKIE_NAMES = {
  token: COOKIE_TOKEN,
  role: COOKIE_ROLE,
} as const
