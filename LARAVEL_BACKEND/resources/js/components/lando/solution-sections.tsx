import Link from "next/link"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"
import {
  Bot,
  Package,
  CreditCard,
  CalendarDays,
  Megaphone,
  MessagesSquare,
  Store,
  QrCode,
  Truck,
  Sparkles,
  ArrowRight,
  Check,
  type LucideIcon,
} from "lucide-react"

const PILLAR_ICONS: Record<string, LucideIcon> = {
  bot: Bot,
  package: Package,
  payment: CreditCard,
  booking: CalendarDays,
  growth: Megaphone,
  inbox: MessagesSquare,
  store: Store,
  dinein: QrCode,
  delivery: Truck,
  sparkles: Sparkles,
}

export type SolutionPillar = {
  id?: string
  icon?: string
  label?: string
  title: string
  description?: string
  points?: string[]
  sampleTitle?: string
  sampleLines?: string[]
  ctaText?: string
  ctaHref?: string
}

export function LandoSolutionPillars({
  title,
  description,
  items = [],
}: {
  title?: string
  description?: string
  items?: SolutionPillar[]
}) {
  if (items.length === 0) return null

  return (
    <section id="pillars" className="bg-muted py-14 lg:py-20">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-3xl text-center">
          {title && <h2 className="text-3xl font-bold text-foreground sm:text-4xl">{title}</h2>}
          {description && (
            <p className="mt-4 text-base leading-relaxed text-muted-foreground sm:text-lg">{description}</p>
          )}
        </div>

        <div className="mt-12 space-y-8">
          {items.map((item, index) => {
            const Icon = PILLAR_ICONS[item.icon ?? ""] ?? Sparkles
            const reversed = index % 2 === 1
            return (
              <article
                key={item.id ?? item.title}
                id={item.id}
                className="overflow-hidden rounded-3xl border border-border bg-card shadow-sm"
              >
                <div className={cn("grid gap-0 lg:grid-cols-2", reversed && "lg:[&>*:first-child]:order-2")}>
                  <div className="p-8 lg:p-10">
                    {item.label && (
                      <p className="mb-3 text-xs font-bold tracking-widest text-primary uppercase">{item.label}</p>
                    )}
                    <div className="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                      <Icon className="h-5 w-5" aria-hidden />
                    </div>
                    <h3 className="text-2xl font-bold text-card-foreground sm:text-3xl">{item.title}</h3>
                    {item.description && (
                      <p className="mt-3 text-base leading-relaxed text-muted-foreground">{item.description}</p>
                    )}
                    {(item.points?.length ?? 0) > 0 && (
                      <ul className="mt-6 space-y-2.5">
                        {item.points!.map((point) => (
                          <li key={point} className="flex gap-2.5 text-sm text-muted-foreground">
                            <Check className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden />
                            <span>{point}</span>
                          </li>
                        ))}
                      </ul>
                    )}
                    {item.ctaText && item.ctaHref && (
                      <Button asChild className="mt-8 h-11 gap-2 rounded-lg bg-primary px-6 text-white hover:bg-primary/90">
                        <Link href={item.ctaHref}>
                          {item.ctaText}
                          <ArrowRight className="h-4 w-4" />
                        </Link>
                      </Button>
                    )}
                  </div>

                  <div className="border-t border-border bg-muted/60 p-6 lg:border-t-0 lg:border-l lg:p-8">
                    <p className="mb-4 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                      {item.sampleTitle ?? "Sample flow"}
                    </p>
                    <div className="space-y-3 rounded-2xl border border-border bg-background p-4 shadow-sm">
                      {(item.sampleLines ?? ["Customer asks → AI answers with catalog facts → Order or booking confirmed."]).map(
                        (line, i) => {
                          const isCustomer = i % 2 === 0
                          return (
                            <div key={`${item.title}-${i}`} className={cn("flex", isCustomer ? "justify-end" : "justify-start")}>
                              <div
                                className={cn(
                                  "max-w-[90%] rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed",
                                  isCustomer
                                    ? "rounded-br-md bg-primary text-primary-foreground"
                                    : "rounded-bl-md border border-border bg-card text-card-foreground"
                                )}
                              >
                                {line}
                              </div>
                            </div>
                          )
                        }
                      )}
                    </div>
                  </div>
                </div>
              </article>
            )
          })}
        </div>
      </div>
    </section>
  )
}

export type IndustryCard = {
  icon?: string
  title: string
  description?: string
  outcomes?: string[]
  ctaText?: string
  ctaHref?: string
}

