'use client'

import { Suspense, useState, useCallback, useMemo } from 'react'
import Link from 'next/link'
import { useRouter, useSearchParams } from 'next/navigation'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Spinner } from '@/components/ui/spinner'
import { login, resendVerificationEmail, type LoginCredentials } from '@/lib/api-actions'
import { setAuthCookie } from '@/lib/auth-cookie'
import { useAppBranding } from '@/components/providers/AppBrandingProvider'
import { Eye, EyeOff, AlertCircle } from 'lucide-react'
import {
  LandoAuthHeader,
  LandoAuthError,
  LandoAuthSuccess,
  landoBtnClass,
  landoInputClass,
} from '@/components/lando/auth-form'

const SAFE_REDIRECT_PREFIXES = ['/admin', '/dashboard']

function LoginPageContent() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const branding = useAppBranding()
  const redirectTo = useMemo(() => {
    const r = searchParams.get('redirect')
    if (!r || !r.startsWith('/')) return null
    if (SAFE_REDIRECT_PREFIXES.some((p) => r === p || r.startsWith(p + '/'))) return r
    return null
  }, [searchParams])

  const planId = searchParams.get('plan')
  const forcePay = searchParams.get('pay') === '1'
  const trialParam = searchParams.get('trial') === '1'
  const registeredParam = searchParams.get('registered') === '1'
  const verifiedParam = searchParams.get('verified') === '1'
  const registerHref = planId ? `/register?plan=${encodeURIComponent(planId)}` : '/register'

  const resolvePostLoginPath = (role: string): string => {
    if (redirectTo) return redirectTo
    if (typeof window !== 'undefined') {
      const stored = sessionStorage.getItem('post_login_path')
      if (stored && stored.startsWith('/')) {
        sessionStorage.removeItem('post_login_path')
        return stored
      }
    }
    // After signup with a trial: welcome dashboard (do not force payment).
    if (registeredParam && trialParam && !forcePay) {
      return '/dashboard?trial_started=1'
    }
    // Existing user clicked a plan on Pricing, or signup for a no-trial paid plan.
    if (planId && (forcePay || !registeredParam)) {
      return `/dashboard/subscription?subscribe=${encodeURIComponent(planId)}`
    }
    if (registeredParam && planId) {
      return '/dashboard?trial_started=1'
    }
    return role === 'admin' ? '/admin' : '/dashboard'
  }

  const [showPassword, setShowPassword] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [emailNotVerified, setEmailNotVerified] = useState(false)
  const [resendSent, setResendSent] = useState(false)
  const [resendLoading, setResendLoading] = useState(false)

  const [formData, setFormData] = useState<LoginCredentials>({
    email: '',
    password: '',
    rememberMe: false,
  })

  const [formErrors, setFormErrors] = useState<Record<string, string>>({})

  const validateForm = (): boolean => {
    const errors: Record<string, string> = {}

    if (!formData.email.trim()) {
      errors.email = 'Email is required'
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      errors.email = 'Please enter a valid email address'
    }

    if (!formData.password) {
      errors.password = 'Password is required'
    } else if (formData.password.length < 8) {
      errors.password = 'Password must be at least 8 characters'
    }

    setFormErrors(errors)
    return Object.keys(errors).length === 0
  }

  const handleResendVerification = useCallback(async () => {
    if (!formData.email.trim()) return
    setResendLoading(true)
    setResendSent(false)
    try {
      const result = await resendVerificationEmail(formData.email.trim())
      if (result.success) {
        setResendSent(true)
        setError(null)
      } else {
        setError(result.message || 'Failed to resend verification email.')
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to resend.')
    } finally {
      setResendLoading(false)
    }
  }, [formData.email])

  const handleFieldChange = (field: keyof LoginCredentials, value: string | boolean) => {
    setFormData((prev) => ({ ...prev, [field]: value }))
    setError(null)
    setEmailNotVerified(false)
    setResendSent(false)
    if (formErrors[field]) {
      setFormErrors((prev) => ({ ...prev, [field]: '' }))
    }
  }

  const handleSubmit = useCallback(async (e: React.FormEvent) => {
    e.preventDefault()

    if (!validateForm()) return

    setIsLoading(true)
    setError(null)

    try {
      const result = await login(formData)

      if (result.success && result.user) {
        setEmailNotVerified(false)

        const token = result.token ?? '1'

        if (formData.rememberMe) {
          localStorage.setItem('auth_token', token)
          localStorage.setItem('auth_user', JSON.stringify(result.user))
        } else {
          sessionStorage.setItem('auth_token', token)
          sessionStorage.setItem('auth_user', JSON.stringify(result.user))
        }

        setAuthCookie(result.user.role, !!formData.rememberMe)
        router.push(resolvePostLoginPath(result.user.role))
      } else {
        const code = (result as { code?: string }).code
        setEmailNotVerified(code === 'email_not_verified')
        setError(result.message || 'Invalid email or password')
      }
    } catch (err) {
      const e = err as Error & { code?: string }
      setEmailNotVerified(e?.code === 'email_not_verified')
      setError(e?.message || 'An unexpected error occurred. Please try again.')
      console.error('Login error:', err)
    } finally {
      setIsLoading(false)
    }
  }, [formData, redirectTo, planId, forcePay, trialParam, registeredParam, router])

  return (
    <div className="w-full">
      <LandoAuthHeader title="Welcome back" description="Sign in to your account to continue" />

      {verifiedParam && (
        <LandoAuthSuccess>Email verified successfully. You can now sign in.</LandoAuthSuccess>
      )}

      {registeredParam && !verifiedParam && (
        <LandoAuthSuccess>
          {branding.requireEmailVerification
            ? 'Account created. Please check your email to verify your account, then sign in below.'
            : trialParam
              ? 'Account created — your free trial has started. Sign in to open your dashboard.'
              : 'Account created successfully. You can sign in below.'}
        </LandoAuthSuccess>
      )}

      {error && (
        <LandoAuthError>
          <div className="flex items-center gap-2">
            <AlertCircle className="h-4 w-4 shrink-0" />
            <span>{error}</span>
          </div>
          {emailNotVerified && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="mt-2 w-fit border-red-300 text-red-700 hover:bg-red-100"
              onClick={handleResendVerification}
              disabled={resendLoading || !formData.email.trim()}
            >
              {resendLoading ? 'Sending…' : 'Resend verification email'}
            </Button>
          )}
          {resendSent && <p className="mt-2 text-xs text-green-700">New verification link sent. Check your inbox.</p>}
        </LandoAuthError>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="email" className="text-sm font-medium text-black">Email</Label>
          <Input
            id="email"
            type="email"
            value={formData.email}
            onChange={(e) => handleFieldChange('email', e.target.value)}
            placeholder="you@company.com"
            autoComplete="email"
            disabled={isLoading}
            className={formErrors.email ? `${landoInputClass} border-red-400` : landoInputClass}
          />
          {formErrors.email && <p className="text-xs text-red-600">{formErrors.email}</p>}
        </div>

        <div className="space-y-2">
          <Label htmlFor="password" className="text-sm font-medium text-black">Password</Label>
          <div className="relative">
            <Input
              id="password"
              type={showPassword ? 'text' : 'password'}
              value={formData.password}
              onChange={(e) => handleFieldChange('password', e.target.value)}
              placeholder="Enter your password"
              autoComplete="current-password"
              disabled={isLoading}
              className={formErrors.password ? `${landoInputClass} pr-10 border-red-400` : `${landoInputClass} pr-10`}
            />
            <button
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-black"
              tabIndex={-1}
            >
              {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </button>
          </div>
          {formErrors.password && <p className="text-xs text-red-600">{formErrors.password}</p>}
        </div>

        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Checkbox
              id="remember"
              checked={formData.rememberMe}
              onCheckedChange={(checked) => handleFieldChange('rememberMe', checked === true)}
              disabled={isLoading}
            />
            <label htmlFor="remember" className="cursor-pointer text-sm text-gray-600">Remember me</label>
          </div>
          <Link href="/forgot-password" className="text-sm font-medium text-[#2563eb] hover:text-[#1d4ed8]">
            Forgot password?
          </Link>
        </div>

        <Button type="submit" className={landoBtnClass} disabled={isLoading}>
          {isLoading ? <Spinner className="h-4 w-4" /> : 'Sign in'}
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-gray-600">
        {"Don't have an account? "}
        <Link href={registerHref} className="font-medium text-[#2563eb] hover:text-[#1d4ed8]">Sign up</Link>
      </p>
    </div>
  )
}

export default function LoginPage() {
  return (
    <Suspense
      fallback={
        <div className="flex items-center justify-center py-12">
          <Spinner className="h-6 w-6" />
        </div>
      }
    >
      <LoginPageContent />
    </Suspense>
  )
}
