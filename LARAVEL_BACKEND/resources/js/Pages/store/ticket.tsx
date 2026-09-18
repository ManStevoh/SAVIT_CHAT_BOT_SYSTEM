'use client'

import { CalendarDays, Download, MapPin, Ticket } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { SeoHead, type SeoPayload } from '@/components/seo/SeoHead'

type Attendee = {
  name: string
  ticketCode: string
  ticketUrl: string
  icsUrl: string
  googleCalendarUrl: string
  status: string
  eventTitle?: string | null
  sessionTitle?: string | null
  startsAt?: string | null
  endsAt?: string | null
  venueName?: string | null
  joinUrl?: string | null
  checkedInAt?: string | null
}

type Props = {
  attendee: Attendee
  company: { name: string }
  seo?: SeoPayload | null
}

function fmt(iso: string | null | undefined) {
  if (!iso) return ''
  return new Date(iso).toLocaleString([], {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

export default function EventTicketPage({ attendee, company, seo }: Props) {
  const cancelled = attendee.status === 'cancelled'

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12 dark:bg-slate-950">
      <SeoHead seo={seo} />
      <div className="w-full max-w-md space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900">
        <div className="text-center">
          <p className="text-xs font-bold uppercase tracking-wider text-slate-400">{company.name}</p>
          <h1 className="mt-1 text-2xl font-extrabold">{attendee.eventTitle || 'Event ticket'}</h1>
          {attendee.sessionTitle && <p className="text-sm text-slate-500">{attendee.sessionTitle}</p>}
        </div>

        <div className="rounded-2xl bg-slate-900 px-4 py-5 text-center text-white dark:bg-emerald-700">
          <p className="text-[11px] font-semibold uppercase tracking-wider opacity-70">Ticket code</p>
          <p className="mt-1 font-mono text-2xl font-extrabold tracking-wider">{attendee.ticketCode}</p>
          <p className="mt-1 text-xs opacity-80">{attendee.name}</p>
        </div>

        {cancelled ? (
          <p className="rounded-xl bg-red-50 p-3 text-sm text-red-700">This ticket has been cancelled.</p>
        ) : (
          <div className="space-y-2 text-sm text-slate-600 dark:text-slate-300">
            {attendee.startsAt && (
              <p className="inline-flex items-center gap-1.5"><CalendarDays className="h-4 w-4" /> {fmt(attendee.startsAt)}</p>
            )}
            {attendee.venueName && (
              <p className="flex items-center gap-1.5"><MapPin className="h-4 w-4" /> {attendee.venueName}</p>
            )}
            {attendee.joinUrl && (
              <a href={attendee.joinUrl} className="block font-semibold text-emerald-700 underline" target="_blank" rel="noreferrer">
                Join online
              </a>
            )}
          </div>
        )}

        {!cancelled && (
          <div className="flex flex-col gap-2">
            <Button asChild className="rounded-2xl">
              <a href={attendee.icsUrl}><Download className="mr-2 h-4 w-4" /> Add to calendar</a>
            </Button>
            <Button asChild variant="outline" className="rounded-2xl">
              <a href={attendee.googleCalendarUrl} target="_blank" rel="noreferrer">Google Calendar</a>
            </Button>
          </div>
        )}

        <p className="text-center text-[11px] text-slate-400">
          <Ticket className="mr-1 inline h-3 w-3" /> Show this code at check-in
        </p>
      </div>
    </div>
  )
}
