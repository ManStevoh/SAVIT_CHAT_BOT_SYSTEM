"use client"

import { useState } from "react"
import Link from "next/link"
import { useTheme } from "next-themes"
import { Button } from "@/components/ui/button"
import { Menu, Moon, Sun, X } from "lucide-react"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"

export function LandingNavbar() {
  const [isOpen, setIsOpen] = useState(false)
  const { resolvedTheme, setTheme } = useTheme()

  return (
    <nav className="fixed top-0 left-0 right-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-xl">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="flex h-16 items-center justify-between">
          <div className="flex items-center gap-2">
            <AppLogoAndName variant="navbar" />
          </div>

          <div className="hidden md:flex md:items-center md:gap-8">
            <Link href="#features" className="text-sm text-muted-foreground transition-colors hover:text-foreground">
              Features
            </Link>
            <Link href="#pricing" className="text-sm text-muted-foreground transition-colors hover:text-foreground">
              Pricing
            </Link>
            <Link href="#testimonials" className="text-sm text-muted-foreground transition-colors hover:text-foreground">
              Testimonials
            </Link>
            <Link href="#faq" className="text-sm text-muted-foreground transition-colors hover:text-foreground">
              FAQ
            </Link>
          </div>

          <div className="flex items-center gap-2">
            <Button
              variant="ghost"
              size="icon"
              onClick={() => {
                const current = resolvedTheme ?? "light"
                setTheme(current === "dark" ? "light" : "dark")
              }}
              className="text-muted-foreground hover:text-foreground shrink-0"
              aria-label="Toggle color theme"
            >
              {(resolvedTheme ?? "light") === "dark" ? (
                <Sun className="h-5 w-5" />
              ) : (
                <Moon className="h-5 w-5" />
              )}
            </Button>

            <div className="hidden md:flex md:items-center md:gap-4">
              <Button variant="ghost" asChild>
                <Link href="/login">Sign In</Link>
              </Button>
              <Button asChild>
                <Link href="/register">Start Free Trial</Link>
              </Button>
            </div>

            <button
              className="md:hidden shrink-0"
              type="button"
              onClick={() => setIsOpen(!isOpen)}
              aria-label="Toggle menu"
            >
              {isOpen ? (
                <X className="h-6 w-6 text-foreground" />
              ) : (
                <Menu className="h-6 w-6 text-foreground" />
              )}
            </button>
          </div>
        </div>

        {isOpen && (
          <div className="border-t border-border/50 py-4 md:hidden">
            <div className="flex flex-col gap-4">
              <Link href="#features" className="text-sm text-muted-foreground transition-colors hover:text-foreground">
                Features
              </Link>
              <Link href="#pricing" className="text-sm text-muted-foreground transition-colors hover:text-foreground">
                Pricing
              </Link>
              <Link href="#testimonials" className="text-sm text-muted-foreground transition-colors hover:text-foreground">
                Testimonials
              </Link>
              <Link href="#faq" className="text-sm text-muted-foreground transition-colors hover:text-foreground">
                FAQ
              </Link>
              <div className="flex flex-col gap-2 pt-4">
                <Button variant="ghost" asChild className="w-full">
                  <Link href="/login">Sign In</Link>
                </Button>
                <Button asChild className="w-full">
                  <Link href="/register">Start Free Trial</Link>
                </Button>
              </div>
            </div>
          </div>
        )}
      </div>
    </nav>
  )
}
