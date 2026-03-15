"use client"

import { MessageSquare, Shield } from "lucide-react"
import { cn } from "@/lib/utils"
import { useAppBranding } from "@/components/providers/AppBrandingProvider"

type Variant = "sidebar" | "navbar" | "footer" | "admin"

const sizeMap: Record<Variant, { box: string; icon: string; textClass: string }> = {
  sidebar: { box: "h-8 w-8", icon: "h-4 w-4", textClass: "text-sidebar-foreground" },
  navbar: { box: "h-9 w-9", icon: "h-5 w-5", textClass: "text-foreground" },
  footer: { box: "h-9 w-9", icon: "h-5 w-5", textClass: "text-foreground" },
  admin: { box: "h-8 w-8", icon: "h-4 w-4", textClass: "text-sidebar-foreground" },
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
  /** e.g. <span className="ml-2 text-xs text-muted-foreground">Admin</span> */
  suffix?: React.ReactNode
  /** Only render the logo/icon box (e.g. for collapsed sidebar) */
  iconOnly?: boolean
  className?: string
}) {
  const branding = useAppBranding()
  const sizes = sizeMap[variant]
  const name = branding.applicationName || "Savit Chat"

  const iconBox = (
    <div
      className={`flex ${sizes.box} items-center justify-center rounded-lg bg-primary shrink-0 overflow-hidden`}
    >
      {branding.appLogo ? (
        <img
          src={branding.appLogo}
          alt=""
          className="h-full w-full object-contain p-0.5"
        />
      ) : showAdminBadge ? (
        <Shield className={`${sizes.icon} text-primary-foreground`} />
      ) : (
        <MessageSquare className={`${sizes.icon} text-primary-foreground`} />
      )}
    </div>
  )

  if (iconOnly) return <div className={className}>{iconBox}</div>

  return (
    <div className={className ?? "flex items-center gap-2"}>
      {iconBox}
      <div className="flex items-center min-w-0">
        <span className={cn("font-bold truncate", sizes.textClass)}>{name}</span>
        {suffix}
      </div>
    </div>
  )
}
