"use client"

import { useState } from "react"
import Link from "next/link"
import { Button } from "@/components/ui/button"
import { Menu, X } from "lucide-react"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"
import { cn } from "@/lib/utils"
import type { CmsLink } from "./types"

interface LandoNavbarProps {
  links?: CmsLink[]
  loginLabel?: string
  loginHref?: string
  signupLabel?: string
  signupHref?: string
  activePath?: string
}

export function LandoNavbar({
  links = [],
  loginLabel = "Log in",
  loginHref = "/login",
  signupLabel = "Sign up",
  signupHref = "/register",
  activePath = "/",
}: LandoNavbarProps) {
  const [open, setOpen] = useState(false)

  return (
    <nav className="lando-nav fixed top-0 left-0 right-0 z-50 border-b border-border/80 bg-muted/95 backdrop-blur-sm">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
        <Link href="/" onClick={() => setOpen(false)} className="min-w-0 shrink flex items-center gap-2">
          <AppLogoAndName variant="navbar" className="font-bold text-foreground" />
          <span className="hidden sm:inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-primary">
            v2.0
          </span>
        </Link>

        <div className="hidden md:flex md:items-center md:gap-8">
          {links.map((link) => {
            const isActive = activePath === link.href
            return (
              <Link
                key={link.href}
                href={link.href}
                className={cn(
                  "text-sm font-medium transition-colors",
                  isActive ? "text-primary" : "text-foreground/90 hover:text-primary dark:text-foreground/90 dark:hover:text-primary"
                )}
              >
                {link.label}
              </Link>
            )
          })}
        </div>

        <div className="flex items-center gap-2">
          <Link
            href={loginHref}
            className="hidden text-sm font-medium text-foreground/90 transition-colors hover:text-primary sm:inline"
          >
            {loginLabel}
          </Link>
          <Button
            asChild
            className="hidden h-9 rounded-lg bg-primary px-5 text-sm font-medium text-white transition-transform hover:bg-primary/90 hover:-translate-y-0.5 sm:inline-flex"
          >
            <Link href={signupHref}>{signupLabel}</Link>
          </Button>
          <button
            type="button"
            className="rounded-lg p-2 text-foreground md:hidden"
            onClick={() => setOpen(!open)}
            aria-label="Toggle menu"
          >
            {open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </button>
        </div>
      </div>

      <div
        className={cn(
          "overflow-hidden border-t border-border/80 bg-muted transition-all md:hidden",
          open ? "max-h-96 pb-4 opacity-100" : "max-h-0 opacity-0"
        )}
      >
        <div className="mx-auto max-w-6xl space-y-1 px-4 pt-3 sm:px-6">
          {links.map((link) => {
            const isActive = activePath === link.href
            return (
              <Link
                key={link.href}
                href={link.href}
                onClick={() => setOpen(false)}
                className={cn(
                  "block rounded-lg px-3 py-2 text-sm font-medium",
                  isActive ? "bg-card text-primary" : "text-foreground hover:bg-card/70"
                )}
              >
                {link.label}
              </Link>
            )
          })}
          <div className="mt-3 flex flex-col gap-2 border-t border-border pt-3">
            <Link
              href={loginHref}
              onClick={() => setOpen(false)}
              className="rounded-lg px-3 py-2 text-sm font-medium text-foreground hover:bg-card/70"
            >
              {loginLabel}
            </Link>
            <Button asChild className="h-10 rounded-lg bg-primary text-white hover:bg-primary/90">
              <Link href={signupHref} onClick={() => setOpen(false)}>
                {signupLabel}
              </Link>
            </Button>
          </div>
        </div>
      </div>
    </nav>
  )
}
