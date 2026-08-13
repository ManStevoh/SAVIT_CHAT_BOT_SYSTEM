'use client'

import { FormEvent, useState } from 'react'
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
}

export function StorefrontAuthModal({ open, onOpenChange, slug, companyName }: Props) {
  const [mode, setMode] = useState<'login' | 'register'>('login')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [errorMessage, setErrorMessage] = useState('')

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setErrorMessage('')
    setSubmitting(true)

    const endpoint = mode === 'register' ? `/s/${slug}/account/register` : `/s/${slug}/account/login`
    const payload = mode === 'register' ? { name, email, password } : { email, password }

    router.post(endpoint, payload, {
      onSuccess: () => {
        onOpenChange(false)
        setName('')
        setEmail('')
        setPassword('')
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
            {mode === 'login' ? 'Welcome Back' : 'Create Storefront Account'}
          </DialogTitle>
          <DialogDescription className="text-xs text-slate-500">
            {mode === 'login'
              ? 'Sign in with your Email & Password to manage orders, addresses, and fast checkout.'
              : 'Create an account to track orders and checkout without entering your phone number.'}
          </DialogDescription>
        </DialogHeader>

        {/* Tab Switcher */}
        <div className="grid grid-cols-2 rounded-2xl bg-slate-100 p-1 text-xs font-semibold dark:bg-slate-800">
          <button
            type="button"
            onClick={() => {
              setMode('login')
              setErrorMessage('')
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

        {errorMessage && (
          <div className="rounded-2xl bg-rose-50 p-3.5 text-xs font-medium text-rose-700 border border-rose-200/60 dark:bg-rose-950/40 dark:border-rose-900/50 dark:text-rose-300">
            {errorMessage}
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

          <div className="space-y-1.5">
            <Label htmlFor="auth-password" className="text-xs font-semibold">
              Password
            </Label>
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

          <Button
            type="submit"
            disabled={submitting}
            size="lg"
            className="w-full gap-2 rounded-2xl bg-slate-900 py-5 text-sm font-semibold shadow-md transition-all hover:bg-slate-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"
          >
            {submitting ? 'Please wait…' : mode === 'login' ? 'Sign In' : 'Create Account'}
            <ArrowRight className="h-4 w-4" />
          </Button>
        </form>
      </DialogContent>
    </Dialog>
  )
}
