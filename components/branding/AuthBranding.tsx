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
    <div className="min-h-screen bg-background">
      <div className="grid min-h-screen lg:grid-cols-2">
        <div className="relative hidden flex-col justify-between overflow-hidden bg-foreground p-10 lg:flex">
          <div className="absolute inset-0 bg-[radial-gradient(ellipse_80%_80%_at_20%_20%,oklch(0.42_0.11_255/0.4),transparent)]" />
          <div className="relative">
            <Link href="/">
              <AppLogoAndName variant="navbar" className="[&_span]:text-background [&_.rounded-lg]:bg-background/10 [&_svg]:text-background" />
            </Link>
          </div>
          <div className="relative max-w-md">
            <blockquote className="font-display text-3xl leading-snug text-background/95">
              &ldquo;Automate conversations, close sales, and grow — all on WhatsApp.&rdquo;
            </blockquote>
            <p className="mt-4 text-sm text-background/60">
              AI-powered order flows with M-Pesa and card payments built in.
            </p>
          </div>
          <p className="relative text-xs text-background/40">
            © {new Date().getFullYear()} {appName}
          </p>
        </div>

        <div className="flex min-h-screen flex-col">
          <header className="p-6 lg:hidden">
            <Link href="/">
              <AppLogoAndName variant="navbar" />
            </Link>
          </header>
          <main className="flex flex-1 items-center justify-center p-6">
            {children}
          </main>
          <footer className="p-6 text-center text-xs text-muted-foreground lg:hidden">
            © {new Date().getFullYear()} {appName}. All rights reserved.
          </footer>
        </div>
      </div>
    </div>
  )
}
