// Central plan entitlement helper for the company dashboard.
// Single source of truth for Starter (free) limits in the UI.
// Backend truth: PlanSeeder + EntitlementService::DEFAULTS['free'].
"use client"

import { useSubscription } from "@/lib/api-hooks"

export const STARTER_LIMITS = {
  products: 20,
  tables: 5,
  bookingsPerMonth: 30,
  aiConversationsPerMonth: 0, // Starter is manual-only — AI lives on Growth
  whatsappNumbers: 0,
  teamMembers: 1,
} as const

export const GROWTH_HIGHLIGHTS = [
  "WhatsApp number connection",
  "50 products · 20 tables · 150 bookings/mo",
  "1,000 AI conversations/mo",
  "WhatsApp campaigns & Growth Engine",
  "M-Pesa, Paystack & Stripe",
] as const

export type PlanSlug = "free" | "professional" | "enterprise" | "commission" | string

export function normalizePlanSlug(slug?: string | null): string {
  return (slug ?? "free").toLowerCase()
}

export function isStarterPlan(slug?: string | null): boolean {
  return normalizePlanSlug(slug) === "free"
}

export function planDisplayName(slug?: string | null, fallback?: string | null): string {
  const s = normalizePlanSlug(slug)
  if (s === "free") return "Starter"
  if (s === "professional") return "Growth"
  if (s === "enterprise") return "Custom"
  if (s === "commission") return "Commission-on-Sales"
  return fallback ?? slug ?? "Plan"
}

/**
 * Plan-aware dashboard state.
 * - `isStarter`: KSh 0 plan (free slug) — storefront/bookings/dine-in, no WhatsApp.
 * - `needsUpgradeForWhatsapp`: starter always needs Growth for WhatsApp.
 */
export function usePlan() {
  const { data: subscription, ...rest } = useSubscription()
  const slug = normalizePlanSlug(subscription?.plan)
  const isStarter = isStarterPlan(slug)
  const isCommission = slug === "commission"

  return {
    ...rest,
    subscription,
    planSlug: slug,
    planName: planDisplayName(slug, subscription?.planName),
    isStarter,
    isGrowth: slug === "professional",
    isEnterprise: slug === "enterprise",
    isCommission,
    // Starter never includes WhatsApp numbers (limit 0) — Growth unlocks it.
    needsUpgradeForWhatsapp: isStarter,
    limits: {
      products: isStarter ? STARTER_LIMITS.products : null,
      tables: isStarter ? STARTER_LIMITS.tables : null,
      bookingsPerMonth: isStarter ? STARTER_LIMITS.bookingsPerMonth : null,
      aiConversations: isStarter ? STARTER_LIMITS.aiConversationsPerMonth : null,
    },
  }
}

export function limitPercent(used: number, limit: number | null | undefined): number {
  if (limit == null || limit <= 0) return 0
  return Math.min(100, Math.round((used / limit) * 100))
}

export function isNearLimit(used: number, limit: number | null | undefined, threshold = 80): boolean {
  if (limit == null || limit <= 0) return false
  return used / limit >= threshold / 100
}

export function isAtLimit(used: number, limit: number | null | undefined): boolean {
  if (limit == null || limit <= 0) return false
  return used >= limit
}
