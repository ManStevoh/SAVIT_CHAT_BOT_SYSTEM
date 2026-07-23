"use client"

import { Shield } from "lucide-react"
import { cn } from "@/lib/utils"
import { useAppBranding } from "@/components/providers/AppBrandingProvider"

const DEFAULT_MARK = "/images/branding/relaysiq-favicon.png"

type Variant = "sidebar" | "navbar" | "footer" | "admin"

const sizeMap: Record<Variant, { box: string; icon: string; textClass: string }> = {
  sidebar: { box: "h-7 w-7", icon: "h-3.5 w-3.5", textClass: "text-sidebar-foreground text-sm" },
  navbar: { box: "h-8 w-8", icon: "h-4 w-4", textClass: "text-foreground text-sm" },
  footer: { box: "h-8 w-8", icon: "h-4 w-4", textClass: "text-foreground text-sm" },
  admin: { box: "h-7 w-7", icon: "h-3.5 w-3.5", textClass: "text-sidebar-foreground text-sm" },
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
  const logoSrc = branding.appLogo || DEFAULT_MARK

  const iconBox = (
    <div
      className={cn(
        `flex ${sizes.box} items-center justify-center rounded-lg bg-foreground shrink-0 overflow-hidden`,
        "bg-transparent"
      )}
    >
      {showAdminBadge && !branding.appLogo ? (
        <div className={cn(`flex ${sizes.box} items-center justify-center rounded-lg bg-foreground`)}>
          <Shield className={`${sizes.icon} text-background`} />
        </div>
      ) : (
        <img
          src={logoSrc}
          alt=""
          className="h-full w-full object-contain"
        />
      )}
    </div>
  )

  if (iconOnly) return <div className={className}>{iconBox}</div>

  return (
    <div className={className ?? "flex items-center gap-2.5"}>
      {iconBox}
      <div className="flex min-w-0 items-center">
        <span className={cn("font-semibold tracking-tight truncate", sizes.textClass)}>
          {name}
        </span>
        {suffix}
      </div>
    </div>
  )
}
