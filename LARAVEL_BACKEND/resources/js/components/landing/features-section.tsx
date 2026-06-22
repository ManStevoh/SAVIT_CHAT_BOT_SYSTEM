import {
  Bot,
  ShoppingCart,
  Users,
  MessageCircleQuestion,
  BarChart3,
  Inbox,
} from "lucide-react"
import { SectionHeader } from "@/components/shared/section-header"
import { FadeIn } from "@/components/shared/fade-in"
import { cn } from "@/lib/utils"

const features = [
  {
    icon: Bot,
    title: "AI Chatbot Automation",
    description:
      "Context-aware replies with conversation history, custom away messages, and seamless human escalation.",
    span: "lg:col-span-2",
    featured: true,
  },
  {
    icon: ShoppingCart,
    title: "Order Management",
    description:
      "Full order flow in chat with M-Pesa STK push or Stripe card payments.",
    span: "",
  },
  {
    icon: Users,
    title: "Customer CRM",
    description:
      "Unified conversations and purchase history with human takeover controls.",
    span: "",
  },
  {
    icon: MessageCircleQuestion,
    title: "Smart FAQ Bot",
    description:
      "Train on your FAQs to handle repetitive questions automatically.",
    span: "",
  },
  {
    icon: BarChart3,
    title: "Analytics Dashboard",
    description:
      "Message volumes, response times, and sales performance in one view.",
    span: "",
  },
  {
    icon: Inbox,
    title: "Multi-Agent Inbox",
    description:
      "Agents take over any chat; the bot pauses and resumes on demand.",
    span: "lg:col-span-2",
    featured: true,
  },
]

export function FeaturesSection() {
  return (
    <section id="features" className="section-padding">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <FadeIn>
          <SectionHeader
            label="Platform"
            title="Everything to run WhatsApp at scale"
            description="From AI replies and order flows to in-chat payments and human takeover — close sales without leaving the chat."
          />
        </FadeIn>

        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {features.map((feature, i) => (
            <FadeIn key={feature.title} delay={i * 60}>
              <div
                className={cn(
                  "group h-full rounded-xl border border-border/80 bg-card p-6 transition-all duration-300 hover:border-border hover:shadow-premium",
                  feature.span,
                  feature.featured && "lg:p-8"
                )}
              >
                <div className="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-muted text-foreground transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                  <feature.icon className="h-5 w-5" />
                </div>
                <h3 className="mb-2 text-base font-semibold text-foreground">
                  {feature.title}
                </h3>
                <p className="text-sm leading-relaxed text-muted-foreground">
                  {feature.description}
                </p>
              </div>
            </FadeIn>
          ))}
        </div>
      </div>
    </section>
  )
}
