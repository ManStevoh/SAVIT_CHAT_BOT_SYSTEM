import { Inbox, ShoppingBag, BarChart3 } from "lucide-react"
import { SectionHeader } from "@/components/shared/section-header"
import { FadeIn } from "@/components/shared/fade-in"
import { InboxMockup, OrdersMockup, AnalyticsMockup } from "@/components/landing/product-mockups"

const screenshots = [
  {
    title: "Unified inbox",
    description: "All customer conversations in one place with AI suggestions and agent handoff.",
    icon: Inbox,
    Mockup: InboxMockup,
  },
  {
    title: "Orders dashboard",
    description: "Track orders in real time with status updates and customer notifications.",
    icon: ShoppingBag,
    Mockup: OrdersMockup,
  },
  {
    title: "Analytics",
    description: "Insights on messages, orders, and customer behavior at a glance.",
    icon: BarChart3,
    Mockup: AnalyticsMockup,
  },
]

export function ProductScreenshots() {
  return (
    <section id="demo" className="section-padding">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <FadeIn>
          <SectionHeader
            label="Product"
            title="Built for how you work"
            description="A complete suite of tools designed for modern WhatsApp businesses."
          />
        </FadeIn>

        <div className="grid gap-5 md:grid-cols-3">
          {screenshots.map((item, i) => (
            <FadeIn key={item.title} delay={i * 80}>
              <div className="group overflow-hidden rounded-xl border border-border/80 bg-card shadow-sm transition-all duration-300 hover:shadow-premium">
                <div className="relative aspect-[4/3] overflow-hidden border-b border-border/60 bg-muted/30">
                  <div className="absolute inset-3 overflow-hidden rounded-lg shadow-premium ring-1 ring-border/60">
                    <item.Mockup />
                  </div>
                </div>
                <div className="p-5">
                  <div className="mb-1.5 flex items-center gap-2">
                    <item.icon className="h-4 w-4 text-muted-foreground" strokeWidth={1.75} />
                    <h3 className="text-sm font-semibold text-foreground">{item.title}</h3>
                  </div>
                  <p className="text-sm leading-relaxed text-muted-foreground">{item.description}</p>
                </div>
              </div>
            </FadeIn>
          ))}
        </div>
      </div>
    </section>
  )
}
