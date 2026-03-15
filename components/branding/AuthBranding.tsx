"use client"

import Link from "next/link"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"
import { useAppBranding } from "@/components/providers/AppBrandingProvider"

export function AuthBranding({
  children,
}: {
  children: React.ReactNode
}) {
  const branding = useAppBranding()
  const appName = branding.applicationName || "Savit Chat"

  return (
    <div className="min-h-screen flex flex-col bg-background">
      <header className="p-6">
        <Link href="/" className="inline-flex items-center gap-2">
          <AppLogoAndName variant="navbar" />
        </Link>
      </header>
      <main className="flex-1 flex items-center justify-center p-6">
        {children}
      </main>
      <footer className="p-6 text-center text-sm text-muted-foreground">
        © {new Date().getFullYear()} {appName}. All rights reserved.
      </footer>
    </div>
  )
}
