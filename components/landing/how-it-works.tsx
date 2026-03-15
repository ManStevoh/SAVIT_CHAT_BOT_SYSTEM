import { MessageSquare, GraduationCap, Zap } from "lucide-react"

const steps = [
  {
    step: "01",
    icon: MessageSquare,
    title: "Connect WhatsApp",
    description: "Link your WhatsApp Business account in minutes. No technical expertise required.",
  },
  {
    step: "02",
    icon: GraduationCap,
    title: "Train Your AI Bot",
    description: "Upload your FAQs, product catalog, and business rules. Our AI learns and adapts to your needs.",
  },
  {
    step: "03",
    icon: Zap,
    title: "Start Automating",
    description: "Go live instantly. Your AI assistant handles conversations while you focus on growing your business.",
  },
]

export function HowItWorks() {
  return (
    <section className="py-20 lg:py-32 bg-card/30 border-y border-border/50">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-16">
          <h2 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
            Get started in minutes
          </h2>
          <p className="mt-4 text-lg text-muted-foreground max-w-2xl mx-auto">
            Three simple steps to transform your WhatsApp business communication
          </p>
        </div>

        <div className="grid gap-8 md:grid-cols-3">
          {steps.map((step, index) => (
            <div key={step.step} className="relative text-center">
              {index < steps.length - 1 && (
                <div className="absolute top-12 left-1/2 hidden w-full border-t-2 border-dashed border-border md:block" />
              )}
              
              <div className="relative z-10 mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-2xl border border-border bg-card shadow-lg">
                <step.icon className="h-10 w-10 text-primary" />
                <span className="absolute -top-3 -right-3 flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground">
                  {step.step}
                </span>
              </div>
              
              <h3 className="mb-2 text-xl font-semibold text-foreground">
                {step.title}
              </h3>
              <p className="text-muted-foreground max-w-xs mx-auto">
                {step.description}
              </p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
