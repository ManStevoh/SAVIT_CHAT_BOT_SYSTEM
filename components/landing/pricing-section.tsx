"use client"

import { useState, useEffect } from "react"
import Link from "next/link"
import { Button } from "@/components/ui/button"
import { Check } from "lucide-react"
import { usePlans } from "@/lib/api-hooks"
import { createCheckoutSession } from "@/lib/api-actions"
import { getAuthToken } from "@/lib/api-client"

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
      alert(result.message ?? "Could not start checkout.")
    }
  }

  return (
    <section id="pricing" className="py-20 lg:py-32 bg-card/30 border-y border-border/50">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-16">
          <h2 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
            Simple, transparent pricing
          </h2>
          <p className="mt-4 text-lg text-muted-foreground max-w-2xl mx-auto">
            Choose the plan that fits your business. All plans include a 14-day free trial.
          </p>
        </div>

        {isLoading && list.length === 0 ? (
          <div className="flex justify-center py-12">
            <span className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
          </div>
        ) : error ? (
          <div className="text-center py-12 text-muted-foreground">
            Unable to load pricing. Please try again later.
          </div>
        ) : (
        <div className="grid gap-8 lg:grid-cols-3">
          {list.map((plan) => {
            const canCheckout = plan.checkoutAvailable && isLoggedIn
            const showContactSales = !plan.checkoutAvailable
            const ctaText = plan.cta ?? "Start Free Trial"

            return (
              <div
                key={plan.id}
                className={`relative rounded-2xl border ${
                  plan.popular
                    ? "border-primary bg-card shadow-xl shadow-primary/10"
                    : "border-border bg-card"
                } p-8 transition-all hover:shadow-lg`}
              >
                {plan.popular && (
                  <div className="absolute -top-4 left-1/2 -translate-x-1/2">
                    <span className="inline-flex items-center rounded-full bg-primary px-4 py-1 text-sm font-medium text-primary-foreground">
                      Most Popular
                    </span>
                  </div>
                )}

                <div className="text-center mb-8">
                  <h3 className="text-xl font-semibold text-foreground">{plan.name}</h3>
                  <div className="mt-4">
                    <span className="text-4xl font-bold text-foreground">{plan.price ?? plan.priceDisplay}</span>
                    {(plan.price ?? plan.priceDisplay) !== "Custom" && (
                      <span className="text-muted-foreground">/month</span>
                    )}
                  </div>
                  <p className="mt-2 text-sm text-muted-foreground">{plan.description}</p>
                </div>

                <ul className="space-y-3 mb-8">
                  {(plan.features ?? []).map((feature) => (
                    <li key={feature} className="flex items-center gap-3">
                      <Check className="h-5 w-5 text-primary shrink-0" />
                      <span className="text-sm text-muted-foreground">{feature}</span>
                    </li>
                  ))}
                </ul>

                {canCheckout ? (
                  <Button
                    className="w-full"
                    variant={plan.popular ? "default" : "outline"}
                    disabled={checkoutPlanId !== null}
                    onClick={() => handleSubscribe(plan.id)}
                  >
                    {checkoutPlanId === plan.id ? "Redirecting…" : ctaText}
                  </Button>
                ) : showContactSales ? (
                  <div className="space-y-2">
                    <Button asChild className="w-full" variant={plan.popular ? "default" : "outline"}>
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
                    <Button asChild className="w-full" variant={plan.popular ? "default" : "outline"}>
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
            )
          })}
        </div>
        )}
      </div>
    </section>
  )
}
