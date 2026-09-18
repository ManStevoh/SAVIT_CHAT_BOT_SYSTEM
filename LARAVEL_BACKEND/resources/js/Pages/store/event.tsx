'use client'

import { FormEvent, useMemo, useState } from 'react'
import { Link, router, usePage } from '@inertiajs/react'
import { CalendarDays, CreditCard, MapPin } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { SeoHead, type SeoPayload } from '@/components/seo/SeoHead'
import { StorefrontFooter } from '@/components/store/StorefrontFooter'
import { resolveStorefrontStyle, type BrandTheme } from '@/lib/theme-utils'

type Session = {
  id: string
  title: string
  startsAt: string | null
  remaining: number | null
  soldOut: boolean
}

type TicketType = {
  id: string
  name: string
  priceFormatted: string
  isFree: boolean
  onSale: boolean
  soldOut: boolean
  sessionId: string | null
}

type EventPayload = {
  id: string
  title: string
  slug: string
  subtitle?: string | null
  description?: string | null
  venueName?: string | null
  venueAddress?: string | null
  timezone: string
  registrationOpen: boolean
  sessions: Session[]
  ticketTypes: TicketType[]
}

type Props = {
  slug: string
  company: {
    name: string
    theme?: BrandTheme
    aboutUrl?: string
    termsUrl?: string
    whatsappUrl?: string | null
  }
  event: EventPayload
  paymentDetails?: { details: string[] }
  seo?: SeoPayload | null
}

function fmt(iso: string | null, timezone: string) {
  if (!iso) return ''
  return new Date(iso).toLocaleString([], {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    timeZone: timezone,
  })
}

export default function StoreEventPage({ slug, company, event, paymentDetails, seo }: Props) {
  const page = usePage<{ errors?: Record<string, string> }>()
  const errors = page.props.errors ?? {}
  const style = resolveStorefrontStyle(company.theme)
  const firstOpenSession = event.sessions.find((s) => !s.soldOut) ?? event.sessions[0]
  const [sessionId, setSessionId] = useState(firstOpenSession?.id ?? '')
  const ticketsForSession = useMemo(
    () => event.ticketTypes.filter((t) => t.onSale && (!t.sessionId || t.sessionId === sessionId)),
    [event.ticketTypes, sessionId]
  )
  const [ticketTypeId, setTicketTypeId] = useState(ticketsForSession[0]?.id ?? '')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const selectedTicket = ticketsForSession.find((t) => t.id === ticketTypeId) ?? ticketsForSession[0]
  const details = paymentDetails?.details ?? []

  const submit = (e: FormEvent) => {
    e.preventDefault()
    if (!sessionId || !selectedTicket || !name.trim()) return
    setSubmitting(true)
    router.post(
      `/s/${slug}/e/${event.slug}/register`,
      {
        sessionId: Number(sessionId),
        ticketTypeId: Number(selectedTicket.id),
        quantity: 1,
        buyerName: name,
        buyerEmail: email || null,
        buyerPhone: phone || null,
      },
      { onFinish: () => setSubmitting(false) }
    )
  }

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-white" style={style}>
      <SeoHead seo={seo} />
      <header className="border-b border-slate-200 bg-white px-4 py-4 dark:border-slate-800 dark:bg-slate-950">
        <div className="mx-auto flex max-w-3xl items-center justify-between">
          <Link href={`/s/${slug}`} className="text-sm font-bold">{company.name}</Link>
          <Link href={`/s/${slug}/events`} className="text-xs font-semibold text-slate-500">All events</Link>
        </div>
      </header>

      <main className="mx-auto grid max-w-3xl gap-8 px-4 py-10 lg:grid-cols-[1.1fr_0.9fr]">
        <div className="space-y-4">
          <h1 className="text-3xl font-extrabold tracking-tight">{event.title}</h1>
          {event.subtitle && <p className="text-slate-600 dark:text-slate-300">{event.subtitle}</p>}
          {event.venueName && (
            <p className="inline-flex items-center gap-1.5 text-sm text-slate-600">
              <MapPin className="h-4 w-4" /> {event.venueName}
              {event.venueAddress ? ` · ${event.venueAddress}` : ''}
            </p>
          )}
          {event.description && (
            <div className="whitespace-pre-line text-sm leading-relaxed text-slate-700 dark:text-slate-300">
              {event.description}
            </div>
          )}
          <div className="space-y-2">
            {event.sessions.map((session) => (
              <div key={session.id} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-800 dark:bg-slate-900">
                <div className="font-semibold">{session.title}</div>
                <div className="inline-flex items-center gap-1 text-slate-500">
                  <CalendarDays className="h-3.5 w-3.5" /> {fmt(session.startsAt, event.timezone)}
                </div>
              </div>
            ))}
          </div>
        </div>

        <form onSubmit={submit} className="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <h2 className="text-lg font-bold">Register</h2>
          {!event.registrationOpen && <p className="text-sm text-amber-700">Registration is closed.</p>}

          {event.sessions.length > 1 && (
            <div className="space-y-1.5">
              <Label>Session</Label>
              <select
                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-800 dark:bg-slate-950"
                value={sessionId}
                onChange={(e) => setSessionId(e.target.value)}
              >
                {event.sessions.map((s) => (
                  <option key={s.id} value={s.id} disabled={s.soldOut}>
                    {s.title} — {fmt(s.startsAt, event.timezone)}{s.soldOut ? ' (sold out)' : ''}
                  </option>
                ))}
              </select>
            </div>
          )}

          {ticketsForSession.length > 1 && (
            <div className="space-y-1.5">
              <Label>Ticket</Label>
              <select
                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-800 dark:bg-slate-950"
                value={selectedTicket?.id ?? ''}
                onChange={(e) => setTicketTypeId(e.target.value)}
              >
                {ticketsForSession.map((t) => (
                  <option key={t.id} value={t.id} disabled={t.soldOut}>
                    {t.name} — {t.isFree ? 'Free' : t.priceFormatted}
                  </option>
                ))}
              </select>
            </div>
          )}

          {selectedTicket && (
            <p className="text-sm font-semibold">
              {selectedTicket.isFree ? 'Free registration' : `${selectedTicket.priceFormatted} per person`}
            </p>
          )}

          <div className="space-y-1.5">
            <Label>Full name</Label>
            <Input required value={name} onChange={(e) => setName(e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label>Phone</Label>
            <Input value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="07…" />
          </div>
          <div className="space-y-1.5">
            <Label>Email</Label>
            <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
          </div>

          {details.length > 0 && (
            <div className="space-y-1 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
              <p className="inline-flex items-center gap-1 font-semibold">
                <CreditCard className="h-3.5 w-3.5" /> Payment details
              </p>
              {details.map((d, i) => (
                <p key={i} className="whitespace-pre-line">{d}</p>
              ))}
              <p className="text-[11px] opacity-80">Complete payment on the next page after you register.</p>
            </div>
          )}

          {(errors.event || errors.sessionId || errors.ticketTypeId || errors.buyerName || errors.buyerEmail) && (
            <p className="text-xs text-red-600">
              {errors.event || errors.sessionId || errors.ticketTypeId || errors.buyerName || errors.buyerEmail}
            </p>
          )}

          <Button type="submit" disabled={submitting || !event.registrationOpen} className="w-full rounded-2xl py-6 text-base">
            {submitting ? 'Registering…' : selectedTicket?.isFree ? 'Register' : 'Register and pay'}
          </Button>
        </form>
      </main>
      <StorefrontFooter slug={slug} company={company} />
    </div>
  )
}
