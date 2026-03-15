'use client'

import { useState, useCallback, useMemo } from 'react'
import Link from 'next/link'
import { useRouter, useSearchParams } from 'next/navigation'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Spinner } from '@/components/ui/spinner'
import { login, resendVerificationEmail, type LoginCredentials } from '@/lib/api-actions'
import { setAuthCookie } from '@/lib/auth-cookie'
import { Eye, EyeOff, AlertCircle } from 'lucide-react'

const SAFE_REDIRECT_PREFIXES = ['/admin', '/dashboard']

export default function LoginPage() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const redirectTo = useMemo(() => {
    const r = searchParams.get('redirect')
    if (!r || !r.startsWith('/')) return null
    if (SAFE_REDIRECT_PREFIXES.some((p) => r === p || r.startsWith(p + '/'))) return r
    return null
  }, [searchParams])

  const planId = searchParams.get('plan')
  const subscribeRedirect = planId ? `/dashboard/subscription?subscribe=${planId}` : null
  const verifiedParam = searchParams.get('verified') === '1'
  const registeredParam = searchParams.get('registered') === '1'

  const [showPassword, setShowPassword] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [emailNotVerified, setEmailNotVerified] = useState(false)
  const [resendSent, setResendSent] = useState(false)
  const [resendLoading, setResendLoading] = useState(false)
  
  // Form state
  const [formData, setFormData] = useState<LoginCredentials>({
    email: '',
    password: '',
    rememberMe: false,
  })
  
  // Form validation errors
  const [formErrors, setFormErrors] = useState<Record<string, string>>({})

  // Validate form
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

  // Handle field change
  const handleFieldChange = (field: keyof LoginCredentials, value: string | boolean) => {
    setFormData((prev) => ({ ...prev, [field]: value }))
    setError(null)
    setEmailNotVerified(false)
    setResendSent(false)
    if (formErrors[field]) {
      setFormErrors((prev) => ({ ...prev, [field]: '' }))
    }
  }

  // Handle form submission — uses api-actions.login → POST /api/auth/login
  const handleSubmit = useCallback(async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!validateForm()) return

    setIsLoading(true)
    setError(null)

    try {
      const result = await login(formData)

      if (result.success && result.user) {
        setEmailNotVerified(false)
        if (result.token) {
          if (formData.rememberMe) {
            localStorage.setItem('auth_token', result.token)
            localStorage.setItem('auth_user', JSON.stringify(result.user))
          } else {
            sessionStorage.setItem('auth_token', result.token)
            sessionStorage.setItem('auth_user', JSON.stringify(result.user))
          }
          setAuthCookie(result.user.role, formData.rememberMe)
        }
        const target =
          redirectTo ||
          subscribeRedirect ||
          (result.user.role === 'admin' ? '/admin' : '/dashboard')
        router.push(target)
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
  }, [formData, redirectTo, subscribeRedirect, router])

  return (
    <div className="w-full max-w-md">
      <div className="rounded-2xl border border-border/50 bg-card p-8 shadow-xl">
        <div className="mb-8 text-center">
          <h1 className="text-2xl font-bold text-foreground">Welcome back</h1>
          <p className="mt-2 text-sm text-muted-foreground">
            Sign in to your account to continue
          </p>
        </div>

        {/* Email verified success (from redirect after verification) */}
        {verifiedParam && (
          <div className="mb-6 rounded-lg border border-green-500/50 bg-green-500/10 p-3 text-sm text-green-700 dark:text-green-400">
            Email verified successfully. You can now sign in.
          </div>
        )}

        {/* Post-registration: must sign in (no token issued on register) */}
        {registeredParam && !verifiedParam && (
          <div className="mb-6 rounded-lg border border-green-500/50 bg-green-500/10 p-3 text-sm text-green-700 dark:text-green-400">
            Account created. Please check your email to verify your account, then sign in below.
          </div>
        )}

        {/* Error Alert */}
        {error && (
          <div className="mb-6 flex flex-col gap-2 rounded-lg border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
            <div className="flex items-center gap-2">
              <AlertCircle className="h-4 w-4 shrink-0" />
              <span>{error}</span>
            </div>
            {emailNotVerified && (
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="w-fit border-destructive/50 text-destructive hover:bg-destructive/10"
                onClick={handleResendVerification}
                disabled={resendLoading || !formData.email.trim()}
              >
                {resendLoading ? 'Sending…' : 'Resend verification email'}
              </Button>
            )}
            {resendSent && (
              <p className="text-xs text-green-600 dark:text-green-400">New verification link sent. Check your inbox.</p>
            )}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Email Field */}
          <div className="space-y-2">
            <Label htmlFor="email" className="text-sm font-medium text-foreground">
              Email
            </Label>
            <Input
              id="email"
              type="email"
              value={formData.email}
              onChange={(e) => handleFieldChange('email', e.target.value)}
              placeholder="you@company.com"
              autoComplete="email"
              disabled={isLoading}
              className={formErrors.email ? 'border-destructive focus-visible:ring-destructive' : 'border-border/50'}
            />
            {formErrors.email && (
              <p className="text-xs text-destructive">{formErrors.email}</p>
            )}
          </div>

          {/* Password Field */}
          <div className="space-y-2">
            <Label htmlFor="password" className="text-sm font-medium text-foreground">
              Password
            </Label>
            <div className="relative">
              <Input
                id="password"
                type={showPassword ? 'text' : 'password'}
                value={formData.password}
                onChange={(e) => handleFieldChange('password', e.target.value)}
                placeholder="Enter your password"
                autoComplete="current-password"
                disabled={isLoading}
                className={formErrors.password ? 'border-destructive focus-visible:ring-destructive pr-10' : 'border-border/50 pr-10'}
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                tabIndex={-1}
              >
                {showPassword ? (
                  <EyeOff className="h-4 w-4" />
                ) : (
                  <Eye className="h-4 w-4" />
                )}
              </button>
            </div>
            {formErrors.password && (
              <p className="text-xs text-destructive">{formErrors.password}</p>
            )}
          </div>

          {/* Remember Me & Forgot Password */}
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Checkbox
                id="remember"
                checked={formData.rememberMe}
                onCheckedChange={(checked) =>
                  handleFieldChange('rememberMe', checked === true)
                }
                disabled={isLoading}
              />
              <label
                htmlFor="remember"
                className="cursor-pointer text-sm text-muted-foreground"
              >
                Remember me
              </label>
            </div>
            <Link
              href="/forgot-password"
              className="text-sm text-primary hover:text-primary/80"
            >
              Forgot password?
            </Link>
          </div>

          {/* Submit Button */}
          <Button type="submit" className="w-full" disabled={isLoading}>
            {isLoading ? <Spinner className="h-4 w-4" /> : 'Sign In'}
          </Button>
        </form>

        <p className="mt-6 text-center text-sm text-muted-foreground">
          {"Don't have an account? "}
          <Link href="/register" className="text-primary hover:text-primary/80">
            Sign up
          </Link>
        </p>
      </div>
    </div>
  )
}
