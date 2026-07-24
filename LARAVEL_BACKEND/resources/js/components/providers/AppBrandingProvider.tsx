"use client"

import { createContext, useContext, useEffect, useState } from "react"
import { getAppBranding, type AppBranding } from "@/lib/api-actions"

const AppBrandingContext = createContext<AppBranding | null>(null)

export function useAppBranding(): AppBranding {
  const ctx = useContext(AppBrandingContext)
  return (
    ctx ?? {
      applicationName: "RelayIQ",
      appLogo: null,
      primaryColor: null,
      secondaryColor: null,
      requireEmailVerification: false,
      cookieBannerEnabled: true,
      cookieBannerText: null,
      cookiePolicyUrl: "/privacy",
      recaptchaEnabled: false,
      recaptchaSiteKey: null,
    }
  )
}

export function AppBrandingProvider({ children }: { children: React.ReactNode }) {
  const [branding, setBranding] = useState<AppBranding | null>(null)

  useEffect(() => {
    getAppBranding()
      .then(setBranding)
      .catch(() => setBranding(null))
  }, [])

  useEffect(() => {
    if (!branding) return
    const root = document.documentElement
    if (branding.primaryColor) {
      root.style.setProperty("--primary", branding.primaryColor)
      root.style.setProperty("--ring", branding.primaryColor)
      root.style.setProperty("--accent", branding.primaryColor)
      root.style.setProperty("--sidebar-primary", branding.primaryColor)
      root.style.setProperty("--chart-1", branding.primaryColor)
      root.style.setProperty("--wa-brand", branding.primaryColor)
    }
    if (branding.secondaryColor) {
      root.style.setProperty("--secondary", branding.secondaryColor)
      root.style.setProperty("--sidebar-accent", branding.secondaryColor)
      root.style.setProperty("--muted", branding.secondaryColor)
    }
    return () => {
      root.style.removeProperty("--primary")
      root.style.removeProperty("--ring")
      root.style.removeProperty("--accent")
      root.style.removeProperty("--sidebar-primary")
      root.style.removeProperty("--chart-1")
      root.style.removeProperty("--wa-brand")
      root.style.removeProperty("--secondary")
      root.style.removeProperty("--sidebar-accent")
      root.style.removeProperty("--muted")
    }
  }, [branding?.primaryColor, branding?.secondaryColor])

  return (
    <AppBrandingContext.Provider value={branding}>
      {children}
    </AppBrandingContext.Provider>
  )
}
