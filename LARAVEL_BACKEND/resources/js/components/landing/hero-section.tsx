import Link from "next/link"
import { Button } from "@/components/ui/button"
import { ArrowRight, Play, ShoppingCart } from "lucide-react"
import { FadeIn } from "@/components/shared/fade-in"

const DEMO_ORDER = {
  orderNumber: "1234",
  lines: [
    { name: "Margherita", qty: 1, price: "KES 1,299" },
    { name: "Pepperoni", qty: 1, price: "KES 1,499" },
  ],
  total: "KES 2,798",
}

export function HeroSection() {
  return (
    <section className="relative overflow-hidden pt-28 pb-20 lg:pt-36 lg:pb-28 grain">
      <div className="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_80%_60%_at_50%_-20%,oklch(0.42_0.11_255/0.08),transparent)]" />

      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid items-center gap-16 lg:grid-cols-2 lg:gap-12">
          <FadeIn className="text-center lg:text-left">
            <p className="mb-5 inline-flex items-center gap-2 rounded-full border border-border/80 bg-card px-3.5 py-1 text-xs font-medium tracking-wide text-muted-foreground shadow-sm">
              WhatsApp · M-Pesa · Stripe
            </p>

            <h1 className="font-display text-4xl font-normal leading-[1.1] text-foreground sm:text-5xl lg:text-[3.25rem]">
              Turn WhatsApp into your{" "}
              <span className="text-gradient">best sales channel</span>
            </h1>

            <p className="mx-auto mt-6 max-w-lg text-base leading-relaxed text-muted-foreground lg:mx-0 lg:text-lg">
              AI replies, full order flows, and in-chat payments — so you close sales automatically while your team stays in control.
            </p>

            <div className="mt-9 flex flex-col justify-center gap-3 sm:flex-row lg:justify-start">
              <Button size="lg" asChild className="h-11 gap-2 rounded-lg px-6 shadow-premium">
                <Link href="/register">
                  Start free trial
                  <ArrowRight className="h-4 w-4" />
                </Link>
              </Button>
              <Button size="lg" variant="outline" asChild className="h-11 gap-2 rounded-lg px-6">
                <Link href="#demo">
                  <Play className="h-4 w-4" />
                  Book a demo
                </Link>
              </Button>
            </div>

            <p className="mt-6 text-xs text-muted-foreground">
              14-day free trial · No credit card required
            </p>
          </FadeIn>

          <FadeIn delay={150} direction="up">
            <div className="relative mx-auto max-w-[340px] lg:max-w-sm">
              <div className="overflow-hidden rounded-2xl shadow-premium-lg ring-1 ring-black/5">
                <div
                  className="flex items-center gap-3 px-4 py-3"
                  style={{ backgroundColor: "var(--wa-header)" }}
                >
                  <div className="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-sm font-semibold text-white">
                    SC
                  </div>
                  <div>
                    <div className="text-sm font-medium text-white">Savit Assistant</div>
                    <div className="text-xs text-white/70">online</div>
                  </div>
                </div>

                <div className="space-y-3 p-4" style={{ backgroundColor: "var(--wa-bg)" }}>
                  <div className="flex justify-end">
                    <div
                      className="max-w-[85%] rounded-lg rounded-br-sm px-3 py-2 text-[13px] leading-snug text-foreground shadow-sm"
                      style={{ backgroundColor: "var(--wa-bubble-out)" }}
                    >
                      Hi! I want to order 2 pizzas
                    </div>
                  </div>

                  <div className="flex justify-start">
                    <div
                      className="max-w-[85%] rounded-lg rounded-bl-sm px-3 py-2 text-[13px] leading-snug text-foreground shadow-sm"
                      style={{ backgroundColor: "var(--wa-bubble-in)" }}
                    >
                      {"I'd be happy to help. Which pizzas would you like?"}
                    </div>
                  </div>

                  <div className="flex justify-end">
                    <div
                      className="max-w-[85%] rounded-lg rounded-br-sm px-3 py-2 text-[13px] leading-snug text-foreground shadow-sm"
                      style={{ backgroundColor: "var(--wa-bubble-out)" }}
                    >
                      1 Margherita and 1 Pepperoni please
                    </div>
                  </div>

                  <div className="flex justify-start">
                    <div className="max-w-[90%] space-y-2">
                      <div
                        className="rounded-lg rounded-bl-sm px-3 py-2 text-[13px] leading-snug text-foreground shadow-sm"
                        style={{ backgroundColor: "var(--wa-bubble-in)" }}
                      >
                        {"Perfect! Here's your order:"}
                      </div>
                      <div className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-black/5">
                        <div className="flex items-center gap-2 border-b border-border/60 px-3 py-2 text-xs font-medium text-foreground">
                          <ShoppingCart className="h-3.5 w-3.5 text-accent" />
                          Order #{DEMO_ORDER.orderNumber}
                        </div>
                        <div className="space-y-1 px-3 py-2.5 text-xs text-muted-foreground">
                          {DEMO_ORDER.lines.map((line) => (
                            <div key={line.name} className="flex justify-between">
                              <span>{line.qty}× {line.name}</span>
                              <span className="tabular-nums">{line.price}</span>
                            </div>
                          ))}
                          <div className="mt-2 flex justify-between border-t border-border/60 pt-2 font-medium text-foreground">
                            <span>Total</span>
                            <span className="tabular-nums">{DEMO_ORDER.total}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </FadeIn>
        </div>
      </div>
    </section>
  )
}