export function LandoIndustries({
  title,
  description,
  items = [],
}: {
  title?: string
  description?: string
  items?: IndustryCard[]
}) {
  if (items.length === 0) return null

  return (
    <section id="industries" className="bg-muted py-14 lg:py-20">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-3xl text-center">
          {title && <h2 className="text-3xl font-bold text-foreground sm:text-4xl">{title}</h2>}
          {description && (
            <p className="mt-4 text-base leading-relaxed text-muted-foreground sm:text-lg">{description}</p>
          )}
        </div>

        <div className="mt-12 grid gap-5 md:grid-cols-2">
          {items.map((item) => {
            const Icon = PILLAR_ICONS[item.icon ?? ""] ?? Sparkles
            return (
              <div key={item.title} className="rounded-2xl border border-border bg-card p-6 shadow-sm lg:p-8">
                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                  <Icon className="h-5 w-5" aria-hidden />
                </div>
                <h3 className="mt-4 text-xl font-semibold text-card-foreground">{item.title}</h3>
                {item.description && (
                  <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{item.description}</p>
                )}
                {(item.outcomes?.length ?? 0) > 0 && (
                  <ul className="mt-5 space-y-2">
                    {item.outcomes!.map((outcome) => (
                      <li key={outcome} className="flex gap-2 text-sm text-muted-foreground">
                        <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" aria-hidden />
                        <span>{outcome}</span>
                      </li>
                    ))}
                  </ul>
                )}
                {item.ctaText && item.ctaHref && (
                  <Link
                    href={item.ctaHref}
                    className="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"
                  >
                    {item.ctaText}
                    <ArrowRight className="h-3.5 w-3.5" />
                  </Link>
                )}
              </div>
            )
          })}
        </div>
      </div>
    </section>
  )
}

export type DemoCard = {
  badge?: string
  title: string
  channel?: string
  description?: string
  steps?: string[]
  result?: string
}

export function LandoDemoGallery({
  title,
  description,
  items = [],
}: {
  title?: string
  description?: string
  items?: DemoCard[]
}) {
  if (items.length === 0) return null

  return (
    <section id="demos" className="bg-muted py-14 lg:py-20">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-3xl text-center">
          {title && <h2 className="text-3xl font-bold text-foreground sm:text-4xl">{title}</h2>}
          {description && (
            <p className="mt-4 text-base leading-relaxed text-muted-foreground sm:text-lg">{description}</p>
          )}
        </div>

        <div className="mt-12 grid gap-5 lg:grid-cols-3">
          {items.map((item) => (
            <article key={item.title} className="flex flex-col rounded-2xl border border-border bg-card p-6 shadow-sm">
              <div className="flex flex-wrap items-center gap-2">
                {item.badge && (
                  <span className="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">
                    {item.badge}
                  </span>
                )}
                {item.channel && (
                  <span className="rounded-full border border-border px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                    {item.channel}
                  </span>
                )}
              </div>
              <h3 className="mt-4 text-lg font-semibold text-card-foreground">{item.title}</h3>
              {item.description && (
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{item.description}</p>
              )}
              {(item.steps?.length ?? 0) > 0 && (
                <ol className="mt-5 flex-1 space-y-2.5">
                  {item.steps!.map((step, i) => (
                    <li key={step} className="flex gap-3 text-sm text-muted-foreground">
                      <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-bold text-foreground">
                        {i + 1}
                      </span>
                      <span>{step}</span>
                    </li>
                  ))}
                </ol>
              )}
              {item.result && (
                <p className="mt-5 rounded-xl border border-primary/20 bg-primary/5 px-3 py-2.5 text-sm font-medium text-foreground">
                  {item.result}
                </p>
              )}
            </article>
          ))}
        </div>
      </div>
    </section>
  )
}

export type OutcomeItem = {
  value: string
  label: string
  detail?: string
}

export function LandoOutcomes({
  title,
  description,
  items = [],
}: {
  title?: string
  description?: string
  items?: OutcomeItem[]
}) {
  if (items.length === 0) return null

  return (
    <section id="outcomes" className="bg-muted py-12 lg:py-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div className="overflow-hidden rounded-3xl border border-border bg-card px-6 py-10 shadow-sm sm:px-10 lg:px-12">
          <div className="mx-auto max-w-2xl text-center">
            {title && <h2 className="text-2xl font-bold text-card-foreground sm:text-3xl">{title}</h2>}
            {description && <p className="mt-3 text-sm text-muted-foreground sm:text-base">{description}</p>}
          </div>
          <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {items.map((item) => (
              <div key={item.label} className="text-center">
                <p className="text-3xl font-bold tracking-tight text-primary sm:text-4xl">{item.value}</p>
                <p className="mt-2 text-sm font-semibold text-card-foreground">{item.label}</p>
                {item.detail && <p className="mt-1 text-xs text-muted-foreground">{item.detail}</p>}
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  )
}
