import { SectionHeader } from "@/components/shared/section-header"
import { FadeIn } from "@/components/shared/fade-in"

const steps = [
  {
    step: "1",
    title: "Connect WhatsApp",
    description:
      "Link your WhatsApp Business account in minutes. No technical setup required.",
  },
  {
    step: "2",
    title: "Configure your bot",
    description:
      "Add FAQs, products, and business rules. The AI learns your catalog and tone.",
  },
  {
    step: "3",
    title: "Go live",
    description:
      "Start automating conversations. Your team can take over anytime.",
  },
]

export function HowItWorks() {
  return (
    <section className="section-padding surface-subtle border-y border-border/60">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <FadeIn>
          <SectionHeader
            label="How it works"
            title="Live in three steps"
            description="Three simple steps to transform your WhatsApp business communication."
          />
        </FadeIn>

        <div className="relative grid gap-10 md:grid-cols-3 md:gap-8">
          <div className="absolute top-5 left-[16.67%] right-[16.67%] hidden h-px bg-border md:block" />

          {steps.map((step, i) => (
            <FadeIn key={step.step} delay={i * 100}>
              <div className="relative text-center">
                <div className="relative z-10 mx-auto mb-5 flex h-10 w-10 items-center justify-center rounded-full border border-border bg-card text-sm font-semibold text-foreground shadow-sm">
                  {step.step}
                </div>
                <h3 className="mb-2 text-lg font-semibold text-foreground">
                  {step.title}
                </h3>
                <p className="mx-auto max-w-xs text-sm leading-relaxed text-muted-foreground">
                  {step.description}
                </p>
              </div>
            </FadeIn>
          ))}
        </div>
      </div>
    </section>
  )
}
