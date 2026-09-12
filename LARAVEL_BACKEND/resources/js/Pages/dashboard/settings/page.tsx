"use client"

import { Suspense, useEffect, useState } from "react"
import Link from "next/link"
import { useSearchParams } from "next/navigation"
import {
  Bot,
  Building2,
  CreditCard,
  MessageSquare,
  Palette,
  Settings as SettingsIcon,
  Users,
} from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { cn } from "@/lib/utils"
import { useCompanySettings, useSubscription } from "@/lib/api-hooks"
import { planDisplayName } from "@/lib/use-plan"
import { BusinessSection } from "@/components/settings/BusinessSection"
import { BrandSection } from "@/components/settings/BrandSection"
import { PaymentsSection } from "@/components/settings/PaymentsSection"
import { WhatsAppSection } from "@/components/settings/WhatsAppSection"
import { AiAssistantSection } from "@/components/settings/AiAssistantSection"
import { TeamSection } from "@/components/settings/TeamSection"

type SectionId = "profile" | "branding" | "order-payments" | "whatsapp" | "ai" | "team"

const SECTIONS: { id: SectionId; label: string; desc: string; icon: typeof Building2 }[] = [
  { id: "profile", label: "Business", desc: "Details, sales channels, region and currency.", icon: Building2 },
  { id: "branding", label: "Brand", desc: "Logo, colors and storefront appearance.", icon: Palette },
  { id: "order-payments", label: "Payments", desc: "M-Pesa, cards, cash, delivery and recovery.", icon: CreditCard },
  { id: "whatsapp", label: "WhatsApp", desc: "Connection, setup and message templates.", icon: MessageSquare },
  { id: "ai", label: "AI assistant", desc: "Persona, selling agent, voice and brand voice.", icon: Bot },
  { id: "team", label: "Team", desc: "Members, invites and seats.", icon: Users },
]

/** Legacy/alias tab values keep working: appearance→brand, byok/notifications→AI. */
function normalizeTab(raw: string | null): SectionId {
  switch (raw) {
    case "branding":
    case "appearance":
      return "branding"
    case "order-payments":
      return "order-payments"
    case "whatsapp":
      return "whatsapp"
    case "ai":
    case "byok":
    case "notifications":
      return "ai"
    case "team":
      return "team"
    case "profile":
    default:
      return "profile"
  }
}

function SettingsContent() {
  const searchParams = useSearchParams()
  const [active, setActive] = useState<SectionId>(() => normalizeTab(searchParams.get("tab")))
  const { data: settings } = useCompanySettings()
  const { data: subscription } = useSubscription()
  const isStarter = (subscription?.plan ?? "free") === "free"

  useEffect(() => {
    setActive(normalizeTab(searchParams.get("tab")))
  }, [searchParams])

  const select = (id: SectionId) => {
    setActive(id)
    if (typeof window !== "undefined") {
      const url = new URL(window.location.href)
      url.searchParams.set("tab", id)
      window.history.replaceState({}, "", url.pathname + url.search)
    }
  }

  return (
    <div className="mx-auto max-w-6xl space-y-6">
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
        {/* Section tabs: segmented control, not a second sidebar */}
        <div className="overflow-x-auto pb-1">
          <div className="flex min-w-max gap-1 rounded-2xl border border-border bg-muted/50 p-1.5 sm:inline-flex sm:min-w-0 sm:max-w-full sm:flex-wrap">
            {SECTIONS.map((s) => {
              const selected = active === s.id
              return (
                <button
                  key={s.id}
                  type="button"
                  onClick={() => select(s.id)}
                  className={cn(
                    "flex items-center gap-2 rounded-xl px-3.5 py-2 text-[13px] font-medium transition-all",
                    selected
                      ? "bg-background text-foreground shadow-sm ring-1 ring-border"
                      : "text-muted-foreground hover:text-foreground"
                  )}
                >
                  <s.icon className={cn("h-4 w-4", selected && "text-primary")} />
                  {s.label}
                  {s.id === "whatsapp" && isStarter && (
                    <span className="rounded bg-primary/10 px-1 py-px text-[10px] font-semibold uppercase tracking-wide text-primary">
                      Growth
                    </span>
                  )}
                </button>
              )
            })}
          </div>
        </div>
        <p className="text-[13px] text-muted-foreground">
          {SECTIONS.find((s) => s.id === active)?.desc}
        </p>

        {/* Active section */}
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
