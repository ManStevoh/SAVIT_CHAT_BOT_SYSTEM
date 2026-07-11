"use client"

import { Suspense, useState } from "react"
import Link from "next/link"
import { useRouter, useSearchParams } from "next/navigation"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Spinner } from "@/components/ui/spinner"
import { Eye, EyeOff, CheckCircle } from "lucide-react"
import { resetPassword } from "@/lib/api-actions"
import { LandoAuthHeader, LandoAuthError, landoBtnClass, landoInputClass } from "@/components/lando/auth-form"

function ResetPasswordForm() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const token = searchParams.get("token") ?? ""
  const [showPassword, setShowPassword] = useState(false)
  const [showConfirmPassword, setShowConfirmPassword] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [isSuccess, setIsSuccess] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setError(null)
    const email = (e.currentTarget.elements.namedItem("email") as HTMLInputElement)?.value ?? ""
    const password = (e.currentTarget.elements.namedItem("password") as HTMLInputElement).value
    const confirmPassword = (e.currentTarget.elements.namedItem("confirmPassword") as HTMLInputElement).value
    if (password !== confirmPassword) {
      setError("Passwords do not match")
      return
    }
    if (!token) {
      setError("Invalid or missing reset token. Please use the link from your email.")
      return
    }
    if (!email) {
      setError("Email is required")
      return
    }
    setIsLoading(true)
    const result = await resetPassword({ token, email, password, confirmPassword })
    setIsLoading(false)
    if (!result.success) {
      setError(result.message ?? "Reset failed")
      return
    }
    setIsSuccess(true)
    setTimeout(() => router.push("/login"), 2000)
  }

  if (isSuccess) {
    return (
      <div className="w-full text-center">
        <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-100">
          <CheckCircle className="h-7 w-7 text-green-600" />
        </div>
        <LandoAuthHeader
          title="Password reset!"
          description="Your password has been successfully reset. Redirecting to login..."
          className="text-center"
        />
      </div>
    )
  }

  return (
    <div className="w-full">
      <LandoAuthHeader title="Reset password" description="Enter your new password below" />

      <form onSubmit={handleSubmit} className="space-y-4">
        {error && <LandoAuthError>{error}</LandoAuthError>}

        <div className="space-y-2">
          <Label htmlFor="email" className="text-sm font-medium text-black">Email</Label>
          <Input id="email" name="email" type="email" placeholder="Your email address" required className={landoInputClass} />
        </div>

        <div className="space-y-2">
          <Label htmlFor="password" className="text-sm font-medium text-black">New Password</Label>
          <div className="relative">
            <Input
              id="password"
              name="password"
              type={showPassword ? "text" : "password"}
              placeholder="Enter new password"
              required
              className={`${landoInputClass} pr-10`}
            />
            <button
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-black"
            >
              {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </button>
          </div>
        </div>

        <div className="space-y-2">
          <Label htmlFor="confirmPassword" className="text-sm font-medium text-black">Confirm New Password</Label>
          <div className="relative">
            <Input
              id="confirmPassword"
              name="confirmPassword"
              type={showConfirmPassword ? "text" : "password"}
              placeholder="Confirm new password"
              required
              className={`${landoInputClass} pr-10`}
            />
            <button
              type="button"
              onClick={() => setShowConfirmPassword(!showConfirmPassword)}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-black"
            >
              {showConfirmPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </button>
          </div>
        </div>

        <Button type="submit" className={landoBtnClass} disabled={isLoading}>
          {isLoading ? <Spinner className="h-4 w-4" /> : "Reset password"}
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-gray-600">
        Remember your password?{" "}
        <Link href="/login" className="font-medium text-[#2563eb] hover:text-[#1d4ed8]">Sign in</Link>
      </p>
    </div>
  )
}

export default function ResetPasswordPage() {
  return (
    <Suspense fallback={<div className="flex items-center justify-center py-12"><Spinner className="h-8 w-8" /></div>}>
      <ResetPasswordForm />
    </Suspense>
  )
}
