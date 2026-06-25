import {
  Bot,
  ShoppingCart,
  Users,
  MessageCircleQuestion,
  BarChart3,
  Inbox,
} from "lucide-react"
import { SectionHeader } from "@/components/shared/section-header"
import { cn } from "@/lib/utils"

const features = [
  {
    icon: Bot,
    title: "AI replies",
    description:
      "Answers from your FAQs, products, and policies — with full conversation history.",
  },
  {
    icon: ShoppingCart,
    title: "Orders in chat",
    description: "Customers browse, order, and pay with M-Pesa STK push or Stripe.",
  },
  {
    icon: Inbox,
    title: "Team inbox",
    description: "Agents take over any thread. The bot pauses until you hand it back.",
  },
  {
    icon: MessageCircleQuestion,
    title: "FAQ automation",
    description: "Stop answering the same questions. Train once, reply forever.",
  },
  {
    icon: Users,
    title: "Customer history",
    description: "Every conversation and purchase in one place per customer.",
  },
  {
    icon: BarChart3,
    title: "Analytics",
    description: "Message volume, response time, and sales in a single dashboard.",
  },
]

export function FeaturesSection() {
  return (
    <section id="features" className="section-padding">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <SectionHeader
          label="What you get"
          title="One platform for WhatsApp sales"
          description="Not just a chatbot — orders, payments, inbox, and analytics wired together."
        />

        <div className="grid gap-px overflow-hidden rounded-lg border border-border/70 bg-border/70 md:grid-cols-2 lg:grid-cols-3">
          {features.map((feature) => (
            <div
              key={feature.title}
              className={cn("bg-card p-6 lg:p-7")}
            >
              <feature.icon className="mb-4 h-5 w-5 text-primary" strokeWidth={1.75} />
              <h3 className="mb-1.5 text-base font-semibold text-foreground">{feature.title}</h3>
              <p className="text-sm leading-relaxed text-muted-foreground">{feature.description}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
