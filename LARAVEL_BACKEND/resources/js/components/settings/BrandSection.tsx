"use client"

import Link from "next/link"
import { BrandCustomizationCard } from "@/components/dashboard/BrandCustomizationCard"
import { useCompanySettings, useSubscription } from "@/lib/api-hooks"

export function BrandSection() {
  const { data: settings } = useCompanySettings()
  const { data: subscription } = useSubscription()
  const isStarter = (subscription?.plan ?? "free") === "free"

  return (
    <div className="space-y-4">
      {isStarter && (
        <p className="rounded-xl border border-border bg-muted/40 px-4 py-3 text-[13px] leading-relaxed text-muted-foreground">
          Starter includes <span className="font-medium text-foreground">RelayIQ branding</span> on your
          storefront — your logo, colors, and content are all yours.{" "}
          <Link href="/dashboard/subscription#plans" className="font-medium text-primary hover:underline">
            Growth removes RelayIQ branding
          </Link>
          .
        </p>
      )}
      <BrandCustomizationCard
        initialLogo={settings?.logo}
        initialTheme={settings?.storefrontTheme}
        initialAnnouncementBar={settings?.storefrontAnnouncementBar}
        initialFooterText={settings?.storefrontTheme?.footer_text}
        businessName={settings?.companyName || "My Brand"}
        storeSlug={settings?.storeSlug || ""}
      />
    </div>
  )
}
