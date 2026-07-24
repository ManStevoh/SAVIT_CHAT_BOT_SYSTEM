"use client"

import React, { Suspense, useState } from "react"
import Link from "next/link"
import { useRouter, useSearchParams } from "next/navigation"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Checkbox } from "@/components/ui/checkbox"
import { Spinner } from "@/components/ui/spinner"
import { Eye, EyeOff } from "lucide-react"
import { register as registerApi, type RegisterData } from "@/lib/api-actions"
import { LandoAuthHeader, LandoAuthError, landoBtnClass, landoInputClass } from "@/components/lando/auth-form"
import { RecaptchaWidget, resetRecaptchaWidget } from "@/components/compliance/RecaptchaWidget"
import { useAppBranding } from "@/components/providers/AppBrandingProvider"

function RegisterPageContent() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const branding = useAppBranding()
  const planId = searchParams.get("plan")
  const [showPassword, setShowPassword] = useState(false)
  const [showConfirmPassword, setShowConfirmPassword] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [acceptTerms, setAcceptTerms] = useState(false)
  const [marketingConsent, setMarketingConsent] = useState(false)
  const [recaptchaToken, setRecaptchaToken] = useState<string | null>(null)

  const loginHref = planId ? `/login?plan=${encodeURIComponent(planId)}` : "/login"

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setError(null)
    if (!acceptTerms) {
      setError("You must accept the Terms of Service and Privacy Policy to continue.")
      return
    }
    if (branding.recaptchaEnabled && !recaptchaToken) {
      setError("Please complete the captcha challenge.")
      return
    }
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
      marketingConsent,
      planId: planId || undefined,
      recaptchaToken: recaptchaToken || undefined,
    }
    const result = await registerApi(data)
    setIsLoading(false)
    if (!result.success) {
      setError(result.message ?? "Registration failed")
      resetRecaptchaWidget()
      setRecaptchaToken(null)
      return
    }

    const params = new URLSearchParams({ registered: "1" })
    if (planId) params.set("plan", planId)
    if (result.trialStarted) params.set("trial", "1")
    if (result.requiresPayment && planId) params.set("pay", "1")
    if (result.postLoginPath) {
      sessionStorage.setItem("post_login_path", result.postLoginPath)
    }
    router.push(`/login?${params.toString()}`)
  }

  return (
    <div className="w-full">
      <LandoAuthHeader title="Create your account" description="Start your free trial — pick a plan on Pricing, or begin with our starter trial." />

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

        <div className="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
          <div className="flex items-start gap-2">
            <Checkbox
              id="acceptTerms"
              checked={acceptTerms}
              onCheckedChange={(v) => setAcceptTerms(v === true)}
              className="mt-0.5"
            />
            <label htmlFor="acceptTerms" className="text-sm text-gray-700 leading-snug cursor-pointer">
              I agree to the{" "}
              <Link href="/terms" target="_blank" className="font-medium text-[#2563eb] hover:underline">
                Terms of Service
              </Link>{" "}
              and{" "}
              <Link href="/privacy" target="_blank" className="font-medium text-[#2563eb] hover:underline">
                Privacy Policy
              </Link>
              <span className="text-red-600"> *</span>
            </label>
          </div>
          <div className="flex items-start gap-2">
            <Checkbox
              id="marketingConsent"
              checked={marketingConsent}
              onCheckedChange={(v) => setMarketingConsent(v === true)}
              className="mt-0.5"
            />
            <label htmlFor="marketingConsent" className="text-sm text-gray-700 leading-snug cursor-pointer">
              Send me product updates and marketing emails (optional)
            </label>
          </div>
        </div>

        <RecaptchaWidget onChange={setRecaptchaToken} />

        <Button type="submit" className={landoBtnClass} disabled={isLoading}>
          {isLoading ? <Spinner className="h-4 w-4" /> : "Create account"}
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-gray-600">
        Already have an account?{" "}
        <Link href={loginHref} className="font-medium text-[#2563eb] hover:text-[#1d4ed8]">Sign in</Link>
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
