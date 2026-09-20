'use client'

import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'next/navigation'
import { parseEventsTab } from '@/components/dashboard/sidebar'
import { PageHeader } from '@/components/shared/page-header'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { Modal } from '@/components/shared/modal'
import { apiRequest } from '@/lib/api-client'
import { toast } from 'sonner'
import {
  CalendarDays,
  Copy,
  Loader2,
  Plus,
  Ticket,
  UserCheck,
} from 'lucide-react'

type SessionRow = {
  id: string
  title: string
  startsAt: string | null
  endsAt: string | null
  capacity: number | null
  remaining: number | null
  soldOut: boolean
  status: string
}

type TicketRow = {
  id: string
  name: string
  price: number
  priceFormatted: string
  remaining: number | null
  quantityTotal: number | null
  isFree: boolean
  onSale: boolean
  sessionId: string | null
  status: string
}

type EventRow = {
  id: string
  title: string
  slug: string
  subtitle?: string | null
  description?: string | null
  format: string
  venueName?: string | null
  onlineUrl?: string | null
  timezone: string
  status: string
  visibility: string
  publicUrl: string
  registrationOpen: boolean
  sessions: SessionRow[]
  ticketTypes: TicketRow[]
}

type RegistrationRow = {
  id: string
  buyerName: string
  buyerEmail?: string | null
  buyerPhone?: string | null
  quantity: number
  status: string
  sessionTitle?: string | null
  ticketName?: string | null
  attendees: { id: string; ticketCode: string; name: string; checkedInAt?: string | null; status: string }[]
}

