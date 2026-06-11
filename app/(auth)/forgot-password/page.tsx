"use client"

import { useState } from "react"
import Link from "next/link"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Spinner } from "@/components/ui/spinner"
import { ArrowLeft, Mail } from "lucide-react"
// API: POST /api/auth/forgot-password — request password reset (api-actions.forgotPassword)
import { forgotPassword } from "@/lib/api-actions"

export default function ForgotPasswordPage() {
  const [isLoading, setIsLoading] = useState(false)
  const [isSubmitted, setIsSubmitted] = useState(false)
  // API error handling: display message from failed forgotPassword() response
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setError(null)
    setIsLoading(true)
    const email = (e.currentTarget.elements.namedItem("email") as HTMLInputElement).value.trim()
    const result = await forgotPassword({ email })
    setIsLoading(false)
    if (!result.success) {
      setError(result.message ?? "Request failed")
      return
    }
    setIsSubmitted(true)
  }

  if (isSubmitted) {
    return (
      <div className="w-full max-w-md">
        <div className="rounded-xl border border-border/60 bg-card p-8 shadow-premium text-center">
          <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
            <Mail className="h-6 w-6 text-primary" />
          </div>
          <h1 className="text-2xl font-bold text-foreground">Check your email</h1>
          <p className="mt-2 text-sm text-muted-foreground">
            {"We've sent a password reset link to your email address."}
          </p>
          <Button asChild className="mt-6 w-full" variant="outline">
            <Link href="/login">
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back to login
            </Link>
          </Button>
        </div>
      </div>
    )
  }

  return (
    <div className="w-full max-w-md">
      <div className="rounded-xl border border-border/60 bg-card p-8 shadow-premium">
        <div className="text-center mb-8">
          <h1 className="text-2xl font-bold text-foreground">Forgot password?</h1>
          <p className="mt-2 text-sm text-muted-foreground">
            {"No worries, we'll send you reset instructions."}
          </p>
        </div>

        <form onSubmit={handleSubmit}>
          {error && (
            <div className="mb-4 rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-2 text-sm text-destructive">
              {error}
            </div>
          )}
          <FieldGroup>
            <Field>
              <FieldLabel htmlFor="email">Email</FieldLabel>
              <Input
                id="email"
                name="email"
                type="email"
                placeholder="you@company.com"
                required
              />
            </Field>

            <Button type="submit" className="w-full" disabled={isLoading}>
              {isLoading ? <Spinner className="h-4 w-4" /> : "Send Reset Link"}
            </Button>
          </FieldGroup>
        </form>

        <p className="mt-6 text-center">
          <Link
            href="/login"
            className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to login
          </Link>
        </p>
      </div>
    </div>
  )
}
