import Link from "next/link"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"
import { LandoHeroFlowSimulation } from "./hero-flow-simulation"
import { Reveal } from "./reveal"
import { WhatsAppPartnerBadge } from "./whatsapp-partner-badge"
import {
  Bot,
  Package,
  CreditCard,
  CalendarDays,
  Megaphone,
  MessagesSquare,
  Smartphone,
  Sparkles,
  Store,
  QrCode,
  Truck,
  type LucideIcon,
} from "lucide-react"

interface LandoHeroProps {
  kicker?: string
  title: string
  description?: string
  primaryCtaText?: string
  primaryCtaHref?: string
  secondaryCtaText?: string
  secondaryCtaHref?: string
  imageUrl?: string
  imageAlt?: string
  showFlowSimulation?: boolean
}

export function LandoHeroSection({
  kicker,
  title,
  description,
  primaryCtaText,
  primaryCtaHref,
  secondaryCtaText,
  secondaryCtaHref,
  imageUrl,
  imageAlt = "",
  showFlowSimulation = false,
}: LandoHeroProps) {
  return (
    <section className="lando-hero overflow-x-hidden bg-muted pt-28 pb-16 lg:pt-32 lg:pb-24">
      <div className="mx-auto grid max-w-6xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
        <Reveal className="min-w-0">
          <div className="min-w-0">
            {kicker && (
              <p className="mb-4 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                — {kicker}
              </p>
            )}
            <h1 className="text-balance text-3xl font-bold leading-tight text-foreground sm:text-4xl md:text-5xl lg:text-[3.25rem] lg:leading-[1.1]">
              {title}
            </h1>
            {description && (
              <p className="mt-5 max-w-lg text-base leading-relaxed text-muted-foreground sm:text-lg">
                {description}
              </p>
            )}
            {(primaryCtaText || secondaryCtaText) && (
              <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                {primaryCtaText && primaryCtaHref && (
                  <Button
                    asChild
                    className="h-11 w-full rounded-lg bg-primary px-6 text-white transition-transform hover:bg-primary/90 hover:-translate-y-0.5 sm:w-auto"
                  >
                    <Link href={primaryCtaHref}>{primaryCtaText}</Link>
                  </Button>
                )}
                {secondaryCtaText && secondaryCtaHref && (
                  <Button
                    asChild
                    variant="outline"
                    className="h-11 w-full rounded-lg border-border bg-card px-6 text-foreground transition-transform hover:bg-muted hover:text-foreground hover:-translate-y-0.5 sm:w-auto"
                  >
                    <Link href={secondaryCtaHref}>{secondaryCtaText}</Link>
                  </Button>
                )}
              </div>
            )}
            <div className="mt-6 max-w-full">
              <WhatsAppPartnerBadge className="max-w-full" />
            </div>
          </div>
        </Reveal>

        <Reveal delayMs={120} className="min-w-0 w-full">
          {showFlowSimulation ? (
            <LandoHeroFlowSimulation />
          ) : (
            imageUrl && (
              <div className="flex justify-center lg:justify-end">
                <img
                  src={imageUrl}
                  alt={imageAlt}
                  loading="eager"
                  fetchPriority="high"
                  decoding="async"
                  className="max-h-[420px] w-full max-w-md object-contain transition-transform duration-500 hover:scale-[1.02]"
                />
              </div>
            )
          )}
        </Reveal>
      </div>
    </section>
  )
}

export function LandoPageHero({ title, description }: { title: string; description?: string }) {
  return (
    <section className="bg-muted pt-28 pb-12 text-center lg:pt-32">
      <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <Reveal>
          <h1 className="text-4xl font-bold text-foreground sm:text-5xl">{title}</h1>
          {description && (
            <p className="mx-auto mt-4 max-w-xl text-base text-muted-foreground sm:text-lg">{description}</p>
          )}
          <div className="mt-6 flex justify-center">
            <WhatsAppPartnerBadge />
          </div>
        </Reveal>
      </div>
    </section>
  )
}

const CAPABILITY_ICONS: Record<string, LucideIcon> = {
  bot: Bot,
  package: Package,
  payment: CreditCard,
  booking: CalendarDays,
  growth: Megaphone,
  inbox: MessagesSquare,
  mobile: Smartphone,
  sparkles: Sparkles,
  store: Store,
  dinein: QrCode,
  delivery: Truck,
}

export function LandoCapabilities({
  title,
  description,
  items = [],
}: {
  title?: string
  description?: string
  items?: Array<{ title: string; description?: string; icon?: string }>
}) {
  if (items.length === 0) return null

  return (
    <section className="bg-muted py-12 lg:py-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <Reveal>
          <div className="mx-auto max-w-2xl text-center">
            {title && <h2 className="text-3xl font-bold text-foreground sm:text-4xl">{title}</h2>}
            {description && <p className="mt-3 text-base text-muted-foreground sm:text-lg">{description}</p>}
          </div>
        </Reveal>
        <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {items.map((item, i) => {
            const Icon = CAPABILITY_ICONS[item.icon ?? ""] ?? Sparkles
            return (
              <Reveal key={item.title} delayMs={i * 60}>
                <div className="h-full rounded-2xl border border-border bg-card p-6 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-primary/40 hover:shadow-md">
                  <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary/15">
                    <Icon className="h-5 w-5" aria-hidden />
                  </div>
                  <h3 className="mt-4 text-lg font-semibold text-card-foreground">{item.title}</h3>
                  {item.description && (
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{item.description}</p>
                  )}
                </div>
              </Reveal>
            )
          })}
        </div>
      </div>
    </section>
  )
}

