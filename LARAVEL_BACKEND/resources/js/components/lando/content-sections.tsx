import { Star } from "lucide-react"
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion"
import { Button } from "@/components/ui/button"
import Link from "next/link"
import { usePlans } from "@/lib/api-hooks"
import { Check } from "lucide-react"
import { cn } from "@/lib/utils"
import { useState, useEffect } from "react"
import { createCheckoutSession } from "@/lib/api-actions"
import { getAuthToken } from "@/lib/api-client"
import { toast } from "sonner"

export function LandoTrustedCompanies({
  title,
  companies = [],
}: {
  title?: string
  companies?: Array<{ name: string; logoUrl?: string } | string>
}) {
  const parsed = companies.map((c) =>
    typeof c === "string" ? { name: c, logoUrl: "" } : c
  )

  return (
    <section className="bg-[#f3f4f6] py-12">
      <div className="mx-auto max-w-6xl px-4 text-center sm:px-6 lg:px-8">
        {title && <p className="text-sm text-gray-600">{title}</p>}
        <div className="mt-8 flex flex-wrap items-center justify-center gap-8 lg:gap-12">
          {parsed.map((company) => (
            <div key={company.name} className="flex items-center gap-2">
              {company.logoUrl ? (
                <img src={company.logoUrl} alt={company.name} className="h-8 max-w-[120px] object-contain opacity-60" />
              ) : (
                <span className="text-lg font-bold text-gray-400">{company.name}</span>
              )}
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

export function LandoTestimonials({
  title,
  description,
  testimonials = [],
}: {
  title?: string
  description?: string
  testimonials?: Array<{ id: string; name: string; role: string; content: string; rating: number }>
}) {
  if (testimonials.length === 0) return null

  return (
    <section className="bg-[#f3f4f6] py-16 lg:py-24">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div className="text-center">
          {title && <h2 className="text-3xl font-bold text-black sm:text-4xl">{title}</h2>}
          {description && <p className="mt-3 text-gray-600">{description}</p>}
        </div>
        <div className="mt-12 grid gap-6 md:grid-cols-3">
          {testimonials.slice(0, 3).map((t) => (
            <div key={t.id} className="rounded-2xl bg-white p-8 text-center shadow-sm">
              <p className="text-base leading-relaxed text-black">&ldquo;{t.content}&rdquo;</p>
              <div className="mt-4 flex justify-center gap-0.5">
                {Array.from({ length: t.rating || 5 }).map((_, i) => (
                  <Star key={i} className="h-4 w-4 fill-[#2563eb] text-[#2563eb]" />
                ))}
              </div>
              <p className="mt-4 font-bold text-black">{t.name}</p>
              {t.role && <p className="text-sm text-gray-500">{t.role}</p>}
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

export function LandoPricingPlans({ popularBadge = "Most Popular" }: { popularBadge?: string }) {
  const { data: plans, isLoading } = usePlans()
  const [isLoggedIn, setIsLoggedIn] = useState(false)
  const [busy, setBusy] = useState<string | null>(null)

  useEffect(() => {
    setIsLoggedIn(!!getAuthToken())
  }, [])

  const list = plans ?? []

  const handleSubscribe = async (planId: string) => {
    setBusy(planId)
    const result = await createCheckoutSession(planId)
    setBusy(null)
    if (result.success && result.url) {
      window.location.href = result.url
    } else {
      toast.error(result.message ?? "Could not start checkout.")
    }
  }

  if (isLoading && list.length === 0) {
    return (
      <div className="flex justify-center py-16">
        <span className="h-8 w-8 animate-spin rounded-full border-2 border-[#2563eb] border-t-transparent" />
      </div>
    )
  }

  return (
    <section className="bg-[#f3f4f6] pb-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div className="grid gap-6 lg:grid-cols-3">
          {list.map((plan) => (
            <div
              key={plan.id}
              className={cn(
                "relative rounded-2xl bg-white p-8 shadow-sm",
                plan.popular && "ring-2 ring-[#2563eb]"
              )}
            >
              {plan.popular && (
                <span className="absolute -top-3 right-6 rounded-full bg-[#2563eb] px-3 py-1 text-xs font-medium text-white">
                  {popularBadge}
                </span>
              )}
              <h3 className="text-xl font-bold text-black">{plan.name}</h3>
              <p className="mt-4 text-4xl font-bold text-black">{plan.price}</p>
              <p className="mt-2 text-sm text-gray-600">{plan.description}</p>
              <ul className="mt-6 space-y-3">
                {plan.features.map((f) => (
                  <li key={f} className="flex items-start gap-2 text-sm text-gray-700">
                    <Check className="mt-0.5 h-4 w-4 shrink-0 text-[#2563eb]" />
                    {f}
                  </li>
                ))}
              </ul>
              <Button
                className={cn(
                  "mt-8 w-full rounded-lg",
                  plan.popular
                    ? "bg-[#2563eb] text-white hover:bg-[#1d4ed8]"
                    : "border-black bg-white text-black hover:bg-gray-50"
                )}
                variant={plan.popular ? "default" : "outline"}
                disabled={busy !== null}
                onClick={() => {
                  if (plan.checkoutAvailable && isLoggedIn) {
                    handleSubscribe(plan.id)
                  } else if (!isLoggedIn) {
                    window.location.href = "/register"
                  }
                }}
              >
                {plan.cta ?? "Get started"}
              </Button>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

export function LandoCompareFeatures({
  title = "Compare Features",
  columns = [],
}: {
  title?: string
  columns?: Array<{ name: string; features: string[] }>
}) {
  if (columns.length === 0) return null

  return (
    <section className="bg-[#f3f4f6] py-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <h2 className="text-3xl font-bold text-black">{title}</h2>
        <div className="mt-8 overflow-hidden rounded-2xl bg-white shadow-sm">
          <div className="grid border-b border-gray-200 md:grid-cols-3">
            {columns.map((col) => (
              <div key={col.name} className="border-gray-200 p-6 font-bold text-black md:border-r last:md:border-r-0">
                {col.name}
              </div>
            ))}
          </div>
          <div className="grid md:grid-cols-3">
            {columns.map((col) => (
              <div key={col.name} className="space-y-4 border-gray-200 p-6 md:border-r last:md:border-r-0">
                {col.features.map((f) => (
                  <div key={f} className="flex items-center gap-2 text-sm text-gray-700">
                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-[#2563eb] text-white">
                      <Check className="h-3 w-3" />
                    </span>
                    {f}
                  </div>
                ))}
              </div>
            ))}
