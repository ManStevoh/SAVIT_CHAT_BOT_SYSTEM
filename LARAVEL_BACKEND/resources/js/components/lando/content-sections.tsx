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
    <section className="bg-[#f3f4f6] py-12">
      <div className="mx-auto max-w-6xl px-4 text-center sm:px-6 lg:px-8">
        {title && <p className="text-sm text-gray-600">{title}</p>}
        <div className="mt-8 flex flex-wrap items-center justify-center gap-8 lg:gap-12">
          {parsed.map((company) => (
            <div key={company.name} className="flex items-center gap-2">
              {company.logoUrl ? (
                <img src={company.logoUrl} alt={company.name} loading="lazy" decoding="async" className="h-8 max-w-[120px] object-contain opacity-60" />
              ) : (
                <span className="text-lg font-bold text-gray-400">{company.name}</span>
              )}
            </div>
          ))}
        </div>
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
    <section className="bg-[#f3f4f6] py-16 lg:py-24">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div className="text-center">
          {title && <h2 className="text-3xl font-bold text-black sm:text-4xl">{title}</h2>}
          {description && <p className="mt-3 text-gray-600">{description}</p>}
        </div>
        <div className="mt-12 grid gap-6 md:grid-cols-3">
          {testimonials.slice(0, 3).map((t) => (
            <div key={t.id} className="rounded-2xl bg-white p-8 text-center shadow-sm">
              <p className="text-base leading-relaxed text-black">&ldquo;{t.content}&rdquo;</p>
              <div className="mt-4 flex justify-center gap-0.5">
                {Array.from({ length: t.rating || 5 }).map((_, i) => (
                  <Star key={i} className="h-4 w-4 fill-[#2563eb] text-[#2563eb]" />
                ))}
              </div>
              <p className="mt-4 font-bold text-black">{t.name}</p>
              {t.role && <p className="text-sm text-gray-500">{t.role}</p>}
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

export function LandoPricingPlans({ popularBadge = "Most Popular" }: { popularBadge?: string }) {
  const { data: plans, isLoading } = usePlans()
  const [isLoggedIn, setIsLoggedIn] = useState(false)
  const [busy, setBusy] = useState<string | null>(null)

  useEffect(() => {
    setIsLoggedIn(!!getAuthToken())
  }, [])

  const list = plans ?? []

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
        <span className="h-8 w-8 animate-spin rounded-full border-2 border-[#2563eb] border-t-transparent" />
      </div>
    )
  }

  return (
    <section className="bg-[#f3f4f6] pb-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div className="grid gap-6 lg:grid-cols-3">
          {list.map((plan) => (
            <div
              key={plan.id}
              className={cn(
                "relative rounded-2xl bg-white p-8 shadow-sm",
                plan.popular && "ring-2 ring-[#2563eb]"
              )}
            >
              {plan.popular && (
                <span className="absolute -top-3 right-6 rounded-full bg-[#2563eb] px-3 py-1 text-xs font-medium text-white">
                  {popularBadge}
                </span>
              )}
              <h3 className="text-xl font-bold text-black">{plan.name}</h3>
              <p className="mt-4 text-4xl font-bold text-black">{plan.price}</p>
              <p className="mt-2 text-sm text-gray-600">{plan.description}</p>
              <ul className="mt-6 space-y-3">
                {plan.features.map((f) => (
                  <li key={f} className="flex items-start gap-2 text-sm text-gray-700">
                    <Check className="mt-0.5 h-4 w-4 shrink-0 text-[#2563eb]" />
                    {f}
                  </li>
                ))}
              </ul>
              <Button
                className={cn(
                  "mt-8 w-full rounded-lg",
                  plan.popular
                    ? "bg-[#2563eb] text-white hover:bg-[#1d4ed8]"
                    : "border-black bg-white text-black hover:bg-gray-50"
                )}
                variant={plan.popular ? "default" : "outline"}
                disabled={busy !== null}
                onClick={() => {
                  if (plan.checkoutAvailable && isLoggedIn) {
                    handleSubscribe(plan.id)
                  } else if (!isLoggedIn) {
                    window.location.href = "/register"
                  }
                }}
              >
                {plan.cta ?? "Get started"}
              </Button>
            </div>
          ))}
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
    <section className="bg-[#f3f4f6] py-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <h2 className="text-3xl font-bold text-black">{title}</h2>
        <div className="mt-8 overflow-hidden rounded-2xl bg-white shadow-sm">
          <div className="grid border-b border-gray-200 md:grid-cols-3">
            {columns.map((col) => (
              <div key={col.name} className="border-gray-200 p-6 font-bold text-black md:border-r last:md:border-r-0">
                {col.name}
              </div>
            ))}
          </div>
          <div className="grid md:grid-cols-3">
            {columns.map((col) => (
              <div key={col.name} className="space-y-4 border-gray-200 p-6 md:border-r last:md:border-r-0">
                {col.features.map((f) => (
                  <div key={f} className="flex items-center gap-2 text-sm text-gray-700">
                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-[#2563eb] text-white">
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
    <section className="bg-[#f3f4f6] py-16 lg:py-24">
      <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <h2 className="text-center text-3xl font-bold text-black sm:text-4xl">{title}</h2>
        <Accordion type="single" collapsible className="mt-10">
          {faqs.map((faq) => (
            <AccordionItem key={faq.id} value={faq.id} className="border-gray-300">
              <AccordionTrigger className="text-left font-medium text-black hover:no-underline">
                {faq.question}
              </AccordionTrigger>
              <AccordionContent className="text-gray-600">{faq.answer}</AccordionContent>
            </AccordionItem>
          ))}
        </Accordion>
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
    <section className="relative overflow-hidden bg-[#eef2f7] pt-28 pb-0 lg:pt-32">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(37,99,235,0.12),_transparent_55%)]"
      />
      <div className="relative mx-auto grid max-w-6xl items-end gap-10 px-4 sm:px-6 lg:grid-cols-[1.05fr_0.95fr] lg:gap-12 lg:px-8">
        <div className="pb-12 lg:pb-20">
          <p className="text-xs font-semibold tracking-[0.2em] text-[#2563eb] uppercase">RelayIQ</p>
          <h1 className="mt-4 max-w-xl text-4xl font-bold leading-[1.08] tracking-tight text-black sm:text-5xl lg:text-[3.4rem]">
            {title}
          </h1>
          {description && (
            <p className="mt-5 max-w-lg text-base leading-relaxed text-gray-600 sm:text-lg">
              {description}
            </p>
          )}
        </div>
        {imageUrl ? (
          <div className="relative min-h-[240px] lg:min-h-[360px]">
            <img
              src={imageUrl}
              alt={imageAlt}
              loading="eager"
              fetchPriority="high"
              decoding="async"
              className="h-full w-full object-cover object-center lg:absolute lg:inset-0 lg:rounded-tl-[2rem]"
            />
          </div>
        ) : null}
      </div>
    </section>
  )
}

export function LandoMission({ title, description }: { title: string; description?: string }) {
  return (
    <section className="border-t border-gray-200/80 bg-[#f3f4f6] py-16 text-center lg:py-20">
      <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <h2 className="text-3xl font-bold tracking-tight text-black sm:text-4xl">{title}</h2>
        {description && (
          <p className="mt-6 text-base leading-relaxed text-gray-600 sm:text-lg">{description}</p>
        )}
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
    <section className="relative overflow-hidden bg-[#0f172a] py-20 text-white lg:py-28">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,_rgba(37,99,235,0.35),_transparent_45%),radial-gradient(circle_at_80%_80%,_rgba(56,189,248,0.18),_transparent_40%)]"
      />
      <div className="relative mx-auto flex max-w-4xl flex-col items-center px-4 text-center sm:px-6 lg:px-8">
        <h2 className="max-w-3xl text-balance text-3xl font-bold leading-tight tracking-tight sm:text-5xl lg:text-6xl">
          {displayTitle}
        </h2>
        {description ? (
          <p className="mt-6 max-w-2xl text-base leading-relaxed text-slate-300 sm:text-lg">
            {description}
          </p>
        ) : (
          <p className="mt-6 max-w-2xl text-base leading-relaxed text-slate-300 sm:text-lg">
            Automate WhatsApp sales, keep humans in control, and grow with a platform built for commerce teams.
          </p>
        )}
        {ctaText && ctaHref ? (
          <Button
            asChild
            className="mt-10 h-11 rounded-lg bg-[#2563eb] px-7 text-white hover:bg-[#1d4ed8]"
          >
            <Link href={ctaHref}>{ctaText}</Link>
          </Button>
        ) : (
          <Button
            asChild
            className="mt-10 h-11 rounded-lg bg-[#2563eb] px-7 text-white hover:bg-[#1d4ed8]"
          >
            <Link href="/register">Start free</Link>
          </Button>
        )}
      </div>
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
    <section className="bg-[#f3f4f6] py-16 lg:py-24">
      <div className="mx-auto max-w-6xl px-4 text-center sm:px-6 lg:px-8">
        {title && <h2 className="text-3xl font-bold text-black sm:text-4xl">{title}</h2>}
        {description && <p className="mt-3 text-gray-600">{description}</p>}
        <div className="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
          {members.map((m) => (
            <div key={m.name}>
              {m.imageUrl ? (
                <img
                  src={m.imageUrl}
                  alt={m.name}
                  loading="lazy"
                  decoding="async"
                  className="mx-auto h-32 w-32 rounded-full object-cover"
                />
              ) : (
                <div className="mx-auto flex h-32 w-32 items-center justify-center rounded-full bg-gray-200 text-2xl font-bold text-gray-500">
                  {m.name.charAt(0)}
                </div>
              )}
              <p className="mt-4 font-bold text-black">{m.name}</p>
              <p className="text-sm text-gray-500">{m.role}</p>
            </div>
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

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setBusy(true)
    try {
      const res = await fetch("/api/contact", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ name, email, message }),
      })
      if (res.ok) {
        setSent(true)
        setName("")
        setEmail("")
        setMessage("")
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="bg-[#f3f4f6] pt-28 pb-16 lg:pt-32 lg:pb-24">
      <div className="mx-auto grid max-w-6xl items-start gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
        {imageUrl && (
          <img src={imageUrl} alt={imageAlt} loading="eager" decoding="async" className="max-h-[400px] w-full object-contain" />
        )}
        <div>
          <h1 className="text-4xl font-bold text-black sm:text-5xl">{title}</h1>
          {description && <p className="mt-4 text-gray-600">{description}</p>}
          {sent ? (
            <p className="mt-8 rounded-lg bg-green-50 p-4 text-green-800">{successMessage}</p>
          ) : (
            <form onSubmit={handleSubmit} className="mt-8 space-y-5">
              <div>
                <label className="mb-1.5 block text-sm font-medium text-black">{nameLabel}</label>
                <input
                  required
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder={namePlaceholder}
                  className="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-black outline-none focus:border-[#2563eb]"
                />
              </div>
              <div>
                <label className="mb-1.5 block text-sm font-medium text-black">{emailLabel}</label>
                <input
                  required
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder={emailPlaceholder}
                  className="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-black outline-none focus:border-[#2563eb]"
                />
              </div>
              <div>
                <label className="mb-1.5 block text-sm font-medium text-black">{messageLabel}</label>
                <textarea
                  required
                  rows={4}
                  value={message}
                  onChange={(e) => setMessage(e.target.value)}
                  placeholder={messagePlaceholder}
                  className="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-black outline-none focus:border-[#2563eb]"
                />
              </div>
              <Button
                type="submit"
                disabled={busy}
                className="h-11 rounded-lg bg-[#2563eb] px-6 text-white hover:bg-[#1d4ed8]"
              >
                {submitText}
              </Button>
            </form>
          )}
        </div>
      </div>
    </section>
  )
}
