import Link from "next/link"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"
import type { CmsLink } from "./types"

interface LandoFooterProps {
  copyright?: string
  navLinks?: CmsLink[]
  socialLinks?: CmsLink[]
  legalLinks?: CmsLink[]
}

export function LandoFooter({
  copyright,
  navLinks = [],
  socialLinks = [],
  legalLinks = [],
}: LandoFooterProps) {
  return (
    <footer className="lando-footer border-t border-gray-200 bg-[#f3f4f6]">
      <div className="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:grid-cols-2 lg:grid-cols-4 sm:px-6 lg:px-8">
        <div>
          <AppLogoAndName variant="navbar" className="font-bold text-black" />
          {copyright && <p className="mt-4 text-sm text-gray-500">{copyright}</p>}
        </div>

        <div className="flex flex-col gap-2">
          {navLinks.map((link) => (
            <Link key={link.href} href={link.href} className="text-sm font-medium text-black hover:text-[#2563eb]">
              {link.label}
            </Link>
