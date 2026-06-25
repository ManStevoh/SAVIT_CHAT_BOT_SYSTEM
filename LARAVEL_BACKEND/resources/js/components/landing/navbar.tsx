"use client"

import { useState } from "react"
import Link from "next/link"
import { useTheme } from "next-themes"
import { Button } from "@/components/ui/button"
import { Menu, Moon, Sun, X } from "lucide-react"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"
import { cn } from "@/lib/utils"

const navLinks = [
  { href: "#features", label: "Features" },
  { href: "#use-cases", label: "Use cases" },
  { href: "#growth", label: "Growth" },
  { href: "#pricing", label: "Pricing" },
  { href: "#faq", label: "FAQ" },
]

export function LandingNavbar() {
  const [isOpen, setIsOpen] = useState(false)
  const { resolvedTheme, setTheme } = useTheme()

  return (
    <nav className="fixed top-0 left-0 right-0 z-50 border-b border-border/70 bg-background">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="flex h-14 items-center justify-between">
          <AppLogoAndName variant="navbar" />

          <div className="hidden md:flex md:items-center md:gap-1">
            {navLinks.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                className="rounded-md px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
              >
                {link.label}
              </Link>
            ))}
          </div>

          <div className="flex items-center gap-1.5">
            <Button
              variant="ghost"
              size="icon"
              onClick={() => {
                const current = resolvedTheme ?? "light"
                setTheme(current === "dark" ? "light" : "dark")
              }}
              className="h-8 w-8 text-muted-foreground hover:text-foreground"
              aria-label="Toggle color theme"
            >
              {(resolvedTheme ?? "light") === "dark" ? (
                <Sun className="h-4 w-4" />
              ) : (
                <Moon className="h-4 w-4" />
              )}
            </Button>

            <div className="hidden md:flex md:items-center md:gap-2">
              <Button variant="ghost" size="sm" asChild className="text-muted-foreground">
                <Link href="/login">Sign in</Link>
              </Button>
              <Button size="sm" asChild className="rounded-md wa-cta border-0 shadow-none">
                <Link href="/register">Start free trial</Link>
              </Button>
            </div>

            <button
              className="rounded-md p-2 md:hidden"
              type="button"
              onClick={() => setIsOpen(!isOpen)}
              aria-label="Toggle menu"
            >
              {isOpen ? (
                <X className="h-5 w-5 text-foreground" />
              ) : (
                <Menu className="h-5 w-5 text-foreground" />
              )}
            </button>
          </div>
        </div>

        <div
          className={cn(
            "overflow-hidden border-t border-border/60 transition-all duration-300 md:hidden",
            isOpen ? "max-h-80 py-4 opacity-100" : "max-h-0 opacity-0 border-t-0"
          )}
        >
          <div className="flex flex-col gap-1">
            {navLinks.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                onClick={() => setIsOpen(false)}
                className="rounded-md px-2 py-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
              >
                {link.label}
              </Link>
            ))}
            <div className="mt-3 flex flex-col gap-2 border-t border-border/60 pt-4">
              <Button variant="ghost" asChild className="w-full justify-start">
                <Link href="/login">Sign in</Link>
              </Button>
              <Button asChild className="w-full rounded-lg">
                <Link href="/register">Start free trial</Link>
              </Button>
            </div>
          </div>
        </div>
      </div>
    </nav>
  )
}
