import { UtensilsCrossed, ShoppingBag, Wrench, Store } from "lucide-react"
import { SectionHeader } from "@/components/shared/section-header"

const useCases = [
  {
    icon: UtensilsCrossed,
    title: "Restaurants",
    description: "Menu orders, delivery updates, and M-Pesa payment in one thread.",
  },
  {
    icon: ShoppingBag,
    title: "Retail",
    description: "Product questions, stock checks, and checkout without a separate app.",
  },
  {
    icon: Wrench,
    title: "Services",
    description: "Quote requests, booking FAQs, and lead routing to your sales team.",
  },
  {
    icon: Store,
    title: "Local shops",
    description: "After-hours replies about hours, location, and promotions.",
  },
]

export function UseCasesSection() {
  return (
    <section id="use-cases" className="section-padding landing-divider">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <SectionHeader
          label="Who it's for"
          title="Built for businesses that sell on WhatsApp"
          description="If your customers already message you, RelayIQ turns those chats into sales."
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {useCases.map((item) => (
            <div key={item.title} className="landing-card p-5">
              <item.icon className="mb-3 h-5 w-5 text-primary" strokeWidth={1.75} />
              <h3 className="font-semibold text-foreground">{item.title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{item.description}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
