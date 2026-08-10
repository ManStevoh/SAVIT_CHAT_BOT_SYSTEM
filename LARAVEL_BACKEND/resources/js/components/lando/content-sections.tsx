import { Star } from "lucide-react"
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion"
import { Button } from "@/components/ui/button"
import Link from "next/link"
import { usePlans } from "@/lib/api-hooks"
import { Check } from "lucide-react"
import { cn } from "@/lib/utils"
import { useState, useEffect } from "react"
import { createCheckoutSession } from "@/lib/api-actions"
import { getAuthToken } from "@/lib/api-client"
import { toast } from "sonner"
import { RecaptchaWidget, resetRecaptchaWidget } from "@/components/compliance/RecaptchaWidget"
import { useAppBranding } from "@/components/providers/AppBrandingProvider"
import { Reveal } from "./reveal"
import { WhatsAppPartnerBadge } from "./whatsapp-partner-badge"

export function LandoTrustedCompanies({
  title,
  companies = [],
}: {
  title?: string
  companies?: Array<{ name: string; logoUrl?: string } | string>
}) {
  const parsed = companies.map((c) =>
    typeof c === "string" ? { name: c, logoUrl: "" } : c
  )

  return (
    <section className="bg-muted py-12">
      <div className="mx-auto max-w-6xl px-4 text-center sm:px-6 lg:px-8">
        <Reveal>
          {title && <p className="text-sm text-muted-foreground">{title}</p>}
          <div className="mt-8 flex flex-wrap items-center justify-center gap-8 lg:gap-12">
            {parsed.map((company) => (
              <div
                key={company.name}
                className="flex items-center gap-2 transition-opacity duration-300 hover:opacity-100 opacity-80"
              >
                {company.logoUrl ? (
                  <img
                    src={company.logoUrl}
                    alt={company.name}
                    loading="lazy"
                    decoding="async"
                    className="h-8 max-w-[120px] object-contain opacity-60 transition-opacity hover:opacity-100 dark:invert"
                  />
                ) : (
                  <span className="text-lg font-bold text-muted-foreground transition-colors hover:text-foreground">
                    {company.name}
                  </span>
                )}
              </div>
            ))}
          </div>
        </Reveal>
      </div>
    </section>
  )
}

