'use client'

import { useState, useCallback, useMemo } from 'react'
import Link from 'next/link'
import { useRouter, useSearchParams } from 'next/navigation'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Spinner } from '@/components/ui/spinner'
import { login, type LoginCredentials } from '@/lib/api-actions'
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

  const [showPassword, setShowPassword] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  
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

  // Handle field change
  const handleFieldChange = (field: keyof LoginCredentials, value: string | boolean) => {
    setFormData((prev) => ({ ...prev, [field]: value }))
    setError(null) // Clear general error on change
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
        // Redirect to requested page, subscription (with plan), or by role
        const target =
          redirectTo ||
          subscribeRedirect ||
          (result.user.role === 'admin' ? '/admin' : '/dashboard')
        router.push(target)
      } else {
        setError(result.message || 'Invalid email or password')
      }
    } catch (err) {
      setError('An unexpected error occurred. Please try again.')
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

        {/* Error Alert */}
        {error && (
          <div className="mb-6 flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
            <AlertCircle className="h-4 w-4 shrink-0" />
            <span>{error}</span>
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
