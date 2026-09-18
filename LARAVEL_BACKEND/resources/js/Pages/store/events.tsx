'use client'

import { Link } from '@inertiajs/react'
import { CalendarDays, MapPin, Ticket } from 'lucide-react'
import { SeoHead, type SeoPayload } from '@/components/seo/SeoHead'
import { StorefrontFooter } from '@/components/store/StorefrontFooter'
import { resolveStorefrontStyle, type BrandTheme } from '@/lib/theme-utils'

type EventCard = {
  id: string
  title: string
  slug: string
  subtitle?: string | null
  publicUrl: string
  venueName?: string | null
  sessions: { id: string; title: string; startsAt: string | null }[]
  ticketTypes: { id: string; priceFormatted: string; isFree: boolean }[]
}

type Props = {
  slug: string
  company: {
    name: string
    logo?: string | null
    theme?: BrandTheme
    aboutUrl?: string
    termsUrl?: string
    whatsappUrl?: string | null
  }
  events: EventCard[]
  seo?: SeoPayload | null
}

function fmt(iso: string | null) {
  if (!iso) return ''
  return new Date(iso).toLocaleString([], { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
}

export default function StoreEventsPage({ slug, company, events, seo }: Props) {
  const style = resolveStorefrontStyle(company.theme)

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-white" style={style}>
      <SeoHead seo={seo} />
      <header className="border-b border-slate-200 bg-white px-4 py-4 dark:border-slate-800 dark:bg-slate-950">
        <div className="mx-auto flex max-w-3xl items-center justify-between">
          <Link href={`/s/${slug}`} className="text-sm font-bold">{company.name}</Link>
          <Link href={`/s/${slug}`} className="text-xs font-semibold text-slate-500">Shop</Link>
        </div>
      </header>
      <main className="mx-auto max-w-3xl space-y-6 px-4 py-10">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">Events</p>
          <h1 className="mt-1 text-3xl font-extrabold tracking-tight">Upcoming gatherings</h1>
        </div>
        {events.length === 0 && (
          <p className="rounded-2xl border border-dashed border-slate-300 p-8 text-sm text-slate-500">No public events right now.</p>
        )}
        <div className="space-y-4">
          {events.map((event) => (
            <Link
              key={event.id}
              href={`/s/${slug}/e/${event.slug}`}
              className="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900"
            >
              <h2 className="text-lg font-bold">{event.title}</h2>
              {event.subtitle && <p className="mt-1 text-sm text-slate-500">{event.subtitle}</p>}
              <div className="mt-3 flex flex-wrap gap-3 text-xs font-medium text-slate-600 dark:text-slate-300">
                {event.sessions[0] && (
                  <span className="inline-flex items-center gap-1"><CalendarDays className="h-3.5 w-3.5" /> {fmt(event.sessions[0].startsAt)}</span>
                )}
                {event.venueName && (
                  <span className="inline-flex items-center gap-1"><MapPin className="h-3.5 w-3.5" /> {event.venueName}</span>
                )}
                {event.ticketTypes[0] && (
                  <span className="inline-flex items-center gap-1"><Ticket className="h-3.5 w-3.5" /> {event.ticketTypes[0].isFree ? 'Free' : event.ticketTypes[0].priceFormatted}</span>
                )}
              </div>
            </Link>
          ))}
        </div>
      </main>
      <StorefrontFooter slug={slug} company={company} />
    </div>
  )
}
