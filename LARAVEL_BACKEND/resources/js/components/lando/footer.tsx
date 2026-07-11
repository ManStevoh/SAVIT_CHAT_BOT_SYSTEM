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
