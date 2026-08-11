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
  const [currency, setCurrency] = useState<string | null>(null)
  const { data, error, isLoading } = usePlans(currency)
  const [isLoggedIn, setIsLoggedIn] = useState(false)
  const [checkoutPlanId, setCheckoutPlanId] = useState<string | null>(null)

  useEffect(() => {
    setIsLoggedIn(!!getAuthToken())
  }, [])

  const list = data?.plans ?? []
  const activeCurrency = data?.currency ?? currency ?? "KES"
  const currencies = data?.availableCurrencies?.length
    ? data.availableCurrencies
    : [
        { code: "KES", label: "Kenyan Shilling", symbol: "KSh" },
        { code: "USD", label: "US Dollar", symbol: "$" },
        { code: "NGN", label: "Nigerian Naira", symbol: "₦" },
      ]

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
    <section id="pricing" className="section-padding landing-divider bg-muted/20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <FadeIn>
          <SectionHeader
            label="Pricing"
            title="Straightforward plans"
            description="14-day free trial on every plan. Pick what fits your volume."
          />
        </FadeIn>

        <div className="mb-8 flex flex-col items-center gap-3">
          <div className="inline-flex rounded-lg border border-border/70 bg-card p-1">
            {currencies.map((c) => (
              <button
                key={c.code}
                type="button"
                onClick={() => setCurrency(c.code)}
                className={cn(
                  "rounded-md px-3 py-1.5 text-sm font-medium transition-colors",
                  activeCurrency === c.code
                    ? "bg-primary text-primary-foreground"
                    : "text-muted-foreground hover:text-foreground"
                )}
              >
                {c.code}
              </button>
            ))}
          </div>
          <p className="text-center text-xs text-muted-foreground">
            {data?.source === "cloudflare" || data?.source === "forced"
              ? `Showing ${activeCurrency} based on your location${data?.detectedCountry ? ` (${data.detectedCountry})` : ""}.`
              : `Showing prices in ${activeCurrency}.`}{" "}
            You can switch currency anytime.
          </p>
        </div>

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
              const showSubscribeActions = !plan.isFree && (plan.price ?? plan.priceDisplay) !== "Custom"
              const canCheckout = plan.checkoutAvailable && isLoggedIn
              const ctaText =
                plan.cta && !/contact/i.test(plan.cta) ? plan.cta : "Start Free Trial"

              return (
                <FadeIn key={plan.id} delay={i * 80}>
                  <div
                    className={cn(
                      "relative flex h-full flex-col rounded-lg border bg-card p-7",
                      plan.popular
                        ? "border-primary ring-1 ring-primary/20"
                        : "border-border/70"
                    )}
                  >
                    {plan.popular && (
                      <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span className="inline-flex items-center rounded-md bg-primary px-2.5 py-0.5 text-xs font-medium text-primary-foreground">
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
                          <Check className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                          <span className="text-sm text-muted-foreground">{feature}</span>
                        </li>
                      ))}
                    </ul>

                    {!showSubscribeActions ? (
                      <div className="space-y-2">
                        <Button asChild className="w-full rounded-lg" variant={plan.popular ? "default" : "outline"}>
                          <Link href={`/register?plan=${plan.id}`}>{plan.cta ?? "Contact Sales"}</Link>
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
                        {(plan.hasTrial ?? true) && (
                          <Button asChild className="w-full rounded-lg" variant={plan.popular ? "default" : "outline"}>
                            <Link href={isLoggedIn ? "/dashboard/subscription" : `/register?plan=${plan.id}`}>
                              {ctaText}
                            </Link>
                          </Button>
                        )}
                        {canCheckout ? (
                          <Button
                            className="w-full rounded-lg"
                            variant={plan.hasTrial === false && plan.popular ? "default" : "outline"}
                            disabled={checkoutPlanId !== null}
                            onClick={() => handleSubscribe(plan.id)}
                          >
                            {checkoutPlanId === plan.id ? "Redirecting…" : "Subscribe"}
                          </Button>
                        ) : (
                          <Button asChild className="w-full rounded-lg" variant="outline">
                            <Link
                              href={
                                isLoggedIn
                                  ? `/dashboard/subscription?subscribe=${plan.id}`
                                  : `/register?plan=${plan.id}&intent=subscribe`
                              }
                            >
                              Subscribe
                            </Link>
                          </Button>
                        )}
                        {!isLoggedIn && (
                          <p className="text-center text-xs text-muted-foreground">
                            Already have an account?{" "}
                            <Link href={`/login?plan=${plan.id}&pay=1`} className="text-primary hover:underline">
                              Sign in to pay
                            </Link>
                          </p>
                        )}
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
