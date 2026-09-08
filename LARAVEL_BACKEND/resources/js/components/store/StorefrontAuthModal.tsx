'use client'

import { FormEvent, useEffect, useState } from 'react'
import { router } from '@inertiajs/react'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Lock, Mail, User, ArrowRight } from 'lucide-react'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  slug: string
  companyName: string
  termsUrl?: string
  prefillEmail?: string
  initialMode?: 'login' | 'register' | 'forgot'
}

export function StorefrontAuthModal({ open, onOpenChange, slug, companyName, termsUrl, prefillEmail = '', initialMode = 'login' }: Props) {
  const [mode, setMode] = useState<'login' | 'register' | 'forgot'>(initialMode)
  const [name, setName] = useState('')
  const [email, setEmail] = useState(prefillEmail)
  const [password, setPassword] = useState('')
  const [acceptTerms, setAcceptTerms] = useState(false)
  const [marketingConsent, setMarketingConsent] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [errorMessage, setErrorMessage] = useState('')
  const [successMessage, setSuccessMessage] = useState('')
  const legalUrl = termsUrl || `/s/${slug}/terms`

  useEffect(() => {
    if (prefillEmail) {
      setEmail(prefillEmail)
    }
  }, [prefillEmail])

  useEffect(() => {
    if (open) {
      setMode(initialMode)
      setErrorMessage('')
      setSuccessMessage('')
      if (prefillEmail) setEmail(prefillEmail)
    }
  }, [open, initialMode, prefillEmail])

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setErrorMessage('')
    setSubmitting(true)

    const endpoint =
      mode === 'register'
        ? `/s/${slug}/account/register`
        : mode === 'forgot'
        ? `/s/${slug}/account/forgot-password`
        : `/s/${slug}/account/login`

    const payload =
      mode === 'register'
        ? { name, email, password, acceptTerms, marketingConsent }
        : mode === 'forgot'
        ? { email }
        : { email, password }

    router.post(endpoint, payload, {
      onSuccess: () => {
        if (mode === 'forgot') {
          setSuccessMessage('A link to set or reset your password has been sent to your email.')
          setPassword('')
        } else {
          onOpenChange(false)
          setName('')
          setEmail('')
          setPassword('')
          setAcceptTerms(false)
          setMarketingConsent(false)
        }
      },
      onError: (errs) => {
        const firstError = Object.values(errs)[0]
        setErrorMessage(typeof firstError === 'string' ? firstError : 'Authentication failed. Please check your credentials.')
      },
      onFinish: () => setSubmitting(false),
    })
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md rounded-3xl p-7">
        <DialogHeader className="text-center sm:text-left">
          <span className="text-xs font-bold uppercase tracking-wider text-slate-400">{companyName} Customer Account</span>
          <DialogTitle className="text-2xl font-bold tracking-tight">
            {mode === 'login' ? 'Welcome Back' : mode === 'forgot' ? 'Set or Reset Password' : 'Create Storefront Account'}
          </DialogTitle>
          <DialogDescription className="text-xs text-slate-500">
            {mode === 'login'
              ? 'Sign in with your Email & Password to manage orders, addresses, and fast checkout.'
              : mode === 'forgot'
              ? 'Enter your email to receive a secure link to create or reset your password.'
              : 'Create an account to track orders and manage your saved details.'}
          </DialogDescription>
        </DialogHeader>

        {/* Tab Switcher */}
        {mode !== 'forgot' ? (
          <div className="grid grid-cols-2 rounded-2xl bg-slate-100 p-1 text-xs font-semibold dark:bg-slate-800">
            <button
              type="button"
              onClick={() => {
                setMode('login')
                setErrorMessage('')
                setSuccessMessage('')
              }}
              className={`rounded-xl py-2 transition-all ${
                mode === 'login'
                  ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-900 dark:text-white'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
              }`}
            >
              Sign In
            </button>
            <button
              type="button"
              onClick={() => {
                setMode('register')
                setErrorMessage('')
                setSuccessMessage('')
              }}
              className={`rounded-xl py-2 transition-all ${
                mode === 'register'
                  ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-900 dark:text-white'
                  : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
              }`}
            >
              Create Account
            </button>
          </div>
        ) : (
          <div className="flex items-center justify-between text-xs">
            <button
              type="button"
              onClick={() => {
                setMode('login')
                setErrorMessage('')
                setSuccessMessage('')
              }}
              className="font-semibold text-primary hover:underline"
            >
              ← Back to Sign In
            </button>
          </div>
        )}

        {errorMessage && (
          <div className="rounded-2xl bg-rose-50 p-3.5 text-xs font-medium text-rose-700 border border-rose-200/60 dark:bg-rose-950/40 dark:border-rose-900/50 dark:text-rose-300">
            {errorMessage}
          </div>
        )}

        {successMessage && (
          <div className="rounded-2xl bg-emerald-50 p-3.5 text-xs font-medium text-emerald-700 border border-emerald-200/60 dark:bg-emerald-950/40 dark:border-emerald-900/50 dark:text-emerald-300">
            {successMessage}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          {mode === 'register' && (
            <div className="space-y-1.5">
              <Label htmlFor="auth-name" className="text-xs font-semibold">
                Full Name
              </Label>
              <div className="relative">
                <User className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                <Input
                  id="auth-name"
                  type="text"
                  required
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Jane Doe"
                  className="pl-10 rounded-xl"
                />
              </div>
            </div>
          )}

          <div className="space-y-1.5">
            <Label htmlFor="auth-email" className="text-xs font-semibold">
              Email Address
            </Label>
            <div className="relative">
              <Mail className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
              <Input
                id="auth-email"
                type="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="you@example.com"
                className="pl-10 rounded-xl"
              />
            </div>
          </div>

          {mode !== 'forgot' && (
            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <Label htmlFor="auth-password" className="text-xs font-semibold">
                  Password
                </Label>
                {mode === 'login' && (
                  <button
                    type="button"
                    onClick={() => {
                      setMode('forgot')
                      setErrorMessage('')
                      setSuccessMessage('')
                    }}
                    className="text-[11px] font-semibold text-slate-500 hover:text-slate-900 hover:underline dark:text-slate-400 dark:hover:text-white"
                  >
                    Forgot password?
                  </button>
                )}
              </div>
              <div className="relative">
                <Lock className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                <Input
                  id="auth-password"
                  type="password"
                  required
                  minLength={6}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  className="pl-10 rounded-xl"
                />
              </div>
            </div>
          )}

          {mode === 'register' && (
            <div className="space-y-3 rounded-2xl border border-slate-200/80 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/50">
              <label className="flex items-start gap-2.5 text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                <input
                  type="checkbox"
                  required
                  checked={acceptTerms}
                  onChange={(e) => setAcceptTerms(e.target.checked)}
                  className="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300"
                />
                <span>
                  I agree to {companyName}&apos;s{' '}
                  <a href={legalUrl} target="_blank" rel="noreferrer" className="font-semibold underline underline-offset-2">
                    Terms and Conditions
                  </a>
                  .
                </span>
              </label>
              <label className="flex items-start gap-2.5 text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                <input
                  type="checkbox"
                  checked={marketingConsent}
                  onChange={(e) => setMarketingConsent(e.target.checked)}
                  className="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300"
                />
                <span>Send me offers and updates from {companyName} (optional — you can say no)</span>
              </label>
            </div>
          )}

          <Button
            type="submit"
            disabled={submitting}
            size="lg"
            className="w-full gap-2 rounded-2xl bg-slate-900 py-5 text-sm font-semibold shadow-md transition-all hover:bg-slate-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"
          >
            {submitting ? 'Please wait…' : mode === 'login' ? 'Sign In' : mode === 'forgot' ? 'Send Password Link' : 'Create Account'}
            <ArrowRight className="h-4 w-4" />
          </Button>

          {mode === 'login' && (
            <div className="pt-1 text-center">
              <button
                type="button"
                onClick={() => {
                  setMode('forgot')
                  setErrorMessage('')
                  setSuccessMessage('')
                }}
                className="text-xs font-medium text-slate-500 hover:text-slate-900 hover:underline dark:text-slate-400 dark:hover:text-white"
              >
                Forgot your password? Click here to set or reset it.
              </button>
            </div>
          )}
        </form>
      </DialogContent>
    </Dialog>
  )
}
