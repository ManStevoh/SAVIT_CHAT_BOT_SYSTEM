'use client'

import { Button } from '@/components/ui/button'

type Props = {
  company: { name?: string }
  booking: {
    title?: string | null
    startsAt: string
    endsAt: string
    status: string
    customerName: string
    googleCalendarUrl: string
    icsUrl: string
    timezone: string
  }
}

export default function BookingConfirmationPage({ company, booking }: Props) {
  return (
    <div className="min-h-screen bg-slate-50 px-4 py-16">
      <div className="mx-auto max-w-lg space-y-6 rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <p className="text-sm uppercase tracking-[0.2em] text-slate-500">Confirmed</p>
        <h1 className="text-2xl font-semibold">{booking.title || 'Your meeting'}</h1>
        <p className="text-slate-600">
          Thanks {booking.customerName}. You&apos;re booked with {company.name || 'us'}.
        </p>
        <div className="rounded-xl bg-slate-50 p-4 text-sm">
          <p>
            <strong>When:</strong>{' '}
            {new Date(booking.startsAt).toLocaleString(undefined, { timeZone: booking.timezone })}
          </p>
          <p>
            <strong>Until:</strong>{' '}
            {new Date(booking.endsAt).toLocaleTimeString(undefined, { timeZone: booking.timezone })}
          </p>
          <p>
            <strong>Status:</strong> {booking.status}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button asChild>
            <a href={booking.googleCalendarUrl} target="_blank" rel="noreferrer">
              Add to Google Calendar
            </a>
          </Button>
          <Button variant="outline" asChild>
            <a href={booking.icsUrl}>Download ICS</a>
          </Button>
        </div>
      </div>
    </div>
  )
}
