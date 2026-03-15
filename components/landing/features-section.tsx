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
    description: "Deploy intelligent AI chatbots that understand context and provide human-like responses to customer inquiries 24/7.",
  },
  {
    icon: ShoppingCart,
    title: "WhatsApp Order Management",
    description: "Process orders directly through WhatsApp. Customers can browse products, place orders, and track deliveries seamlessly.",
  },
  {
    icon: Users,
    title: "Customer CRM",
    description: "Manage all your customer relationships in one place. Track conversations, purchase history, and preferences.",
  },
  {
    icon: MessageCircleQuestion,
    title: "Smart FAQ Bot",
    description: "Train your bot with FAQs and let it handle repetitive questions automatically, freeing up your team for complex issues.",
  },
  {
    icon: BarChart3,
    title: "Analytics Dashboard",
    description: "Get insights into customer behavior, message volumes, response times, and sales performance with detailed analytics.",
  },
  {
    icon: Inbox,
    title: "Multi-Agent Inbox",
    description: "Enable multiple team members to handle conversations simultaneously with smart routing and collision detection.",
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
            Powerful features designed to help businesses scale their customer engagement without scaling their team.
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
