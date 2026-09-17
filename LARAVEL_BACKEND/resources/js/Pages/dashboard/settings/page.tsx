"use client"

import { Suspense } from "react"
import Link from "next/link"
import { useSearchParams } from "next/navigation"
import { Settings as SettingsIcon } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { parseSettingsTab, type SettingsTab } from "@/components/dashboard/sidebar"
import { useCompanySettings, useSubscription } from "@/lib/api-hooks"
import { planDisplayName } from "@/lib/use-plan"
import { BusinessSection } from "@/components/settings/BusinessSection"
import { BrandSection } from "@/components/settings/BrandSection"
import { PaymentsSection } from "@/components/settings/PaymentsSection"
import { WhatsAppSection } from "@/components/settings/WhatsAppSection"
import { AiAssistantSection } from "@/components/settings/AiAssistantSection"
import { TeamSection } from "@/components/settings/TeamSection"

const SECTION_DESC: Record<SettingsTab, string> = {
  profile: "Details, sales channels, region and currency.",
  branding: "Logo, colors and storefront appearance.",
  "order-payments": "M-Pesa, cards, cash, delivery and recovery.",
  whatsapp: "Connection, setup and message templates.",
  ai: "Persona, selling agent, voice and brand voice.",
  team: "Members, invites and seats.",
}

function SettingsContent() {
  const searchParams = useSearchParams()
  const active = parseSettingsTab(searchParams.get("tab"))
  const { data: settings } = useCompanySettings()
  const { data: subscription } = useSubscription()

  return (
    <div className="w-full space-y-6">
      {/* Header: context + billing lives one click away */}
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-start gap-3">
          <span className="mt-0.5 flex h-10 w-10 items-center justify-center rounded-xl bg-muted text-foreground">
            <SettingsIcon className="h-5 w-5" />
          </span>
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-foreground">Settings</h1>
            <p className="text-sm text-muted-foreground">
              {settings?.companyName ?? "Your business"} — everything the assistant and storefront run on.
            </p>
          </div>
        </div>
        <Link href="/dashboard/subscription">
          <Badge variant="outline" className="gap-1.5 px-3 py-1.5 text-xs font-medium hover:border-primary hover:text-primary">
            {planDisplayName(subscription?.plan, subscription?.planName)} plan
            <span aria-hidden>→</span>
          </Badge>
        </Link>
      </div>

      <div className="space-y-5">
        <p className="text-[13px] text-muted-foreground">{SECTION_DESC[active]}</p>

        <div className="min-w-0">
          {active === "profile" && <BusinessSection />}
          {active === "branding" && <BrandSection />}
          {active === "order-payments" && <PaymentsSection />}
          {active === "whatsapp" && <WhatsAppSection />}
          {active === "ai" && <AiAssistantSection />}
          {active === "team" && <TeamSection />}
        </div>
      </div>
    </div>
  )
}

export default function SettingsPage() {
  return (
    <Suspense
      fallback={
        <div className="flex items-center justify-center py-20">
          <span className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
        </div>
      }
    >
      <SettingsContent />
    </Suspense>
  )
}
