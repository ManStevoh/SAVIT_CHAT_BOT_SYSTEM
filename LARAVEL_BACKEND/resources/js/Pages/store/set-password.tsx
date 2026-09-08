'use client'

import { FormEvent, useState } from 'react'
import { Head, router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Lock, CheckCircle2, ArrowRight } from 'lucide-react'
import { resolveStorefrontStyle } from '@/lib/theme-utils'

interface Props {
  slug: string
  company: {
    name: string
    storeSlug: string
    theme?: Record<string, any>
  }
  customer: {
    id: number
    name?: string
    email: string
    hasExistingPassword?: boolean
  }
}

export default function SetPasswordPage({ slug, company, customer }: Props) {
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault()
    setError('')

    if (password.length < 6) {
      setError('Password must be at least 6 characters.')
      return
    }

    if (password !== passwordConfirmation) {
      setError('Passwords do not match.')
      return
    }

    setSubmitting(true)

    const currentUrl = typeof window !== 'undefined' ? window.location.href : `/s/${slug}/account/set-password/${customer.id}`

    router.post(
      currentUrl,
      {
        password,
        password_confirmation: passwordConfirmation,
      },
      {
        onSuccess: () => {
          setSuccess(true)
        },
        onError: (errs) => {
          setError(Object.values(errs)[0] || 'Unable to update password. Link may have expired.')
        },
        onFinish: () => setSubmitting(false),
      }
    )
  }

  const style = resolveStorefrontStyle(company.theme)

  return (
    <div
      className="min-h-screen bg-slate-50/80 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100 flex flex-col justify-between"
      style={style}
    >
      <Head title={`Set Password - ${company.name}`} />

      {/* Top Header */}
      <header className="border-b border-slate-200/80 bg-white/90 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90 py-4 px-6 text-center">
        <a href={`/s/${slug}`} className="text-sm font-bold tracking-tight text-slate-900 dark:text-white hover:underline">
          {company.name}
        </a>
      </header>

      {/* Main Card */}
      <main className="mx-auto w-full max-w-md p-6">
        <div className="rounded-3xl border border-slate-200/80 bg-white p-8 shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none space-y-6">
          <div className="space-y-2 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900">
              <Lock className="h-6 w-6" />
            </div>
            <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-white">
              {customer.hasExistingPassword ? 'Reset Your Password' : 'Create Your Password'}
            </h1>
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Account: <strong className="text-slate-800 dark:text-slate-200">{customer.email}</strong>
            </p>
          </div>

          {success ? (
            <div className="rounded-2xl bg-emerald-50 border border-emerald-200 p-5 text-center space-y-3 dark:bg-emerald-950/40 dark:border-emerald-900">
              <CheckCircle2 className="h-8 w-8 text-emerald-600 mx-auto" />
              <p className="text-sm font-semibold text-emerald-900 dark:text-emerald-200">
                Password successfully saved!
              </p>
              <p className="text-xs text-emerald-700 dark:text-emerald-300">
                You are now signed in to {company.name}.
              </p>
              <Button
                type="button"
                onClick={() => (window.location.href = `/s/${slug}`)}
                className="w-full mt-2"
              >
                Go to Storefront <ArrowRight className="ml-2 h-4 w-4" />
              </Button>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="space-y-4">
              {error && (
                <div className="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-600 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                  {error}
                </div>
              )}

              <div className="space-y-1.5">
                <Label className="text-xs font-semibold">New Password</Label>
                <Input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="At least 6 characters"
                  required
                  autoFocus
                  className="rounded-2xl"
                />
              </div>

              <div className="space-y-1.5">
                <Label className="text-xs font-semibold">Confirm Password</Label>
                <Input
                  type="password"
                  value={passwordConfirmation}
                  onChange={(e) => setPasswordConfirmation(e.target.value)}
                  placeholder="Re-enter new password"
                  required
                  className="rounded-2xl"
                />
              </div>

              <Button type="submit" disabled={submitting} className="w-full rounded-2xl">
                {submitting ? 'Saving Password...' : 'Save Password & Sign In'}
              </Button>
            </form>
          )}
        </div>
      </main>

      <footer className="border-t border-slate-200/80 py-6 text-center text-xs text-slate-400 dark:border-slate-800">
        {company.theme?.footer_text || 'Powered by RelayIQ'}
      </footer>
    </div>
  )
}
