import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'
import { AUTH_COOKIE_NAMES } from './lib/auth-cookie'

const LOGIN_PATH = '/login'

export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl

  const isAdmin = pathname === '/admin' || pathname.startsWith('/admin/')
  const isDashboard = pathname === '/dashboard' || pathname.startsWith('/dashboard/')

  if (!isAdmin && !isDashboard) {
    return NextResponse.next()
  }

  const token = request.cookies.get(AUTH_COOKIE_NAMES.token)?.value
  const role = request.cookies.get(AUTH_COOKIE_NAMES.role)?.value

  if (!token) {
    const loginUrl = new URL(LOGIN_PATH, request.url)
    loginUrl.searchParams.set('redirect', pathname)
    return NextResponse.redirect(loginUrl)
  }

  if (isAdmin && role !== 'admin') {
    return NextResponse.redirect(new URL('/dashboard', request.url))
  }

  return NextResponse.next()
}

export const config = {
  matcher: ['/admin', '/admin/:path*', '/dashboard', '/dashboard/:path*'],
}