function fmtWhen(iso: string | null | undefined): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString([], { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
}

export default function EventsPage() {
  const searchParams = useSearchParams()
  const tab = parseEventsTab(searchParams.get('tab'))
  const [loading, setLoading] = useState(true)
  const [events, setEvents] = useState<EventRow[]>([])
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [registrations, setRegistrations] = useState<RegistrationRow[]>([])
  const [ticketCode, setTicketCode] = useState('')
  const [lookup, setLookup] = useState<RegistrationRow['attendees'][number] | null>(null)
  const [createOpen, setCreateOpen] = useState(false)
  const [saving, setSaving] = useState(false)

  const [title, setTitle] = useState('')
  const [sessionTitle, setSessionTitle] = useState('1st Session')
  const [startsAt, setStartsAt] = useState('')
  const [endsAt, setEndsAt] = useState('')
  const [capacity, setCapacity] = useState('')
  const [ticketName, setTicketName] = useState('General admission')
  const [price, setPrice] = useState('1000')
  const [venue, setVenue] = useState('')
  const [format, setFormat] = useState('in_person')

  const selected = useMemo(() => events.find((e) => e.id === selectedId) ?? events[0] ?? null, [events, selectedId])

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const data = await apiRequest<{ events: EventRow[] }>('/api/company/events')
      setEvents(data.events ?? [])
      setSelectedId((prev) => {
        if (prev && (data.events ?? []).some((e) => e.id === prev)) return prev
        return data.events?.[0]?.id ?? null
      })
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Could not load events')
    } finally {
      setLoading(false)
    }
  }, [])

  const loadRegistrations = useCallback(async (eventId: string) => {
    try {
      const data = await apiRequest<{ registrations: RegistrationRow[] }>(`/api/company/events/${eventId}/registrations`)
      setRegistrations(data.registrations ?? [])
    } catch {
      setRegistrations([])
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (selected?.id && (tab === 'registrations' || tab === 'check-in' || tab === 'events')) {
      void loadRegistrations(selected.id)
    }
  }, [selected?.id, tab, loadRegistrations])

  const copyLink = async (url: string) => {
    await navigator.clipboard.writeText(url)
    toast.success('Registration link copied')
  }

  const createEvent = async (e: FormEvent) => {
    e.preventDefault()
    setSaving(true)
    try {
      const created = await apiRequest<{ event: EventRow }>('/api/company/events', {
        method: 'POST',
        body: {
          title,
          format,
          venueName: venue || null,
          visibility: 'unlisted',
          session: startsAt && endsAt ? { title: sessionTitle, startsAt, endsAt, capacity: capacity ? Number(capacity) : null } : undefined,
          ticket: { name: ticketName, price: Number(price) || 0, isFree: Number(price) <= 0 },
        },
      })
      toast.success('Event created')
      setCreateOpen(false)
      setTitle('')
      await load()
      if (created.event?.id) setSelectedId(created.event.id)
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Could not create event')
    } finally {
      setSaving(false)
    }
  }

  const publish = async (event: EventRow) => {
    try {
      await apiRequest(`/api/company/events/${event.id}/publish`, { method: 'POST' })
      toast.success('Event published')
      await load()
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Publish failed — add a session and ticket first')
    }
  }

  const addSession = async () => {
    if (!selected || !startsAt || !endsAt) return
    try {
      await apiRequest(`/api/company/events/${selected.id}/sessions`, {
        method: 'POST',
        body: { title: sessionTitle || 'Session', startsAt, endsAt, capacity: capacity ? Number(capacity) : null },
      })
      toast.success('Session added')
      await load()
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Could not add session')
    }
  }

  const addTicket = async () => {
    if (!selected) return
    try {
      await apiRequest(`/api/company/events/${selected.id}/ticket-types`, {
        method: 'POST',
        body: { name: ticketName || 'General admission', price: Number(price) || 0, isFree: Number(price) <= 0 },
      })
      toast.success('Ticket type added')
      await load()
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Could not add ticket')
    }
  }

  const checkIn = async (eventId: string, attendeeId: string) => {
    try {
      await apiRequest(`/api/company/events/${eventId}/attendees/${attendeeId}/check-in`, { method: 'POST' })
      toast.success('Checked in')
      await loadRegistrations(eventId)
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Check-in failed')
    }
  }

  const lookupTicket = async () => {
    if (!selected || !ticketCode.trim()) return
    try {
      const data = await apiRequest<{ attendee: RegistrationRow['attendees'][number] }>(
        `/api/company/events/${selected.id}/tickets/lookup?code=${encodeURIComponent(ticketCode.trim())}`
      )
      setLookup(data.attendee)
    } catch {
      setLookup(null)
      toast.error('Ticket not found')
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Events"
        description="Publish a registration link, collect payment, and issue tickets."
        icon={Ticket}
        actions={
          <Button onClick={() => setCreateOpen(true)} className="gap-1.5">
            <Plus className="h-4 w-4" /> New event
          </Button>
        }
      />

      {loading ? (
        <div className="flex justify-center py-16">
          <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
      ) : (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,18rem)_1fr]">
          <aside className="space-y-2">
            {events.length === 0 && (
              <p className="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                No events yet. Create one to get a shareable registration link.
              </p>
            )}
            {events.map((event) => (
              <button
                key={event.id}
                type="button"
                onClick={() => setSelectedId(event.id)}
                className={`w-full rounded-xl border p-3 text-left ${selected?.id === event.id ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/40'}`}
              >
                <div className="flex items-center justify-between gap-2">
                  <span className="font-semibold">{event.title}</span>
                  <Badge variant={event.status === 'published' ? 'default' : 'secondary'}>{event.status}</Badge>
                </div>
                <p className="mt-1 text-xs text-muted-foreground">{event.sessions[0] ? fmtWhen(event.sessions[0].startsAt) : 'No session yet'}</p>
              </button>
            ))}
          </aside>

          {selected && (
            <section className="space-y-5">
              <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border p-4">
                <div>
                  <h2 className="text-lg font-semibold">{selected.title}</h2>
                  <p className="text-sm text-muted-foreground break-all">{selected.publicUrl}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button variant="outline" size="sm" onClick={() => copyLink(selected.publicUrl)} className="gap-1.5">
                    <Copy className="h-4 w-4" /> Copy link
                  </Button>
                  {selected.status !== 'published' && (
                    <Button size="sm" onClick={() => publish(selected)}>Publish</Button>
                  )}
                </div>
              </div>

              {(tab === 'events' || tab === 'sessions') && (
                <div className="space-y-3 rounded-2xl border p-4">
                  <h3 className="flex items-center gap-2 font-semibold"><CalendarDays className="h-4 w-4" /> Sessions</h3>
                  {selected.sessions.map((session) => (
                    <div key={session.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-muted/40 px-3 py-2 text-sm">
                      <div>
                        <div className="font-medium">{session.title}</div>
                        <div className="text-muted-foreground">{fmtWhen(session.startsAt)} – {fmtWhen(session.endsAt)}</div>
                      </div>
                      <Badge variant={session.soldOut ? 'secondary' : 'outline'}>
                        {session.capacity == null ? 'Open' : `${session.remaining ?? 0} / ${session.capacity} left`}
                      </Badge>
                    </div>
                  ))}
                  <div className="grid gap-2 sm:grid-cols-2">
                    <Input placeholder="Session title" value={sessionTitle} onChange={(e) => setSessionTitle(e.target.value)} />
                    <Input placeholder="Capacity" type="number" value={capacity} onChange={(e) => setCapacity(e.target.value)} />
                    <div>
                      <Label className="text-xs">Starts</Label>
                      <Input type="datetime-local" value={startsAt} onChange={(e) => setStartsAt(e.target.value)} />
                    </div>
                    <div>
                      <Label className="text-xs">Ends</Label>
                      <Input type="datetime-local" value={endsAt} onChange={(e) => setEndsAt(e.target.value)} />
                    </div>
                  </div>
                  <Button type="button" variant="outline" onClick={addSession}>Add session</Button>
                </div>
              )}

              {(tab === 'events' || tab === 'tickets') && (
                <div className="space-y-3 rounded-2xl border p-4">
                  <h3 className="flex items-center gap-2 font-semibold"><Ticket className="h-4 w-4" /> Tickets</h3>
                  {selected.ticketTypes.map((ticket) => (
                    <div key={ticket.id} className="flex items-center justify-between rounded-xl bg-muted/40 px-3 py-2 text-sm">
                      <span className="font-medium">{ticket.name}</span>
                      <span>{ticket.isFree ? 'Free' : ticket.priceFormatted}</span>
                    </div>
                  ))}
                  <div className="grid gap-2 sm:grid-cols-2">
                    <Input placeholder="Ticket name" value={ticketName} onChange={(e) => setTicketName(e.target.value)} />
                    <Input placeholder="Price" type="number" value={price} onChange={(e) => setPrice(e.target.value)} />
                  </div>
                  <Button type="button" variant="outline" onClick={addTicket}>Add ticket type</Button>
                </div>
              )}

              {(tab === 'registrations' || tab === 'events') && (
                <div className="space-y-3 rounded-2xl border p-4">
                  <h3 className="font-semibold">Registrations</h3>
                  {registrations.length === 0 && tab === 'registrations' && (
                    <p className="text-sm text-muted-foreground">No registrations yet.</p>
                  )}
                  {registrations.map((row) => (
                    <div key={row.id} className="rounded-xl border px-3 py-2 text-sm">
                      <div className="flex justify-between gap-2">
                        <span className="font-medium">{row.buyerName}</span>
                        <Badge variant={row.status === 'confirmed' ? 'default' : 'secondary'}>{row.status}</Badge>
                      </div>
                      <p className="text-muted-foreground">{row.ticketName} · qty {row.quantity}</p>
                    </div>
                  ))}
                </div>
              )}

              {tab === 'check-in' && (
                <div className="space-y-3 rounded-2xl border p-4">
                  <h3 className="flex items-center gap-2 font-semibold"><UserCheck className="h-4 w-4" /> Check-in</h3>
                  <div className="flex gap-2">
                    <Input placeholder="Ticket code e.g. EVT-AB12CD34" value={ticketCode} onChange={(e) => setTicketCode(e.target.value)} />
                    <Button type="button" onClick={lookupTicket}>Lookup</Button>
                  </div>
                  {lookup && (
                    <div className="rounded-xl border p-3 text-sm">
                      <div className="font-medium">{lookup.name} · {lookup.ticketCode}</div>
                      <p className="text-muted-foreground">{lookup.checkedInAt ? `Checked in ${fmtWhen(lookup.checkedInAt)}` : 'Not checked in'}</p>
                      {!lookup.checkedInAt && (
                        <Button className="mt-2" size="sm" onClick={() => checkIn(selected.id, lookup.id)}>Check in</Button>
                      )}
                    </div>
                  )}
                  {registrations.flatMap((r) => r.attendees).map((a) => (
                    <div key={a.id} className="flex items-center justify-between rounded-xl bg-muted/40 px-3 py-2 text-sm">
                      <span>{a.name} · {a.ticketCode}</span>
                      {a.checkedInAt ? <Badge>In</Badge> : <Button size="sm" variant="outline" onClick={() => checkIn(selected.id, a.id)}>Check in</Button>}
                    </div>
                  ))}
                </div>
              )}
            </section>
          )}
        </div>
      )}

      <Modal open={createOpen} onOpenChange={setCreateOpen} title="New event" description="Create the gathering, first session, and ticket in one step.">
        <form className="space-y-3" onSubmit={createEvent}>
          <div className="space-y-1.5">
            <Label>Title</Label>
            <Input required value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Heal to Lead Bootcamp" />
          </div>
          <div className="grid gap-2 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label>Session title</Label>
              <Input value={sessionTitle} onChange={(e) => setSessionTitle(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Capacity</Label>
              <Input type="number" value={capacity} onChange={(e) => setCapacity(e.target.value)} placeholder="Optional" />
            </div>
            <div className="space-y-1.5">
              <Label>Starts</Label>
              <Input type="datetime-local" value={startsAt} onChange={(e) => setStartsAt(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Ends</Label>
              <Input type="datetime-local" value={endsAt} onChange={(e) => setEndsAt(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Ticket name</Label>
              <Input value={ticketName} onChange={(e) => setTicketName(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Price</Label>
              <Input type="number" min="0" step="0.01" value={price} onChange={(e) => setPrice(e.target.value)} />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label>Venue (optional)</Label>
            <Input value={venue} onChange={(e) => setVenue(e.target.value)} />
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>Cancel</Button>
            <Button type="submit" disabled={saving}>{saving ? 'Creating…' : 'Create event'}</Button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
