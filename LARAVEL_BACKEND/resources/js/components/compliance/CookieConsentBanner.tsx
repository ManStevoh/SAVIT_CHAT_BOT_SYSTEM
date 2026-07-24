"use client"

import { useEffect, useState } from "react"
import Link from "next/link"
import { usePathname } from "next/navigation"
import { Button } from "@/components/ui/button"
import { useAppBranding } from "@/components/providers/AppBrandingProvider"

const STORAGE_KEY = "essem_cookie_consent"

function isPrivatePath(pathname: string | null): boolean {
  if (!pathname) return false
  return (
    pathname.startsWith("/admin") ||
    pathname.startsWith("/dashboard") ||
    pathname.startsWith("/login") ||
    pathname.startsWith("/register") ||
    pathname.startsWith("/forgot-password") ||
    pathname.startsWith("/reset-password")
  )
}

export function CookieConsentBanner() {
  const branding = useAppBranding()
  const pathname = usePathname()
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    if (isPrivatePath(pathname)) {
      setVisible(false)
      return
    }
    if (!branding.cookieBannerEnabled) {
      setVisible(false)
      return
    }
    try {
      const stored = localStorage.getItem(STORAGE_KEY)
      setVisible(!stored)
    } catch {
      setVisible(true)
    }
  }, [branding.cookieBannerEnabled, pathname])

  if (!visible) return null

  const text =
    branding.cookieBannerText?.trim() ||
    "We use cookies to keep you signed in, measure site performance, and improve RelayIQ. See our Privacy Policy for details."
  const policyUrl = branding.cookiePolicyUrl?.trim() || "/privacy"

  const accept = () => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({ accepted: true, at: new Date().toISOString() }))
    } catch {
      /* ignore */
    }
    setVisible(false)
  }

  return (
    <div
      role="dialog"
      aria-live="polite"
      aria-label="Cookie consent"
      className="fixed inset-x-0 bottom-0 z-[100] p-4 sm:p-6"
    >
      <div className="mx-auto flex max-w-4xl flex-col gap-4 rounded-xl border border-border bg-background/95 p-4 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:p-5">
        <p className="text-sm leading-relaxed text-muted-foreground">
          {text}{" "}
          <Link href={policyUrl} className="font-medium text-primary underline-offset-4 hover:underline">
            Privacy Policy
          </Link>
        </p>
        <div className="flex shrink-0 gap-2">
          <Button type="button" variant="outline" size="sm" asChild>
            <Link href={policyUrl}>Learn more</Link>
          </Button>
          <Button type="button" size="sm" onClick={accept}>
            Accept
          </Button>
        </div>
      </div>
    </div>
  )
}
