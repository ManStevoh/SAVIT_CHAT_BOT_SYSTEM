"use client"

import React, { Suspense, useState } from "react"
import Link from "next/link"
import { useRouter, useSearchParams } from "next/navigation"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Spinner } from "@/components/ui/spinner"
import { Eye, EyeOff } from "lucide-react"
import { register as registerApi, type RegisterData } from "@/lib/api-actions"
import { LandoAuthHeader, LandoAuthError, landoBtnClass, landoInputClass } from "@/components/lando/auth-form"

function RegisterPageContent() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const planId = searchParams.get("plan")
  const [showPassword, setShowPassword] = useState(false)
  const [showConfirmPassword, setShowConfirmPassword] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setError(null)
    setIsLoading(true)
    const form = e.currentTarget
    const companyName = (form.elements.namedItem("company") as HTMLInputElement).value.trim()
    const name = (form.elements.namedItem("name") as HTMLInputElement | null)?.value.trim() ?? companyName
    const email = (form.elements.namedItem("email") as HTMLInputElement).value.trim()
    const phone = (form.elements.namedItem("phone") as HTMLInputElement | null)?.value.trim() ?? ""
    const password = (form.elements.namedItem("password") as HTMLInputElement).value.trim()
    const confirmPassword = (form.elements.namedItem("confirmPassword") as HTMLInputElement).value.trim()
    if (password !== confirmPassword) {
      setError("Passwords do not match")
      setIsLoading(false)
      return
    }
    const data: RegisterData = {
      companyName,
      name,
      email,
      phone,
      password,
      confirmPassword,
      acceptTerms: true,
    }
    const result = await registerApi(data)
    setIsLoading(false)
    if (!result.success) {
      setError(result.message ?? "Registration failed")
      return
    }
    if (planId) {
      router.push(`/login?registered=1&plan=${planId}`)
    } else {
      router.push("/login?registered=1")
    }
  }

  return (
    <div className="w-full">
      <LandoAuthHeader title="Create your account" description="Start your 14-day free trial" />

      <form onSubmit={handleSubmit} className="space-y-4">
        {error && <LandoAuthError>{error}</LandoAuthError>}

        <div className="space-y-2">
          <Label htmlFor="company" className="text-sm font-medium text-black">Company Name</Label>
          <Input id="company" name="company" type="text" placeholder="Your company name" required className={landoInputClass} />
        </div>

        <div className="space-y-2">
          <Label htmlFor="name" className="text-sm font-medium text-black">Your Name</Label>
          <Input id="name" name="name" type="text" placeholder="Your name" className={landoInputClass} />
        </div>

        <div className="space-y-2">
          <Label htmlFor="email" className="text-sm font-medium text-black">Email</Label>
          <Input id="email" name="email" type="email" placeholder="you@company.com" required className={landoInputClass} />
        </div>

        <div className="space-y-2">
          <Label htmlFor="phone" className="text-sm font-medium text-black">Phone</Label>
          <Input id="phone" name="phone" type="tel" placeholder="+254 700 000 000" required className={landoInputClass} />
        </div>

        <div className="space-y-2">
          <Label htmlFor="password" className="text-sm font-medium text-black">Password</Label>
          <div className="relative">
            <Input
              id="password"
              name="password"
              type={showPassword ? "text" : "password"}
              placeholder="Create a password"
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
          <Label htmlFor="confirmPassword" className="text-sm font-medium text-black">Confirm Password</Label>
          <div className="relative">
            <Input
              id="confirmPassword"
              name="confirmPassword"
              type={showConfirmPassword ? "text" : "password"}
              placeholder="Confirm your password"
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
          {isLoading ? <Spinner className="h-4 w-4" /> : "Create account"}
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-gray-600">
        Already have an account?{" "}
        <Link href="/login" className="font-medium text-[#2563eb] hover:text-[#1d4ed8]">Sign in</Link>
      </p>
    </div>
  )
}

export default function RegisterPage() {
  return (
    <Suspense
      fallback={
        <div className="flex items-center justify-center py-12">
          <Spinner className="h-6 w-6" />
        </div>
      }
    >
      <RegisterPageContent />
    </Suspense>
  )
}
