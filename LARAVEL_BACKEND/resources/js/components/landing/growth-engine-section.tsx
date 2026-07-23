import Link from "next/link"
import { ArrowRight, BarChart2, Link2, Megaphone } from "lucide-react"
import { Button } from "@/components/ui/button"
import { SectionHeader } from "@/components/shared/section-header"

const highlights = [
  {
    icon: Link2,
    title: "Attribution links",
    description: "Know which Instagram or Facebook post started each WhatsApp conversation.",
  },
  {
    icon: Megaphone,
    title: "AI content drafts",
    description: "Generate post ideas in your brand voice, then publish from the dashboard.",
  },
  {
    icon: BarChart2,
    title: "Campaign reporting",
    description: "Clicks → chats → orders. See what actually drives revenue.",
  },
]

export function GrowthEngineSection() {
  return (
    <section id="growth" className="section-padding landing-divider bg-muted/20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid items-start gap-10 lg:grid-cols-2 lg:gap-14">
          <div>
            <SectionHeader
              align="left"
              label="Growth Engine"
              title="Connect marketing to WhatsApp sales"
              description="Most chatbot tools stop at replies. RelayIQ tracks which campaigns turn into paid orders."
              className="mb-0"
            />

            <ul className="mt-8 space-y-5">
              {highlights.map((item) => (
                <li key={item.title} className="flex gap-3">
                  <item.icon className="mt-0.5 h-5 w-5 shrink-0 text-primary" strokeWidth={1.75} />
                  <div>
                    <h3 className="text-sm font-semibold text-foreground">{item.title}</h3>
                    <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                      {item.description}
                    </p>
                  </div>
                </li>
              ))}
            </ul>

            <Button asChild className="mt-8 gap-2 rounded-md wa-cta border-0 shadow-none">
              <Link href="/register">
                Try it free
                <ArrowRight className="h-4 w-4" />
              </Link>
            </Button>
          </div>

          <div className="landing-card overflow-hidden">
            <div className="border-b border-border/70 bg-muted/30 px-4 py-3">
              <p className="text-sm font-medium text-foreground">Campaign performance</p>
            </div>
            <div className="divide-y divide-border/70">
              {[
                { campaign: "Weekend promo — Instagram", clicks: 842, chats: 126, orders: 34 },
                { campaign: "New menu launch", clicks: 531, chats: 89, orders: 21 },
                { campaign: "Flash sale link", clicks: 1204, chats: 203, orders: 57 },
              ].map((row) => (
                <div key={row.campaign} className="grid grid-cols-[1fr_auto_auto_auto] items-center gap-4 px-4 py-3 text-sm">
                  <span className="font-medium text-foreground">{row.campaign}</span>
                  <span className="tabular-nums text-muted-foreground">{row.clicks} clicks</span>
                  <span className="tabular-nums text-muted-foreground">{row.chats} chats</span>
                  <span className="tabular-nums font-medium text-primary">{row.orders} orders</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}
