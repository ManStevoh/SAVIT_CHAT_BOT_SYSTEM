import Link from "next/link"
import { Button } from "@/components/ui/button"
import { ArrowRight, Play, MessageSquare, Bot, ShoppingCart } from "lucide-react"

/** Static demo order for hero chat preview; replace with API data when endpoint is available. */
const DEMO_ORDER = { orderNumber: "1234", lines: [{ name: "Margherita", qty: 1, price: "12.99" }, { name: "Pepperoni", qty: 1, price: "14.99" }], total: "27.98" }

export function HeroSection() {
  return (
    <section className="relative overflow-hidden pt-32 pb-20 lg:pt-40 lg:pb-32">
      {/* Background gradient */}
      <div className="absolute inset-0 -z-10">
        <div className="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[600px] bg-primary/10 rounded-full blur-3xl" />
      </div>

      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid lg:grid-cols-2 gap-12 items-center">
          <div className="text-center lg:text-left">
            <div className="inline-flex items-center gap-2 rounded-full border border-border bg-card px-4 py-1.5 text-sm text-muted-foreground mb-6">
              <span className="flex h-2 w-2 rounded-full bg-primary animate-pulse" />
              Now with GPT-4 Integration
            </div>
            
            <h1 className="text-4xl font-bold tracking-tight text-foreground sm:text-5xl lg:text-6xl text-balance">
              Automate Your WhatsApp Business{" "}
              <span className="text-primary">With AI</span>
            </h1>
            
            <p className="mt-6 text-lg text-muted-foreground leading-relaxed max-w-xl mx-auto lg:mx-0">
              Context-aware AI, full order flows, and in-chat payments (M-Pesa or card) so you close sales automatically. Human takeover and CRM in one place—all on WhatsApp.
            </p>

            <div className="mt-10 flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
              <Button size="lg" asChild className="gap-2">
                <Link href="/register">
                  Start Free Trial
                  <ArrowRight className="h-4 w-4" />
                </Link>
              </Button>
              <Button size="lg" variant="outline" asChild className="gap-2">
                <Link href="#demo">
                  <Play className="h-4 w-4" />
                  Book Demo
                </Link>
              </Button>
            </div>

            <div className="mt-10 flex items-center gap-8 justify-center lg:justify-start">
              <div className="text-center">
                <div className="text-2xl font-bold text-foreground">10K+</div>
                <div className="text-sm text-muted-foreground">Active Users</div>
              </div>
              <div className="h-8 w-px bg-border" />
              <div className="text-center">
                <div className="text-2xl font-bold text-foreground">50M+</div>
                <div className="text-sm text-muted-foreground">Messages Sent</div>
              </div>
              <div className="h-8 w-px bg-border" />
              <div className="text-center">
                <div className="text-2xl font-bold text-foreground">99.9%</div>
                <div className="text-sm text-muted-foreground">Uptime</div>
              </div>
            </div>
          </div>

          <div className="relative">
            {/* Chat mockup */}
            <div className="relative mx-auto max-w-sm lg:max-w-md">
              <div className="rounded-2xl border border-border bg-card p-4 shadow-2xl">
                <div className="flex items-center gap-3 border-b border-border pb-3 mb-4">
                  <div className="h-10 w-10 rounded-full bg-primary/20 flex items-center justify-center">
                    <Bot className="h-5 w-5 text-primary" />
                  </div>
                  <div>
                    <div className="font-medium text-foreground">AI Assistant</div>
                    <div className="text-xs text-primary">Online</div>
                  </div>
                </div>
                
                <div className="space-y-3">
                  {/* Customer message */}
                  <div className="flex justify-end">
                    <div className="max-w-[80%] rounded-2xl rounded-br-md bg-primary px-4 py-2 text-sm text-primary-foreground">
                      Hi! I want to order 2 pizzas
                    </div>
                  </div>
                  
                  {/* AI response */}
                  <div className="flex justify-start">
                    <div className="max-w-[80%] rounded-2xl rounded-bl-md bg-secondary px-4 py-2 text-sm text-secondary-foreground">
                      {"Hello! I'd be happy to help you with your order. Which pizzas would you like?"}
                    </div>
                  </div>

                  {/* Customer message */}
                  <div className="flex justify-end">
                    <div className="max-w-[80%] rounded-2xl rounded-br-md bg-primary px-4 py-2 text-sm text-primary-foreground">
                      1 Margherita and 1 Pepperoni please
                    </div>
                  </div>

                  {/* AI response with order card */}
                  <div className="flex justify-start">
                    <div className="max-w-[85%] space-y-2">
                      <div className="rounded-2xl rounded-bl-md bg-secondary px-4 py-2 text-sm text-secondary-foreground">
                        {"Perfect! Here's your order:"}
                      </div>
                      <div className="rounded-xl border border-border bg-card p-3">
                        <div className="flex items-center gap-2 text-xs font-medium text-foreground mb-2">
                          <ShoppingCart className="h-3.5 w-3.5 text-primary" />
                          Order #{DEMO_ORDER.orderNumber}
                        </div>
                        <div className="space-y-1 text-xs text-muted-foreground">
                          {DEMO_ORDER.lines.map((line) => (
                            <div key={line.name} className="flex justify-between">
                              <span>{line.qty}x {line.name}</span>
                              <span>${line.price}</span>
                            </div>
                          ))}
                          <div className="border-t border-border pt-1 mt-2 flex justify-between font-medium text-foreground">
                            <span>Total</span>
                            <span>${DEMO_ORDER.total}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="mt-4 flex items-center gap-2 rounded-xl border border-border bg-secondary/50 px-3 py-2">
                  <MessageSquare className="h-4 w-4 text-muted-foreground" />
                  <span className="text-sm text-muted-foreground">Type a message...</span>
                </div>
              </div>

              {/* Decorative elements */}
              <div className="absolute -top-4 -right-4 h-20 w-20 rounded-2xl border border-border bg-card/50 backdrop-blur-sm flex items-center justify-center">
                <Bot className="h-8 w-8 text-primary" />
              </div>
              <div className="absolute -bottom-4 -left-4 h-16 w-16 rounded-2xl border border-border bg-card/50 backdrop-blur-sm flex items-center justify-center">
                <ShoppingCart className="h-6 w-6 text-primary" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}
