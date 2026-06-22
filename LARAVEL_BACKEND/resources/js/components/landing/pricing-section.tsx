"use client"

import { useState, useEffect } from "react"
import Link from "next/link"
import { toast } from "sonner"
import { Button } from "@/components/ui/button"
import { Check } from "lucide-react"
import { usePlans } from "@/lib/api-hooks"
import { createCheckoutSession } from "@/lib/api-actions"
import { getAuthToken } from "@/lib/api-client"
import { SectionHeader } from "@/components/shared/section-header"
import { FadeIn } from "@/components/shared/fade-in"
import { cn } from "@/lib/utils"

export function PricingSection() {
  const { data: plans, error, isLoading } = usePlans()
  const [isLoggedIn, setIsLoggedIn] = useState(false)
  const [checkoutPlanId, setCheckoutPlanId] = useState<string | null>(null)

  useEffect(() => {
    setIsLoggedIn(!!getAuthToken())
  }, [])

  const list = plans ?? []

  const handleSubscribe = async (planId: string) => {
    setCheckoutPlanId(planId)
    const result = await createCheckoutSession(planId)
    setCheckoutPlanId(null)
    if (result.success && result.url) {
      window.location.href = result.url
    } else {
      toast.error(result.message ?? "Could not start checkout.")
    }
  }

  return (
    <section id="pricing" className="section-padding surface-subtle border-y border-border/60">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <FadeIn>
          <SectionHeader
            label="Pricing"
            title="Simple, transparent pricing"
            description="Choose the plan that fits your business. All plans include a 14-day free trial."
          />
        </FadeIn>

        {isLoading && list.length === 0 ? (
          <div className="flex justify-center py-12">
            <span className="h-7 w-7 animate-spin rounded-full border-2 border-primary border-t-transparent" />
          </div>
        ) : error ? (
          <div className="py-12 text-center text-muted-foreground">
            Unable to load pricing. Please try again later.
          </div>
        ) : (
          <div className="grid gap-5 lg:grid-cols-3">
            {list.map((plan, i) => {
              const canCheckout = plan.checkoutAvailable && isLoggedIn
              const showContactSales = !plan.checkoutAvailable
              const ctaText = plan.cta ?? "Start free trial"

              return (
                <FadeIn key={plan.id} delay={i * 80}>
                  <div
                    className={cn(
                      "relative flex h-full flex-col rounded-xl border bg-card p-7 transition-all duration-300",
                      plan.popular
                        ? "border-primary/30 shadow-premium ring-1 ring-primary/10"
                        : "border-border/80 hover:shadow-premium"
                    )}
                  >
                    {plan.popular && (
                      <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span className="inline-flex items-center rounded-full bg-primary px-3 py-0.5 text-xs font-medium text-primary-foreground">
                          Most popular
                        </span>
                      </div>
                    )}

                    <div className="mb-7 text-center">
                      <h3 className="text-base font-semibold text-foreground">{plan.name}</h3>
                      <div className="mt-3">
                        <span className="text-4xl font-semibold tabular-nums tracking-tight text-foreground">
                          {plan.price ?? plan.priceDisplay}
                        </span>
                        {(plan.price ?? plan.priceDisplay) !== "Custom" && (
                          <span className="text-sm text-muted-foreground">/month</span>
                        )}
                      </div>
                      <p className="mt-2 text-sm text-muted-foreground">{plan.description}</p>
                    </div>

                    <ul className="mb-8 flex-1 space-y-2.5">
                      {(plan.features ?? []).map((feature) => (
                        <li key={feature} className="flex items-start gap-2.5">
                          <Check className="mt-0.5 h-4 w-4 shrink-0 text-accent" />
                          <span className="text-sm text-muted-foreground">{feature}</span>
                        </li>
                      ))}
                    </ul>

                    {canCheckout ? (
                      <Button
                        className="w-full rounded-lg"
                        variant={plan.popular ? "default" : "outline"}
                        disabled={checkoutPlanId !== null}
                        onClick={() => handleSubscribe(plan.id)}
                      >
                        {checkoutPlanId === plan.id ? "Redirecting…" : ctaText}
                      </Button>
                    ) : showContactSales ? (
                      <div className="space-y-2">
                        <Button asChild className="w-full rounded-lg" variant={plan.popular ? "default" : "outline"}>
                          <Link href={`/register?plan=${plan.id}`}>{ctaText}</Link>
                        </Button>
                        <p className="text-center text-xs text-muted-foreground">
                          Already have an account?{" "}
                          <Link href={`/login?plan=${plan.id}`} className="text-primary hover:underline">
                            Sign in
                          </Link>
                        </p>
                      </div>
                    ) : (
                      <div className="space-y-2">
                        <Button asChild className="w-full rounded-lg" variant={plan.popular ? "default" : "outline"}>
                          <Link href={`/register?plan=${plan.id}`}>{ctaText}</Link>
                        </Button>
                        <p className="text-center text-xs text-muted-foreground">
                          Already have an account?{" "}
                          <Link href={`/login?plan=${plan.id}`} className="text-primary hover:underline">
                            Sign in
                          </Link>
                        </p>
                      </div>
                    )}
                  </div>
                </FadeIn>
              )
            })}
          </div>
        )}
      </div>
    </section>
  )
}
