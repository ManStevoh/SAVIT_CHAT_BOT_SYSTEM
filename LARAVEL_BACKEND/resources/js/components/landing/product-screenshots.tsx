import { Inbox, ShoppingBag, BarChart3 } from "lucide-react"
import { SectionHeader } from "@/components/shared/section-header"
import { InboxMockup, OrdersMockup, AnalyticsMockup } from "@/components/landing/product-mockups"

const screenshots = [
  {
    title: "Team inbox",
    description: "All chats in one view. AI suggestions and one-click agent takeover.",
    icon: Inbox,
    Mockup: InboxMockup,
  },
  {
    title: "Orders",
    description: "Real-time order status with automatic customer updates.",
    icon: ShoppingBag,
    Mockup: OrdersMockup,
  },
  {
    title: "Analytics",
    description: "Messages, orders, and response times at a glance.",
    icon: BarChart3,
    Mockup: AnalyticsMockup,
  },
]

export function ProductScreenshots() {
  return (
    <section id="demo" className="section-padding">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <SectionHeader
          label="Product"
          title="See what you get"
          description="Inbox, orders, and analytics — the three screens you'll use every day."
        />

        <div className="grid gap-5 md:grid-cols-3">
          {screenshots.map((item) => (
            <div key={item.title} className="landing-card overflow-hidden">
              <div className="relative aspect-[4/3] overflow-hidden border-b border-border/70 bg-muted/30">
                <div className="absolute inset-3 overflow-hidden rounded-md border border-border/60">
                  <item.Mockup />
                </div>
              </div>
              <div className="p-5">
                <div className="mb-1 flex items-center gap-2">
                  <item.icon className="h-4 w-4 text-primary" strokeWidth={1.75} />
                  <h3 className="text-sm font-semibold text-foreground">{item.title}</h3>
                </div>
                <p className="text-sm leading-relaxed text-muted-foreground">{item.description}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
