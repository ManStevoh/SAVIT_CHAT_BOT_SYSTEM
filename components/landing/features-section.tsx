import { 
  Bot, 
  ShoppingCart, 
  Users, 
  MessageCircleQuestion, 
  BarChart3, 
  Inbox 
} from "lucide-react"

const features = [
  {
    icon: Bot,
    title: "AI Chatbot Automation",
    description: "Context-aware AI that uses conversation history for natural replies. Custom fallback and away messages, working-hours support, and automatic escalation when customers ask for a human.",
  },
  {
    icon: ShoppingCart,
    title: "WhatsApp Order Management",
    description: "Full order flow in chat: product → quantity → address → confirm. Close sales automatically with M-Pesa (STK push) or card (Stripe); when payment is received the order is marked paid and the customer gets a WhatsApp confirmation. Your team gets email notifications for every new order.",
  },
  {
    icon: Users,
    title: "Customer CRM",
    description: "One place for all conversations and purchase history. Human takeover when needed, then hand the chat back to the bot. Customer names sync from WhatsApp for a clear view of who you're talking to.",
  },
  {
    icon: MessageCircleQuestion,
    title: "Smart FAQ Bot",
    description: "Train your bot with FAQs and improve matching beyond simple keywords. Handles repetitive questions so your team can focus on complex or high-value conversations.",
  },
  {
    icon: BarChart3,
    title: "Analytics Dashboard",
    description: "Message volumes, response times, and sales performance in one dashboard. See delivery and read status from WhatsApp and monitor usage against your plan limits.",
  },
  {
    icon: Inbox,
    title: "Multi-Agent Inbox",
    description: "Agents can take over any conversation; the bot pauses automatically. Use “Hand back to bot” when done. Subscription and per-plan message limits keep usage under control.",
  },
]

export function FeaturesSection() {
  return (
    <section id="features" className="py-20 lg:py-32">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-16">
          <h2 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl text-balance">
            Everything you need to automate WhatsApp
          </h2>
          <p className="mt-4 text-lg text-muted-foreground max-w-2xl mx-auto">
            From AI replies and full order flows to in-chat payments and human takeover—close sales and run WhatsApp at scale.
          </p>
        </div>

        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {features.map((feature) => (
            <div
              key={feature.title}
              className="group relative rounded-2xl border border-border bg-card p-6 transition-all hover:border-primary/50 hover:shadow-lg hover:shadow-primary/5"
            >
              <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                <feature.icon className="h-6 w-6" />
              </div>
              <h3 className="mb-2 text-lg font-semibold text-foreground">
                {feature.title}
              </h3>
              <p className="text-sm text-muted-foreground leading-relaxed">
                {feature.description}
              </p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
