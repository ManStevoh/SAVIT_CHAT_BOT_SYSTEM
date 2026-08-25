"use client"

import { Shield } from "lucide-react"
import { cn } from "@/lib/utils"
import { useAppBranding } from "@/components/providers/AppBrandingProvider"

const DEFAULT_FULL_LOGO_LIGHT = "/images/branding/relaysiq-wordmark-light.png?v=5"
const DEFAULT_FULL_LOGO_DARK = "/images/branding/relaysiq-wordmark-dark.png?v=5"
const DEFAULT_MARK_LIGHT = "/images/branding/relaysiq-mark.png?v=5"
const DEFAULT_MARK_DARK = "/images/branding/relaysiq-mark-dark.png?v=5"

type Variant = "sidebar" | "navbar" | "footer" | "admin"

const sizeMap: Record<Variant, { imgClass: string; markClass: string }> = {
  sidebar: { imgClass: "h-9 w-auto object-contain max-w-[170px]", markClass: "h-7 w-auto object-contain" },
  navbar: { imgClass: "h-8 w-auto object-contain max-w-[140px] sm:h-9 sm:max-w-[180px]", markClass: "h-8 w-auto object-contain" },
  footer: { imgClass: "h-8 w-auto object-contain max-w-[160px] sm:h-9 sm:max-w-[180px]", markClass: "h-8 w-auto object-contain" },
  admin: { imgClass: "h-8 w-auto object-contain max-w-[160px]", markClass: "h-7 w-auto object-contain" },
}

export function AppLogoAndName({
  variant = "sidebar",
  showAdminBadge = false,
  suffix,
  iconOnly = false,
  className,
}: {
  variant?: Variant
  showAdminBadge?: boolean
  suffix?: React.ReactNode
  iconOnly?: boolean
  className?: string
}) {
  const branding = useAppBranding()
  const sizes = sizeMap[variant]
  const name = branding.applicationName || "RelayIQ"

  if (iconOnly) {
    return (
      <div className={cn("inline-flex items-center justify-center shrink-0", className)}>
        <img
          src={DEFAULT_MARK_LIGHT}
          alt={name}
          className={cn(sizes.markClass, "dark:hidden")}
        />
        <img
          src={DEFAULT_MARK_DARK}
          alt={name}
          className={cn(sizes.markClass, "hidden dark:block")}
        />
      </div>
    )
  }

  return (
    <div className={cn("flex items-center gap-2 shrink-0 select-none", className)}>
      <div className="flex items-center shrink-0">
        <img
          src={DEFAULT_FULL_LOGO_LIGHT}
          alt={name}
          className={cn(sizes.imgClass, "dark:hidden")}
        />
        <img
          src={DEFAULT_FULL_LOGO_DARK}
          alt={name}
          className={cn(sizes.imgClass, "hidden dark:block")}
        />
      </div>
      {suffix}
    </div>
  )
}
