"use client"

import Link from "next/link"
import { ArrowRight, Lock, Loader2, Sparkles } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Progress } from "@/components/ui/progress"
import { GROWTH_HIGHLIGHTS, isStarterPlan, limitPercent } from "@/lib/use-plan"
import { cn } from "@/lib/utils"
import { DashboardPlansPicker } from "@/components/billing/DashboardPlansPicker"
import { GROWTH_ONLY_HREFS } from "@/lib/growth-routes"
import { usePathname } from "next/navigation"
import { useSubscription } from "@/lib/api-hooks"

/**
 * Professional, non-pushy upgrade nudge.
 * Used wherever a Starter limit is reached or a Growth-only feature is gated.
 */
export function UpgradePrompt({
  title,
  description,
  highlights = [...GROWTH_HIGHLIGHTS],
  ctaLabel = "Compare plans",
  href = "/dashboard/subscription#plans",
  compact = false,
}: {
  title: string
  description: string
  highlights?: string[]
  ctaLabel?: string
  href?: string
  compact?: boolean
}) {
  return (
    <div
      className={cn(
        "rounded-xl border border-primary/20 bg-gradient-to-br from-primary/[0.07] via-primary/[0.03] to-transparent",
        compact ? "p-4" : "p-5"
      )}
    >
      <div className="flex items-start gap-3">
        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
          <Sparkles className="h-4 w-4 text-primary" />
        </span>
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <p className="text-sm font-semibold text-foreground">{title}</p>
            <Badge variant="outline" className="border-primary/30 text-primary text-[11px]">
              Growth
            </Badge>
          </div>
          <p className="mt-1 text-[13px] leading-relaxed text-muted-foreground">{description}</p>
          {!compact && (
            <ul className="mt-3 grid gap-1.5 text-[13px] text-muted-foreground sm:grid-cols-2">
              {highlights.slice(0, 4).map((h) => (
                <li key={h} className="flex items-center gap-2">
                  <span className="h-1 w-1 shrink-0 rounded-full bg-primary" />
                  {h}
                </li>
              ))}
            </ul>
          )}
          <Button asChild size="sm" className="mt-3" variant="default">
            <Link href={href}>
              {ctaLabel}
              <ArrowRight className="ml-1.5 h-3.5 w-3.5" />
            </Link>
          </Button>
        </div>
      </div>
    </div>
  )
}

/** Full-page gate for Growth-only areas (WhatsApp, campaigns, Growth Engine…). */
export function LockedFeatureGate({
  icon: Icon = Lock,
  title,
  description,
  planLabel = "Starter",
}: {
  icon?: React.ComponentType<{ className?: string }>
  title: string
  description: string
  planLabel?: string
}) {
  return (
    <div className="mx-auto w-full max-w-6xl space-y-8">
      <div className="mx-auto max-w-2xl text-center">
        <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10">
          <Icon className="h-6 w-6 text-primary" />
        </span>
        <h1 className="mt-4 text-2xl font-bold tracking-tight text-foreground">{title}</h1>
        <p className="mt-2 text-sm text-muted-foreground">
          Included on Growth &amp; Custom · Your current plan: {planLabel}
        </p>
        <p className="mt-3 text-sm leading-relaxed text-muted-foreground">{description}</p>
      </div>
      <DashboardPlansPicker
        title="Choose a plan"
        description="Compare every plan and upgrade here. Checkout uses the same payment methods as Subscription."
      />
    </div>
  )
}

const GROWTH_ROUTE_COPY: Record<string, { title: string; description: string }> = {
  "/dashboard/growth": {
    title: "Growth Engine lives on Growth",
    description: "AI content, social publishing, and ads live on plans that include the Growth Engine. Choose a plan below to unlock it.",
  },
  "/dashboard/whatsapp/campaigns": {
    title: "WhatsApp campaigns live on Growth",
    description: "Broadcast posters and templates to customers from your connected number. Choose a plan below to unlock campaigns.",
  },
  "/dashboard/business-intelligence": {
    title: "Business intelligence lives on Growth",
    description: "Deeper reporting and insights are included on Growth and Custom. Choose a plan below to unlock this area.",
  },
  "/dashboard/executive": {
    title: "Executive dashboard lives on Growth",
    description: "Company-wide executive views are included on Growth and Custom. Choose a plan below to unlock this area.",
  },
  "/dashboard/cognitive": {
    title: "Cognitive insights live on Growth",
    description: "Reasoning and investigation tools are included on Growth and Custom. Choose a plan below to unlock this area.",
  },
  "/dashboard/agent-ops": {
    title: "Agent ops lives on Growth",
    description: "Agent operations and monitoring are included on Growth and Custom. Choose a plan below to unlock this area.",
  },
  "/dashboard/mission-control": {
    title: "Mission Control lives on Growth",
    description: "Mission Control is included on Growth and Custom. Choose a plan below to unlock this area.",
  },
  "/dashboard/marketplace": {
    title: "Marketplace lives on Growth",
    description: "Marketplace tools are included on Growth and Custom. Choose a plan below to unlock this area.",
  },
}

/** Replaces Growth-only dashboard routes with the live plans comparison for Starter merchants. */
export function StarterGrowthRouteGate({ children }: { children: React.ReactNode }) {
  const pathname = usePathname()
  const path = pathname.split("?")[0]
  const { data: subscription, isLoading } = useSubscription()
  const gated = GROWTH_ONLY_HREFS.has(path)

  if (!gated) return children
  if (isLoading && !subscription) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    )
  }
  if (!isStarterPlan(subscription?.plan)) return children

  const copy = GROWTH_ROUTE_COPY[path] ?? {
    title: "This area lives on Growth",
    description: "Your current plan does not include this feature. Choose a plan below to unlock it.",
  }

  return (
    <LockedFeatureGate
      title={copy.title}
      description={copy.description}
      planLabel={subscription?.planName ?? "Starter"}
    />
  )
}

/** Slim usage bar with inline upgrade hint once the plan limit is approached. */
export function PlanLimitBar({
  used,
  limit,
  label,
  unit = "used",
}: {
  used: number
  limit: number | null | undefined
  label: string
  unit?: string
}) {
  if (limit == null) {
    return (
      <p className="text-xs text-muted-foreground">
        {label}: {used} {unit} · unlimited on your plan
      </p>
    )
  }
  const pct = limitPercent(used, limit)
  const near = pct >= 80
  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between text-xs">
        <span className="font-medium text-foreground">
          {label}: {used} of {limit} {unit}
        </span>
        {near ? (
          <Link
            href="/dashboard/subscription#plans"
            className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
          >
            {pct >= 100 ? "Limit reached — view Growth" : "Almost full — view Growth"}
            <ArrowRight className="h-3 w-3" />
          </Link>
        ) : (
          <span className="text-muted-foreground">{100 - pct}% left on Starter</span>
        )}
      </div>
      <Progress value={pct} className={cn("h-1.5", near && "[&>div]:bg-amber-500")} />
    </div>
  )
}
