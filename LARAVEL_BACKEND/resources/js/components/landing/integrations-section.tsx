import { SectionHeader } from "@/components/shared/section-header"

const integrations = [
  {
    name: "WhatsApp Business API",
    description: "Official Cloud API — templates, webhooks, embedded signup.",
  },
  {
    name: "M-Pesa",
    description: "STK push payments for Kenyan customers, inside the chat flow.",
  },
  {
    name: "Stripe",
    description: "Card payments and subscription billing worldwide.",
  },
  {
    name: "OpenAI · Anthropic · Gemini",
    description: "Pick your AI provider per company. Usage tracked in dashboard.",
  },
  {
    name: "Growth Engine",
    description: "Attribution links from social posts to WhatsApp conversations.",
  },
  {
    name: "REST API",
    description: "Connect your own tools with authenticated API access.",
  },
]

export function IntegrationsSection() {
  return (
    <section id="integrations" className="section-padding landing-divider">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <SectionHeader
          label="Integrations"
          title="Payments, AI, and messaging in one place"
          description="No duct-taping five tools together. Everything routes through RelayIQ."
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {integrations.map((item) => (
            <div key={item.name} className="landing-card p-5">
              <h3 className="font-semibold text-foreground">{item.name}</h3>
              <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{item.description}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