export function LandoIntroCard({
  title,
  description,
  ctaText,
  ctaHref,
  imageUrl,
  imageAlt = "",
  imagePosition = "right",
}: {
  title: string
  description?: string
  ctaText?: string
  ctaHref?: string
  imageUrl?: string
  imageAlt?: string
  imagePosition?: "left" | "right"
}) {
  const imageOnRight = imagePosition === "right"

  return (
    <section className="bg-muted py-12 lg:py-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <Reveal>
          <div className="overflow-hidden rounded-3xl border border-border bg-card p-8 shadow-sm transition-shadow duration-300 hover:shadow-md lg:p-12">
            <div
              className={cn(
                "flex flex-col items-center gap-10 lg:flex-row lg:gap-16",
                imageOnRight && "lg:flex-row-reverse"
              )}
            >
              {imageUrl && (
                <div className="w-full shrink-0 lg:w-1/2">
                  <img
                    src={imageUrl}
                    alt={imageAlt}
                    loading="lazy"
                    decoding="async"
                    className="mx-auto max-h-72 w-full object-contain transition-transform duration-500 hover:scale-[1.02]"
                  />
                </div>
              )}
              <div className="w-full lg:w-1/2">
                <h2 className="text-3xl font-bold text-card-foreground sm:text-4xl">{title}</h2>
                {description && <p className="mt-4 text-base text-muted-foreground sm:text-lg">{description}</p>}
                {ctaText && ctaHref && (
                  <Button asChild className="mt-8 h-11 rounded-lg bg-primary px-6 text-white transition-transform hover:bg-primary/90 hover:-translate-y-0.5">
                    <Link href={ctaHref}>{ctaText}</Link>
                  </Button>
                )}
              </div>
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  )
}

export function LandoFeatureBlock({
  label,
  title,
  description,
  ctaText,
  ctaHref,
  imageUrl,
  imageAlt = "",
  imagePosition = "left",
}: {
  label?: string
  title: string
  description?: string
  ctaText?: string
  ctaHref?: string
  imageUrl?: string
  imageAlt?: string
  imagePosition?: "left" | "right"
}) {
  const imageOnRight = imagePosition === "right"

  return (
    <section className="bg-muted py-12 lg:py-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <Reveal>
          <div
            className={cn(
              "flex flex-col items-center gap-10 lg:flex-row lg:gap-16",
              imageOnRight && "lg:flex-row-reverse"
            )}
          >
            {imageUrl && (
              <div className="w-full shrink-0 lg:w-1/2">
                <img
                  src={imageUrl}
                  alt={imageAlt}
                  loading="lazy"
                  decoding="async"
                  className="mx-auto max-h-80 w-full object-contain transition-transform duration-500 hover:scale-[1.02]"
                />
              </div>
            )}
            <div className="w-full lg:w-1/2">
              {label && (
                <p className="mb-3 text-xs font-bold tracking-widest text-muted-foreground uppercase">{label}</p>
              )}
              <h2 className="text-3xl font-bold text-foreground sm:text-4xl">{title}</h2>
              {description && <p className="mt-4 text-base leading-relaxed text-muted-foreground">{description}</p>}
              {ctaText && ctaHref && (
                <Button
                  asChild
                  variant="outline"
                  className="mt-8 h-11 rounded-lg border-border bg-card px-6 text-foreground transition-transform hover:bg-muted hover:text-foreground hover:-translate-y-0.5"
                >
                  <Link href={ctaHref}>{ctaText}</Link>
                </Button>
              )}
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  )
}

