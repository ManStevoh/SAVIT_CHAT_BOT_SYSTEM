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
    <nav className="lando-nav fixed top-0 left-0 right-0 z-50 border-b border-gray-200/80 bg-[#f3f4f6]/95 backdrop-blur-sm">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <Link href="/" onClick={() => setOpen(false)}>
          <AppLogoAndName variant="navbar" className="font-bold text-black" />
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
                  isActive ? "text-[#2563eb]" : "text-black hover:text-[#2563eb]"
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
            className="hidden text-sm font-medium text-black sm:inline"
          >
            {loginLabel}
