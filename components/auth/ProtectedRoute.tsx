'use client'

import { useEffect, useState } from 'react'
import { useRouter, usePathname } from 'next/navigation'
import { Spinner } from '@/components/ui/spinner'

const AUTH_TOKEN_KEY = 'auth_token'

function getStoredToken(): string | null {
  if (typeof window === 'undefined') return null
  return localStorage.getItem(AUTH_TOKEN_KEY) ?? sessionStorage.getItem(AUTH_TOKEN_KEY)
}

export interface ProtectedRouteProps {
  children: React.ReactNode
  /** Require admin role (redirect to /dashboard if logged in but not admin). */
  requireAdmin?: boolean
}

/**
 * Wraps protected content and redirects to /login if there is no auth token.
 * Use in admin and dashboard layouts so /admin and /dashboard are not accessible without login.
 */
export function ProtectedRoute({ children, requireAdmin = false }: ProtectedRouteProps) {
  const router = useRouter()
  const pathname = usePathname()
  const [mounted, setMounted] = useState(false)
  const [allowed, setAllowed] = useState(false)

  useEffect(() => {
    setMounted(true)
  }, [])

  useEffect(() => {
    if (!mounted) return

    const token = getStoredToken()
    if (!token) {
      const loginUrl = `/login?redirect=${encodeURIComponent(pathname ?? '/admin')}`
      router.replace(loginUrl)
      return
    }

    // Optional: require admin role (e.g. from stored user)
    if (requireAdmin) {
      try {
        const raw = localStorage.getItem('auth_user') ?? sessionStorage.getItem('auth_user')
        const user = raw ? JSON.parse(raw) : null
        if (user && user.role !== 'admin') {
          router.replace('/dashboard')
          return
        }
      } catch {
        // No stored user or invalid JSON; allow and let API reject if needed
      }
    }

    setAllowed(true)
  }, [mounted, pathname, requireAdmin, router])

  if (!mounted || !allowed) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <Spinner className="h-8 w-8 text-primary" />
      </div>
    )
  }

  return <>{children}</>
}
