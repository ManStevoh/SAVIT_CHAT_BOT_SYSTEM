"use client"

import { useState } from "react"
import Link from "next/link"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Spinner } from "@/components/ui/spinner"
import { ArrowLeft, Mail } from "lucide-react"
import { forgotPassword } from "@/lib/api-actions"
import { LandoAuthHeader, LandoAuthError, landoBtnClass, landoInputClass } from "@/components/lando/auth-form"

export default function ForgotPasswordPage() {
  const [isLoading, setIsLoading] = useState(false)
  const [isSubmitted, setIsSubmitted] = useState(false)
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
      <div className="w-full text-center">
        <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-[#2563eb]/10">
          <Mail className="h-7 w-7 text-[#2563eb]" />
        </div>
        <LandoAuthHeader title="Check your email" description="We've sent a password reset link to your email address." className="text-center" />
        <Button asChild className={`${landoBtnClass} mt-4 border border-black bg-white text-black hover:bg-gray-50`} variant="outline">
          <Link href="/login">
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to login
          </Link>
        </Button>
      </div>
    )
  }

  return (
    <div className="w-full">
      <LandoAuthHeader title="Forgot password?" description="No worries, we'll send you reset instructions." />

      <form onSubmit={handleSubmit} className="space-y-4">
        {error && <LandoAuthError>{error}</LandoAuthError>}

        <div className="space-y-2">
          <Label htmlFor="email" className="text-sm font-medium text-black">Email</Label>
          <Input id="email" name="email" type="email" placeholder="you@company.com" required className={landoInputClass} />
        </div>

        <Button type="submit" className={landoBtnClass} disabled={isLoading}>
          {isLoading ? <Spinner className="h-4 w-4" /> : "Send reset link"}
        </Button>
      </form>

      <p className="mt-6 text-center">
        <Link href="/login" className="inline-flex items-center text-sm text-gray-600 hover:text-black">
          <ArrowLeft className="mr-2 h-4 w-4" />
          Back to login
        </Link>
      </p>
    </div>
  )
}