export function LandoGrowthEngine({
  label,
  title,
  description,
  points = [],
  ctaText,
  ctaHref,
  imageUrl,
  imageAlt = "",
  imagePosition = "left",
}: {
  label?: string
  title: string
  description?: string
  points?: string[]
  ctaText?: string
  ctaHref?: string
  imageUrl?: string
  imageAlt?: string
  imagePosition?: "left" | "right"
}) {
  const imageOnRight = imagePosition === "right"

  return (
    <section className="bg-muted py-12 lg:py-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <Reveal>
          <div className="overflow-hidden rounded-3xl border border-border bg-card px-8 py-10 text-card-foreground shadow-sm transition-shadow duration-300 hover:shadow-md lg:px-12 lg:py-14">
            <div
              className={cn(
                "flex flex-col items-center gap-10 lg:flex-row lg:gap-16",
                imageOnRight && "lg:flex-row-reverse"
              )}
            >
              {imageUrl && (
                <div className="w-full shrink-0 lg:w-1/2">
                  <img
                    src={imageUrl}
                    alt={imageAlt}
                    loading="lazy"
                    decoding="async"
                    className="mx-auto max-h-80 w-full object-contain transition-transform duration-500 hover:scale-[1.02]"
                  />
                </div>
              )}
              <div className="w-full lg:w-1/2">
                {label && (
                  <p className="mb-3 text-xs font-bold tracking-widest text-primary uppercase">{label}</p>
                )}
                <h2 className="text-3xl font-bold text-card-foreground sm:text-4xl">{title}</h2>
                {description && <p className="mt-4 text-base leading-relaxed text-muted-foreground">{description}</p>}
                {points.length > 0 && (
                  <ul className="mt-6 space-y-3">
                    {points.map((point) => (
                      <li key={point} className="flex gap-3 text-sm text-muted-foreground">
                        <span className="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" aria-hidden />
                        <span>{point}</span>
                      </li>
                    ))}
                  </ul>
                )}
                {ctaText && ctaHref && (
                  <Button asChild className="mt-8 h-11 rounded-lg bg-primary px-6 text-white transition-transform hover:bg-primary/90 hover:-translate-y-0.5">
                    <Link href={ctaHref}>{ctaText}</Link>
                  </Button>
                )}
              </div>
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  )
}

export function LandoHowToJoin({
  title,
  description,
  ctaText,
  ctaHref,
  imageUrl,
  imageAlt = "",
  steps = [],
  imagePosition = "right",
}: {
  title: string
  description?: string
  ctaText?: string
  ctaHref?: string
  imageUrl?: string
  imageAlt?: string
  steps?: Array<{ title: string; description?: string }>
  imagePosition?: "left" | "right"
}) {
  const imageOnRight = imagePosition === "right"

  return (
    <section id="how-to-join" className="bg-muted py-12 lg:py-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div
          className={cn(
            "flex flex-col items-center gap-10 lg:flex-row lg:gap-16",
            imageOnRight && "lg:flex-row-reverse"
          )}
        >
          {imageUrl && (
            <Reveal delayMs={100} className="w-full shrink-0 lg:w-1/2">
              <img
                src={imageUrl}
                alt={imageAlt}
                loading="lazy"
                decoding="async"
                className="mx-auto max-h-80 w-full object-contain transition-transform duration-500 hover:scale-[1.02]"
              />
            </Reveal>
          )}
          <Reveal className="w-full lg:w-1/2">
            <div>
              <h2 className="text-3xl font-bold text-foreground sm:text-4xl">{title}</h2>
              {description && <p className="mt-4 text-base text-muted-foreground">{description}</p>}
              <div className="mt-8 space-y-6">
                {steps.map((step, i) => (
                  <div key={step.title + i} className="flex gap-4 transition-transform duration-300 hover:translate-x-1">
                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">
                      {i + 1}
                    </div>
                    <div>
                      <h3 className="font-semibold text-foreground">{step.title}</h3>
                      {step.description && (
                        <p className="mt-1 text-sm leading-relaxed text-muted-foreground">{step.description}</p>
                      )}
                    </div>
                  </div>
                ))}
              </div>
              {ctaText && ctaHref && (
                <Button asChild className="mt-8 h-11 rounded-lg bg-primary px-6 text-white transition-transform hover:bg-primary/90 hover:-translate-y-0.5">
                  <Link href={ctaHref}>{ctaText}</Link>
                </Button>
              )}
            </div>
          </Reveal>
        </div>
      </div>
    </section>
  )
}

export function LandoCtaSection({
  title,
  description,
  ctaText,
  ctaHref,
  imageUrl,
  imageAlt = "",
  showImage = false,
}: {
  title: string
  description?: string
  ctaText?: string
  ctaHref?: string
  imageUrl?: string
  imageAlt?: string
  showImage?: boolean
}) {
  return (
    <section className="bg-muted py-12 lg:py-20">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <Reveal>
          <div className="overflow-hidden rounded-3xl border border-border bg-card px-8 py-12 text-center shadow-sm transition-shadow duration-300 hover:shadow-md lg:px-16">
            {showImage && imageUrl && (
              <img
                src={imageUrl}
                alt={imageAlt}
                loading="lazy"
                decoding="async"
                className="mx-auto mb-8 max-h-40 object-contain"
              />
            )}
            <h2 className="text-3xl font-bold text-card-foreground sm:text-4xl">{title}</h2>
            {description && <p className="mx-auto mt-4 max-w-xl text-base text-muted-foreground">{description}</p>}
            {ctaText && ctaHref && (
              <Button asChild className="mt-8 h-11 rounded-lg bg-primary px-6 text-white transition-transform hover:bg-primary/90 hover:-translate-y-0.5">
                <Link href={ctaHref}>{ctaText}</Link>
              </Button>
            )}
          </div>
        </Reveal>
      </div>
    </section>
  )
}
