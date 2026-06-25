import { SectionHeader } from "@/components/shared/section-header"

const steps = [
  {
    step: "01",
    title: "Connect WhatsApp",
    description: "Link your business number through Meta. Most teams are live in under 10 minutes.",
  },
  {
    step: "02",
    title: "Add your catalog",
    description: "Upload products, FAQs, and business rules. Pick your AI model and tone.",
  },
  {
    step: "03",
    title: "Start selling",
    description: "AI replies instantly. Your team takes over when a human touch is needed.",
  },
]

export function HowItWorks() {
  return (
    <section className="section-padding landing-divider bg-muted/20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <SectionHeader
          label="Setup"
          title="Live in three steps"
          description="No developers required. Connect, configure, and start receiving orders."
        />

        <div className="grid gap-8 md:grid-cols-3">
          {steps.map((step) => (
            <div key={step.step} className="border-l-2 border-primary pl-5">
              <p className="text-sm font-semibold tabular-nums text-primary">{step.step}</p>
              <h3 className="mt-2 text-lg font-semibold text-foreground">{step.title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{step.description}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