export function LandoTestimonials({
  title,
  description,
  testimonials = [],
}: {
  title?: string
  description?: string
  testimonials?: Array<{ id: string; name: string; role: string; content: string; rating: number }>
}) {
  if (testimonials.length === 0) return null

  return (
    <section className="bg-muted py-16 lg:py-24">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <Reveal>
          <div className="text-center">
            {title && <h2 className="text-3xl font-bold text-foreground sm:text-4xl">{title}</h2>}
            {description && <p className="mt-3 text-muted-foreground">{description}</p>}
          </div>
        </Reveal>
        <div className="mt-12 grid gap-6 md:grid-cols-3">
          {testimonials.slice(0, 3).map((t, i) => (
            <Reveal key={t.id} delayMs={i * 80}>
              <div className="h-full rounded-2xl border border-border bg-card p-8 text-center shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-primary/40 hover:shadow-md">
                <p className="text-base leading-relaxed text-card-foreground">&ldquo;{t.content}&rdquo;</p>
                <div className="mt-4 flex justify-center gap-0.5">
                  {Array.from({ length: t.rating || 5 }).map((_, starIndex) => (
                    <Star key={starIndex} className="h-4 w-4 fill-primary text-primary" />
                  ))}
                </div>
                <p className="mt-4 font-bold text-card-foreground">{t.name}</p>
                {t.role && <p className="text-sm text-muted-foreground">{t.role}</p>}
              </div>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  )
}

export function LandoPricingPlans({ popularBadge = "Most Popular" }: { popularBadge?: string }) {
  const [currency, setCurrency] = useState<string | null>(null)
  const { data, isLoading } = usePlans(currency)
  const [isLoggedIn, setIsLoggedIn] = useState(false)
  const [busy, setBusy] = useState<string | null>(null)

  useEffect(() => {
    setIsLoggedIn(!!getAuthToken())
  }, [])

  const list = data?.plans ?? []
  const activeCurrency = data?.currency ?? currency ?? "USD"
  const currencies = data?.availableCurrencies?.length
    ? data.availableCurrencies
    : [
        { code: "KES", label: "Kenyan Shilling", symbol: "KSh" },
        { code: "USD", label: "US Dollar", symbol: "$" },
        { code: "NGN", label: "Nigerian Naira", symbol: "₦" },
      ]

  const handleSubscribe = async (planId: string) => {
    setBusy(planId)
    const result = await createCheckoutSession(planId)
    setBusy(null)
    if (result.success && result.url) {
      window.location.href = result.url
    } else {
      toast.error(result.message ?? "Could not start checkout.")
    }
  }

  if (isLoading && list.length === 0) {
    return (
      <div className="flex justify-center py-16">
        <span className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    )
  }

  return (
    <section className="bg-muted pb-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div className="mb-8 flex flex-col items-center gap-3">
          <div className="inline-flex rounded-lg border border-border bg-card p-1">
            {currencies.map((c) => (
              <button
                key={c.code}
                type="button"
                onClick={() => setCurrency(c.code)}
                className={cn(
                  "rounded-md px-3 py-1.5 text-sm font-medium transition-colors",
                  activeCurrency === c.code
                    ? "bg-primary text-primary-foreground"
                    : "text-muted-foreground hover:text-foreground"
                )}
              >
                {c.code}
              </button>
            ))}
          </div>
          <p className="text-center text-xs text-muted-foreground">
            {data?.source === "cloudflare" || data?.source === "forced"
              ? `Showing ${activeCurrency} based on your location${data?.detectedCountry ? ` (${data.detectedCountry})` : ""}.`
              : `Showing prices in ${activeCurrency}.`}{" "}
            Switch anytime.
          </p>
        </div>
        <div className="grid gap-6 lg:grid-cols-3">
          {list.map((plan, i) => {
            // Show Trial + Subscribe for priced plans even if no gateway is configured yet.
            const showSubscribeActions = !plan.isFree && (plan.price ?? "") !== "Custom"

            return (
            <Reveal key={plan.id} delayMs={i * 70}>
            <div
              className={cn(
                "relative h-full rounded-2xl border border-border bg-card p-8 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md",
                plan.popular && "ring-2 ring-primary"
              )}
            >
              {plan.popular && (
                <span className="absolute -top-3 right-6 rounded-full bg-primary px-3 py-1 text-xs font-medium text-white">
                  {popularBadge}
                </span>
              )}
              <h3 className="text-xl font-bold text-card-foreground">{plan.name}</h3>
              <p className="mt-4 text-4xl font-bold text-card-foreground">{plan.price}</p>
              {(plan.price ?? "") !== "Custom" && (
                <p className="text-sm text-muted-foreground">per month</p>
              )}
              <p className="mt-2 text-sm text-muted-foreground">{plan.description}</p>
              <ul className="mt-6 space-y-3">
                {(plan.features ?? []).map((f) => (
                  <li key={f} className="flex items-start gap-2 text-sm text-muted-foreground">
                    <Check className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                    {f}
                  </li>
                ))}
              </ul>
              {showSubscribeActions ? (
                <div className="mt-8 space-y-2">
                  {(plan.hasTrial ?? true) && (
                    <Button
                      className={cn(
                        "w-full rounded-lg",
                        plan.popular
                          ? "bg-primary text-white hover:bg-primary/90"
                          : "border-border bg-card text-foreground hover:bg-muted hover:text-foreground"
                      )}
                      variant={plan.popular ? "default" : "outline"}
                      disabled={busy !== null}
                      onClick={() => {
                        if (isLoggedIn) {
                          window.location.href = "/dashboard/subscription"
                        } else {
                          window.location.href = `/register?plan=${encodeURIComponent(plan.id)}`
                        }
                      }}
                    >
                      {plan.cta && !/contact/i.test(plan.cta) ? plan.cta : "Start Free Trial"}
                    </Button>
                  )}
                  <Button
                    className={cn(
                      "w-full rounded-lg",
                      plan.hasTrial === false && plan.popular
                        ? "bg-primary text-white hover:bg-primary/90"
                        : "border-border bg-card text-foreground hover:bg-muted hover:text-foreground"
                    )}
                    variant={plan.hasTrial === false && plan.popular ? "default" : "outline"}
                    disabled={busy !== null}
                    onClick={() => {
                      if (isLoggedIn && plan.checkoutAvailable) {
                        handleSubscribe(plan.id)
                      } else if (isLoggedIn) {
                        window.location.href = `/dashboard/subscription?subscribe=${encodeURIComponent(plan.id)}`
                      } else {
                        window.location.href = `/register?plan=${encodeURIComponent(plan.id)}&intent=subscribe`
                      }
                    }}
                  >
                    {busy === plan.id ? "Redirecting…" : "Subscribe"}
                  </Button>
                  {!isLoggedIn && (
                    <p className="text-center text-xs text-muted-foreground">
                      Already have an account?{" "}
                      <a
                        href={`/login?plan=${encodeURIComponent(plan.id)}&pay=1`}
                        className="text-primary hover:underline"
                      >
                        Sign in to pay
                      </a>
                    </p>
                  )}
                </div>
              ) : (
                <Button
                  className={cn(
                    "mt-8 w-full rounded-lg",
                    plan.popular
                      ? "bg-primary text-white hover:bg-primary/90"
                      : "border-border bg-card text-foreground hover:bg-muted hover:text-foreground"
                  )}
                  variant={plan.popular ? "default" : "outline"}
                  disabled={busy !== null}
                  onClick={() => {
                    window.location.href = `/register?plan=${encodeURIComponent(plan.id)}`
                  }}
                >
                  {plan.cta ?? "Contact Sales"}
                </Button>
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

export function LandoCompareFeatures({
  title = "Compare Features",
  columns = [],
}: {
  title?: string
  columns?: Array<{ name: string; features: string[] }>
}) {
  if (columns.length === 0) return null

  return (
    <section className="bg-muted py-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <Reveal>
          <h2 className="text-3xl font-bold text-foreground">{title}</h2>
        <div className="mt-8 overflow-x-auto rounded-2xl border border-border bg-card shadow-sm transition-shadow duration-300 hover:shadow-md">
          <div className="min-w-[520px]">
            <div className="grid border-b border-border md:grid-cols-3">
              {columns.map((col) => (
                <div key={col.name} className="border-border p-6 font-bold text-card-foreground md:border-r last:md:border-r-0">
                  {col.name}
                </div>
              ))}
            </div>
            <div className="grid md:grid-cols-3">
              {columns.map((col) => (
                <div key={col.name} className="space-y-4 border-border p-6 md:border-r last:md:border-r-0">
                  {col.features.map((f) => (
                    <div key={f} className="flex items-center gap-2 text-sm text-muted-foreground">
                      <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary text-white">
                        <Check className="h-3 w-3" />
                      </span>
                      {f}
                    </div>
                  ))}
                </div>
              ))}
            </div>
          </div>
        </div>
        </Reveal>
      </div>
    </section>
  )
}

export function LandoFaqSection({
  title = "Frequently asked questions",
  faqs = [],
}: {
  title?: string
  faqs?: Array<{ id: string; question: string; answer: string }>
}) {
  if (faqs.length === 0) return null

  return (
    <section className="bg-muted py-16 lg:py-24">
      <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <Reveal>
          <h2 className="text-center text-3xl font-bold text-foreground sm:text-4xl">{title}</h2>
          <Accordion type="single" collapsible className="mt-10">
            {faqs.map((faq) => (
              <AccordionItem key={faq.id} value={faq.id} className="border-border">
                <AccordionTrigger className="text-left font-medium text-foreground hover:no-underline">
                  {faq.question}
                </AccordionTrigger>
                <AccordionContent className="text-muted-foreground">{faq.answer}</AccordionContent>
              </AccordionItem>
            ))}
          </Accordion>
        </Reveal>
      </div>
    </section>
  )
}

export function LandoAboutHero({
  title,
  description,
  imageUrl,
  imageAlt = "",
}: {
  title: string
  description?: string
  imageUrl?: string
  imageAlt?: string
}) {
  return (
    <section className="relative overflow-hidden bg-muted pt-28 pb-0 lg:pt-32">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(37,99,235,0.12),_transparent_55%)]"
      />
      <div className="relative mx-auto grid max-w-6xl items-end gap-10 px-4 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:gap-12 lg:px-8">
        <Reveal>
          <div className="pb-12 lg:pb-20">
            <p className="text-xs font-semibold tracking-[0.2em] text-primary uppercase">RelayIQ</p>
            <h1 className="mt-4 max-w-xl text-4xl font-bold leading-[1.08] tracking-tight text-foreground sm:text-5xl lg:text-[3.4rem]">
              {title}
            </h1>
            {description && (
              <p className="mt-5 max-w-lg text-base leading-relaxed text-muted-foreground sm:text-lg">
                {description}
              </p>
            )}
            <div className="mt-6">
              <WhatsAppPartnerBadge />
            </div>
          </div>
        </Reveal>
        {imageUrl ? (
          <Reveal delayMs={100}>
            <div className="relative min-h-[240px] lg:min-h-[360px]">
              <img
                src={imageUrl}
                alt={imageAlt}
                loading="eager"
                fetchPriority="high"
                decoding="async"
                className="h-full w-full object-cover object-center transition-transform duration-500 hover:scale-[1.01] lg:absolute lg:inset-0 lg:rounded-tl-[2rem]"
              />
            </div>
          </Reveal>
        ) : null}
      </div>
    </section>
  )
}

export function LandoMission({ title, description }: { title: string; description?: string }) {
  return (
    <section className="border-t border-border bg-card py-16 text-center lg:py-20">
      <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <Reveal>
          <h2 className="text-3xl font-bold tracking-tight text-card-foreground sm:text-4xl">{title}</h2>
          {description && (
            <p className="mt-6 text-base leading-relaxed text-muted-foreground sm:text-lg">{description}</p>
          )}
        </Reveal>
      </div>
    </section>
  )
}

export function LandoEfficiency({
  title,
  description,
  ctaText,
  ctaHref,
}: {
  title: string
  description?: string
  ctaText?: string
  ctaHref?: string
}) {
  const lines = title.split(/\n+/).map((l) => l.trim()).filter(Boolean)
  const displayTitle = lines.length > 1 ? lines.join(" ") : title

  return (
    <section className="relative overflow-hidden bg-card border-y border-border py-20 text-card-foreground lg:py-28">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,_rgba(37,99,235,0.15),_transparent_45%),radial-gradient(circle_at_80%_80%,_rgba(56,189,248,0.10),_transparent_40%)]"
      />
      <Reveal>
        <div className="relative mx-auto flex max-w-4xl flex-col items-center px-4 text-center sm:px-6 lg:px-8">
          <h2 className="max-w-3xl text-balance text-3xl font-bold leading-tight tracking-tight text-card-foreground sm:text-5xl lg:text-6xl">
            {displayTitle}
          </h2>
          {description ? (
            <p className="mt-6 max-w-2xl text-base leading-relaxed text-muted-foreground sm:text-lg">
              {description}
            </p>
          ) : (
            <p className="mt-6 max-w-2xl text-base leading-relaxed text-muted-foreground sm:text-lg">
              Automate WhatsApp sales, keep humans in control, and grow with a platform built for commerce teams.
            </p>
          )}
          {ctaText && ctaHref ? (
            <Button
              asChild
              className="mt-10 h-11 rounded-lg bg-primary px-7 text-white transition-transform hover:bg-primary/90 hover:-translate-y-0.5"
            >
              <Link href={ctaHref}>{ctaText}</Link>
            </Button>
          ) : (
            <Button
              asChild
              className="mt-10 h-11 rounded-lg bg-primary px-7 text-white transition-transform hover:bg-primary/90 hover:-translate-y-0.5"
            >
              <Link href="/register">Start free</Link>
            </Button>
          )}
        </div>
      </Reveal>
    </section>
  )
}

export function LandoTeam({
  title,
  description,
  members = [],
}: {
  title?: string
  description?: string
  members?: Array<{ name: string; role: string; imageUrl?: string }>
}) {
  if (members.length === 0) return null

  return (
    <section className="bg-muted py-16 lg:py-24">
      <div className="mx-auto max-w-6xl px-4 text-center sm:px-6 lg:px-8">
        <Reveal>
          {title && <h2 className="text-3xl font-bold text-foreground sm:text-4xl">{title}</h2>}
          {description && <p className="mt-3 text-muted-foreground">{description}</p>}
        </Reveal>
        <div className="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
          {members.map((m, i) => (
            <Reveal key={m.name} delayMs={i * 60}>
              <div className="transition-transform duration-300 hover:-translate-y-1">
                {m.imageUrl ? (
                  <img
                    src={m.imageUrl}
                    alt={m.name}
                    loading="lazy"
                    decoding="async"
                    className="mx-auto h-32 w-32 rounded-full object-cover"
                  />
                ) : (
                  <div className="mx-auto flex h-32 w-32 items-center justify-center rounded-full border border-border bg-card text-2xl font-bold text-muted-foreground">
                    {m.name.charAt(0)}
                  </div>
                )}
                <p className="mt-4 font-bold text-foreground">{m.name}</p>
                <p className="text-sm text-muted-foreground">{m.role}</p>
              </div>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  )
}

export function LandoContactSection({
  title,
  description,
  imageUrl,
  imageAlt = "",
  nameLabel = "Name",
  namePlaceholder = "Full Name",
  emailLabel = "Email",
  emailPlaceholder = "Email address",
  messageLabel = "Message",
  messagePlaceholder = "How can we help?",
  submitText = "Send message",
  successMessage = "Thank you! We will get back to you shortly.",
}: {
  title: string
  description?: string
  imageUrl?: string
  imageAlt?: string
  nameLabel?: string
  namePlaceholder?: string
  emailLabel?: string
  emailPlaceholder?: string
  messageLabel?: string
  messagePlaceholder?: string
  submitText?: string
  successMessage?: string
}) {
  const [name, setName] = useState("")
  const [email, setEmail] = useState("")
  const [message, setMessage] = useState("")
  const [sent, setSent] = useState(false)
  const [busy, setBusy] = useState(false)
  const [recaptchaToken, setRecaptchaToken] = useState<string | null>(null)
  const branding = useAppBranding()

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (branding.recaptchaEnabled && !recaptchaToken) {
      toast.error("Please complete the captcha challenge.")
      return
    }
    setBusy(true)
    try {
      const res = await fetch("/api/contact", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ name, email, message, recaptchaToken: recaptchaToken || undefined }),
      })
      if (res.ok) {
        setSent(true)
        setName("")
        setEmail("")
        setMessage("")
        setRecaptchaToken(null)
        resetRecaptchaWidget()
      } else {
        const data = await res.json().catch(() => null)
        toast.error(data?.message || data?.errors?.recaptchaToken?.[0] || "Failed to send message")
        resetRecaptchaWidget()
        setRecaptchaToken(null)
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="bg-muted pt-28 pb-16 lg:pt-32 lg:pb-24">
      <div className="mx-auto grid max-w-6xl items-start gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
        {imageUrl && (
          <Reveal>
            <img
              src={imageUrl}
              alt={imageAlt}
              loading="eager"
              decoding="async"
              className="max-h-[400px] w-full object-contain transition-transform duration-500 hover:scale-[1.02]"
            />
          </Reveal>
        )}
        <Reveal delayMs={100}>
          <div>
            <h1 className="text-4xl font-bold text-foreground sm:text-5xl">{title}</h1>
            {description && <p className="mt-4 text-muted-foreground">{description}</p>}
            {sent ? (
              <p className="mt-8 rounded-lg border border-green-500/20 bg-green-500/10 p-4 text-green-600 dark:text-green-400">
                {successMessage}
              </p>
            ) : (
              <form onSubmit={handleSubmit} className="mt-8 space-y-5">
                <div>
                  <label className="mb-1.5 block text-sm font-medium text-foreground">{nameLabel}</label>
                  <input
                    required
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder={namePlaceholder}
                    className="h-11 w-full rounded-lg border border-border bg-card px-4 text-card-foreground outline-none focus:border-primary"
                  />
                </div>
                <div>
                  <label className="mb-1.5 block text-sm font-medium text-foreground">{emailLabel}</label>
                  <input
                    required
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder={emailPlaceholder}
                    className="h-11 w-full rounded-lg border border-border bg-card px-4 text-card-foreground outline-none focus:border-primary"
                  />
                </div>
                <div>
                  <label className="mb-1.5 block text-sm font-medium text-foreground">{messageLabel}</label>
                  <textarea
                    required
                    rows={4}
                    value={message}
                    onChange={(e) => setMessage(e.target.value)}
                    placeholder={messagePlaceholder}
                    className="w-full rounded-lg border border-border bg-card px-4 py-3 text-card-foreground outline-none focus:border-primary"
                  />
                </div>
                <RecaptchaWidget onChange={setRecaptchaToken} />
                <Button
                  type="submit"
                  disabled={busy}
                  className="h-11 rounded-lg bg-primary px-6 text-white transition-transform hover:bg-primary/90 hover:-translate-y-0.5"
                >
                  {submitText}
                </Button>
              </form>
            )}
          </div>
        </Reveal>
      </div>
    </section>
  )
}
