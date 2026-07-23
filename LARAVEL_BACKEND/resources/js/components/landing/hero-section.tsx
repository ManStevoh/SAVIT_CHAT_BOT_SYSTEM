import Link from "next/link"
import { Button } from "@/components/ui/button"
import { ArrowRight, ShoppingCart } from "lucide-react"

const DEMO_ORDER = {
  orderNumber: "1234",
  lines: [
    { name: "Margherita", qty: 1, price: "KES 1,299" },
    { name: "Pepperoni", qty: 1, price: "KES 1,499" },
  ],
  total: "KES 2,798",
}

const HERO_POINTS = [
  "Official WhatsApp Business API",
  "M-Pesa & Stripe in chat",
  "14-day free trial",
]

export function HeroSection() {
  return (
    <section className="border-b border-border/70 bg-background pt-28 pb-16 lg:pt-32 lg:pb-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
          <div className="text-center lg:text-left">
            <p className="landing-kicker mb-4">WhatsApp commerce platform</p>

            <h1 className="landing-headline">
              Sell on WhatsApp.
              <br />
              <span className="text-primary">Get paid in the chat.</span>
            </h1>

            <p className="landing-subhead mx-auto mt-5 max-w-lg lg:mx-0">
              AI handles replies and orders. Your team steps in when needed. Customers pay with
              M-Pesa or card without leaving the conversation.
            </p>

            <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row lg:justify-start">
              <Button size="lg" asChild className="h-11 gap-2 rounded-md px-6 wa-cta border-0 shadow-none">
                <Link href="/register">
                  Start free trial
                  <ArrowRight className="h-4 w-4" />
                </Link>
              </Button>
              <Button size="lg" variant="outline" asChild className="h-11 rounded-md px-6">
                <Link href="#demo">View product</Link>
              </Button>
            </div>

            <ul className="mt-8 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm text-muted-foreground lg:justify-start">
              {HERO_POINTS.map((point) => (
                <li key={point} className="flex items-center gap-2">
                  <span className="h-1.5 w-1.5 rounded-full bg-primary" />
                  {point}
                </li>
              ))}
            </ul>
          </div>

          <div className="relative mx-auto w-full max-w-[340px] lg:max-w-sm">
            <div className="overflow-hidden rounded-xl border border-border/70 shadow-sm">
              <div
                className="flex items-center gap-3 px-4 py-3"
                style={{ backgroundColor: "var(--wa-header)" }}
              >
                <div className="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-sm font-semibold text-white">
                  EC
                </div>
                <div>
                  <div className="text-sm font-medium text-white">RelayIQ Assistant</div>
                  <div className="text-xs text-white/70">online</div>
                </div>
              </div>

              <div className="space-y-3 p-4" style={{ backgroundColor: "var(--wa-bg)" }}>
                <div className="flex justify-end">
                  <div
                    className="max-w-[85%] rounded-lg rounded-br-sm px-3 py-2 text-[13px] leading-snug text-foreground"
                    style={{ backgroundColor: "var(--wa-bubble-out)" }}
                  >
                    Hi! I want to order 2 pizzas
                  </div>
                </div>

                <div className="flex justify-start">
                  <div
                    className="max-w-[85%] rounded-lg rounded-bl-sm px-3 py-2 text-[13px] leading-snug text-foreground"
                    style={{ backgroundColor: "var(--wa-bubble-in)" }}
                  >
                    {"I'd be happy to help. Which pizzas would you like?"}
                  </div>
                </div>

                <div className="flex justify-end">
                  <div
                    className="max-w-[85%] rounded-lg rounded-br-sm px-3 py-2 text-[13px] leading-snug text-foreground"
                    style={{ backgroundColor: "var(--wa-bubble-out)" }}
                  >
                    1 Margherita and 1 Pepperoni please
                  </div>
                </div>

                <div className="flex justify-start">
                  <div className="max-w-[90%] space-y-2">
                    <div
                      className="rounded-lg rounded-bl-sm px-3 py-2 text-[13px] leading-snug text-foreground"
                      style={{ backgroundColor: "var(--wa-bubble-in)" }}
                    >
                      {"Perfect! Here's your order:"}
                    </div>
                    <div className="overflow-hidden rounded-lg border border-border/60 bg-white">
                      <div className="flex items-center gap-2 border-b border-border/60 px-3 py-2 text-xs font-medium text-foreground">
                        <ShoppingCart className="h-3.5 w-3.5 text-primary" />
                        Order #{DEMO_ORDER.orderNumber}
                      </div>
                      <div className="space-y-1 px-3 py-2.5 text-xs text-muted-foreground">
                        {DEMO_ORDER.lines.map((line) => (
                          <div key={line.name} className="flex justify-between">
                            <span>
                              {line.qty}× {line.name}
                            </span>
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
        </div>
      </div>
    </section>
  )
}
