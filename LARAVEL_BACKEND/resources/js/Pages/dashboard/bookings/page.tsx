'use client'

import { useCallback, useEffect, useMemo, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { apiRequest } from '@/lib/api-client'
import { PlanLimitBar, UpgradePrompt, LockedFeatureGate } from '@/components/shared/upgrade-prompt'
import { SettingSection, SettingRow, SaveBar } from '@/components/settings/shared'
import { isAtLimit } from '@/lib/use-plan'
import { cn } from '@/lib/utils'
import { toast } from 'sonner'
import {
  Calendar as CalendarIcon,
  CalendarClock,
  CalendarDays,
  Check,
  ChevronLeft,
  ChevronRight,
  Copy,
  ExternalLink,
  History,
  Link2,
  Loader2,
  Mail,
  MoreHorizontal,
  Phone,
  RefreshCw,
  Settings2,
} from 'lucide-react'

type AvailabilityRow = { weekday: number; startTime: string; endTime: string }

type BookingSettings = {
  timezone: string
  defaultDurationMinutes: number
  bufferMinutes: number
  minNoticeMinutes: number
  maxDaysAhead: number
  publicSlug: string
  calendarWebhookUrl: string | null
  isEnabled: boolean
  paymentRequirement?: 'at_venue' | 'required' | 'optional'
  whatsappBookingMode?: 'whatsapp_native' | 'web_link' | 'hybrid'
}

type SettingsPayload = {
  settings: BookingSettings
  availability: AvailabilityRow[]
  publicBookingUrl: string
  calendarFeedUrl: string
  maxBookingsPerMonth: number | null
  bookingsThisMonth: number
  message?: string
  code?: string
}

type BookingRow = {
  id: string
  title: string | null
  productName?: string | null
  customerName: string
  customerEmail?: string | null
  customerPhone?: string | null
  startsAt: string
  endsAt: string
  status: string
  notes?: string | null
  googleCalendarUrl: string
  icsUrl: string
}

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const WEEKDAYS_LONG = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

const DEFAULT_AVAILABILITY: AvailabilityRow[] = [1, 2, 3, 4, 5].map((weekday) => ({
  weekday,
  startTime: '09:00',
  endTime: '17:00',
}))

const STATUS_META: Record<string, { label: string; dot: string; badge: 'default' | 'secondary' | 'outline' }> = {
  pending: { label: 'Pending', dot: 'bg-amber-500', badge: 'secondary' },
  confirmed: { label: 'Confirmed', dot: 'bg-emerald-500', badge: 'default' },
  completed: { label: 'Done', dot: 'bg-slate-400', badge: 'outline' },
  cancelled: { label: 'Cancelled', dot: 'bg-slate-300', badge: 'outline' },
}

function statusOf(b: BookingRow) {
  return STATUS_META[b.status] ?? { label: b.status, dot: 'bg-slate-400', badge: 'outline' as const }
}

function fmtTime(iso: string) {
  return new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
}
function fmtDate(iso: string) {
  return new Date(iso).toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' })
}
function dayKey(d: Date) {
  return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`
}
function sameDay(a: Date, b: Date) {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()
}

export default function BookingsPage() {
  const [tab, setTab] = useState<'schedule' | 'setup'>('schedule')
  const [view, setView] = useState<'upcoming' | 'calendar' | 'past'>('upcoming')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState<string | null>(null)
  const [saveOk, setSaveOk] = useState(false)
  const [blocked, setBlocked] = useState<string | null>(null)
  const [settings, setSettings] = useState<BookingSettings | null>(null)
  const [availability, setAvailability] = useState<AvailabilityRow[]>(DEFAULT_AVAILABILITY)
  const [publicUrl, setPublicUrl] = useState('')
  const [calendarUrl, setCalendarUrl] = useState('')
  const [usage, setUsage] = useState({ used: 0, max: null as number | null })
  const [bookings, setBookings] = useState<BookingRow[]>([])
  const [error, setError] = useState<string | null>(null)
  const [actingId, setActingId] = useState<string | null>(null)
  const [monthCursor, setMonthCursor] = useState(() => {
    const n = new Date()
    return new Date(n.getFullYear(), n.getMonth(), 1)
  })
  const [selectedDay, setSelectedDay] = useState<Date | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await apiRequest<SettingsPayload>('/api/company/bookings/settings')
      if ((data as { code?: string }).code === 'bookings_required') {
        setBlocked((data as { message?: string }).message || 'Bookings are not on your plan.')
        setSettings(null)
        return
      }
      setBlocked(null)
      setSettings(data.settings)
      setAvailability(data.availability?.length ? data.availability : DEFAULT_AVAILABILITY)
      setPublicUrl(data.publicBookingUrl)
      setCalendarUrl(data.calendarFeedUrl)
      setUsage({ used: data.bookingsThisMonth, max: data.maxBookingsPerMonth })

      const list = await apiRequest<{ bookings: BookingRow[] }>('/api/company/bookings')
      setBookings(list.bookings || [])
    } catch (e) {
      const err = e as Error & { code?: string }
      if (err.code === 'bookings_required') {
        setBlocked(err.message || 'Bookings are not on your plan.')
      } else {
        setError(err.message || 'Failed to load bookings')
      }
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const setStatus = async (id: string, status: 'pending' | 'confirmed' | 'cancelled' | 'completed') => {
    setActingId(id)
    try {
      const res = await apiRequest<{ success: boolean; booking?: BookingRow; message?: string }>(
        `/api/company/bookings/${id}`,
        { method: 'PATCH', body: { status } }
      )
      if (!res.success) {
        toast.error(res.message ?? 'Could not update booking.')
        return
      }
      setBookings((prev) => prev.map((b) => (b.id === id && res.booking ? { ...b, ...res.booking } : b)))
      toast.success(
        status === 'confirmed' ? 'Booking confirmed.' : status === 'cancelled' ? 'Booking cancelled.' : 'Booking updated.'
      )
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Could not update booking.')
    } finally {
      setActingId(null)
    }
  }

  const saveSetup = async () => {
    if (!settings) return
    setSaving(true)
    setSaveError(null)
    setSaveOk(false)
    try {
      const data = await apiRequest<SettingsPayload>('/api/company/bookings/settings', {
        method: 'PUT',
        body: {
          timezone: settings.timezone,
          defaultDurationMinutes: settings.defaultDurationMinutes,
          bufferMinutes: settings.bufferMinutes,
          minNoticeMinutes: settings.minNoticeMinutes,
          maxDaysAhead: settings.maxDaysAhead,
          publicSlug: settings.publicSlug,
          calendarWebhookUrl: settings.calendarWebhookUrl || null,
          isEnabled: settings.isEnabled,
          paymentRequirement: settings.paymentRequirement ?? 'at_venue',
          whatsappBookingMode: settings.whatsappBookingMode ?? 'hybrid',
          availability,
        },
      })
      setSettings(data.settings)
      setAvailability(data.availability?.length ? data.availability : availability)
      setPublicUrl(data.publicBookingUrl)
      setCalendarUrl(data.calendarFeedUrl)
      setSaveOk(true)
      setTimeout(() => setSaveOk(false), 3000)
      toast.success('Booking page saved.')
    } catch (e) {
      setSaveError(e instanceof Error ? e.message : 'Failed to save')
    } finally {
      setSaving(false)
    }
  }

  const copy = async (value: string) => {
    try {
      await navigator.clipboard.writeText(value)
      toast.success('Link copied')
    } catch {
      /* ignore */
    }
  }

  const now = useMemo(() => new Date(), [])
  const upcoming = useMemo(
    () =>
      bookings
        .filter((b) => new Date(b.startsAt) >= now && ['pending', 'confirmed'].includes(b.status))
        .sort((a, b) => +new Date(a.startsAt) - +new Date(b.startsAt)),
    [bookings, now]
  )
  const past = useMemo(
    () =>
      bookings
        .filter((b) => new Date(b.startsAt) < now || ['completed', 'cancelled'].includes(b.status))
        .sort((a, b) => +new Date(b.startsAt) - +new Date(a.startsAt)),
    [bookings, now]
  )
  const byDay = useMemo(() => {
    const map = new Map<string, BookingRow[]>()
    for (const b of bookings) {
      const k = dayKey(new Date(b.startsAt))
      const arr = map.get(k) ?? []
      arr.push(b)
      map.set(k, arr)
    }
    return map
  }, [bookings])

  const monthCells = useMemo(() => {
    const y = monthCursor.getFullYear()
    const m = monthCursor.getMonth()
    const lead = new Date(y, m, 1).getDay()
    const cells: Date[] = []
    for (let i = lead; i > 0; i--) cells.push(new Date(y, m, 1 - i))
    const daysInMonth = new Date(y, m + 1, 0).getDate()
    for (let d = 1; d <= daysInMonth; d++) cells.push(new Date(y, m, d))
    while (cells.length % 7 !== 0) {
      const last = cells[cells.length - 1]
      cells.push(new Date(last.getFullYear(), last.getMonth(), last.getDate() + 1))
    }
    return cells
  }, [monthCursor])

  const selectedDayBookings = selectedDay
    ? (byDay.get(dayKey(selectedDay)) ?? []).sort((a, b) => +new Date(a.startsAt) - +new Date(b.startsAt))
    : []

  if (loading) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-muted-foreground">
        <Loader2 className="mr-2 h-5 w-5 animate-spin" /> Loading bookings…
      </div>
    )
  }

  if (blocked) {
    return (
      <LockedFeatureGate
        icon={CalendarClock}
        title="Bookings aren't on your current plan"
        description={blocked}
      />
    )
  }

  if (!settings) return null
  const atLimit = isAtLimit(usage.used, usage.max)

  return (
    <div className="w-full space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-foreground">Bookings</h1>
          <p className="mt-0.5 text-sm text-muted-foreground">
            {upcoming.length} upcoming · {usage.used}{usage.max != null ? ` of ${usage.max}` : ''} this month
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={() => void copy(publicUrl)}>
            <Copy className="mr-1.5 h-3.5 w-3.5" /> Booking link
          </Button>
          <Button variant="ghost" size="sm" onClick={() => void load()}>
            <RefreshCw className="mr-1.5 h-3.5 w-3.5" /> Refresh
          </Button>
        </div>
      </div>

      {error && (
        <p className="rounded-xl border border-destructive/40 bg-destructive/10 px-4 py-2.5 text-sm text-destructive">{error}</p>
      )}
      {atLimit && (
        <UpgradePrompt
          title="You've used this month's bookings"
          description="Starter includes 30 bookings a month. Growth raises you to 150 — same calendar, same public page, more customers."
          compact
        />
      )}

      {/* Tabs */}
      <div className="overflow-x-auto pb-1">
        <div className="flex min-w-max gap-1 rounded-2xl border border-border bg-muted/50 p-1.5 sm:inline-flex sm:min-w-0">
          {([
            { id: 'schedule', label: 'Schedule', icon: CalendarDays },
            { id: 'setup', label: 'Setup', icon: Settings2 },
          ] as const).map((t) => (
            <button
              key={t.id}
              type="button"
              onClick={() => setTab(t.id)}
              className={cn(
                'flex items-center gap-2 rounded-xl px-3.5 py-2 text-[13px] font-medium transition-all',
                tab === t.id ? 'bg-background text-foreground shadow-sm ring-1 ring-border' : 'text-muted-foreground hover:text-foreground'
              )}
            >
              <t.icon className={cn('h-4 w-4', tab === t.id && 'text-primary')} />
              {t.label}
            </button>
          ))}
        </div>
      </div>

      {tab === 'schedule' && (
        <div className="space-y-4">
          {/* View switch */}
          <div className="flex gap-1 rounded-xl border border-border bg-muted/40 p-1 sm:w-fit">
            {([
              { id: 'upcoming', label: `Upcoming (${upcoming.length})`, icon: CalendarClock },
              { id: 'calendar', label: 'Calendar', icon: CalendarIcon },
              { id: 'past', label: 'Past', icon: History },
            ] as const).map((v) => (
              <button
                key={v.id}
                type="button"
                onClick={() => setView(v.id)}
                className={cn(
                  'flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] font-medium transition-colors sm:flex-none',
                  view === v.id ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                )}
              >
                <v.icon className="h-3.5 w-3.5" />
                {v.label}
              </button>
            ))}
          </div>

          {view === 'calendar' && (
            <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
              <div className="rounded-2xl border border-border p-4">
                <div className="mb-3 flex items-center justify-between">
                  <p className="text-sm font-semibold text-foreground">
                    {monthCursor.toLocaleDateString([], { month: 'long', year: 'numeric' })}
                  </p>
                  <div className="flex items-center gap-1">
                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setMonthCursor(new Date(monthCursor.getFullYear(), monthCursor.getMonth() - 1, 1))}>
                      <ChevronLeft className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={() => { const n = new Date(); setMonthCursor(new Date(n.getFullYear(), n.getMonth(), 1)) }}>
                      Today
                    </Button>
                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setMonthCursor(new Date(monthCursor.getFullYear(), monthCursor.getMonth() + 1, 1))}>
                      <ChevronRight className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
                <div className="grid grid-cols-7 gap-1 text-center text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                  {WEEKDAYS.map((d) => <span key={d} className="py-1">{d}</span>)}
                </div>
                <div className="grid grid-cols-7 gap-1">
                  {monthCells.map((d, i) => {
                    const inMonth = d.getMonth() === monthCursor.getMonth()
                    const items = byDay.get(dayKey(d)) ?? []
                    const isToday = sameDay(d, new Date())
                    const isSel = selectedDay ? sameDay(d, selectedDay) : false
                    return (
                      <button
                        key={i}
                        type="button"
                        onClick={() => setSelectedDay(d)}
                        className={cn(
                          'flex min-h-[3.5rem] flex-col items-center gap-1 rounded-lg border p-1 transition-colors',
                          isSel ? 'border-primary bg-primary/[0.07]' : 'border-transparent hover:border-border hover:bg-muted/40',
                          !inMonth && 'opacity-40'
                        )}
                      >
                        <span className={cn(
                          'flex h-6 w-6 items-center justify-center rounded-full text-xs',
                          isToday ? 'bg-primary font-bold text-primary-foreground' : 'text-foreground'
                        )}>
                          {d.getDate()}
                        </span>
                        <span className="flex gap-0.5">
                          {items.slice(0, 3).map((b) => (
                            <span key={b.id} className={cn('h-1.5 w-1.5 rounded-full', statusOf(b).dot)} />
                          ))}
                        </span>
                      </button>
                    )
                  })}
                </div>
              </div>
              <div className="space-y-3">
                <p className="text-sm font-semibold text-foreground">
                  {selectedDay ? selectedDay.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' }) : 'Pick a day'}
                </p>
                {selectedDayBookings.length === 0 ? (
                  <p className="rounded-xl border border-dashed p-5 text-center text-[13px] text-muted-foreground">
                    {selectedDay ? 'Nothing booked this day.' : 'Select a date to see its bookings.'}
                  </p>
                ) : (
                  selectedDayBookings.map((b) => <BookingCard key={b.id} booking={b} busy={actingId === b.id} onStatus={(s) => void setStatus(b.id, s)} />)
                )}
              </div>
            </div>
          )}

          {view !== 'calendar' && (
            <div className="space-y-2.5">
              {(view === 'upcoming' ? upcoming : past).length === 0 ? (
                <div className="rounded-2xl border border-dashed p-10 text-center">
                  <CalendarClock className="mx-auto h-8 w-8 text-muted-foreground/50" />
                  <p className="mt-2 text-sm font-semibold text-foreground">
                    {view === 'upcoming' ? 'No upcoming bookings' : 'No past bookings'}
                  </p>
                  <p className="mx-auto mt-1 max-w-sm text-[13px] text-muted-foreground">
                    {view === 'upcoming'
                      ? 'Share your booking link and new appointments will land here for confirmation.'
                      : 'Completed and cancelled bookings will appear here.'}
                  </p>
                  {view === 'upcoming' && (
                    <Button variant="outline" size="sm" className="mt-3" onClick={() => void copy(publicUrl)}>
                      <Copy className="mr-1.5 h-3.5 w-3.5" /> Copy booking link
                    </Button>
                  )}
                </div>
              ) : (
                (view === 'upcoming' ? upcoming : past).map((b) => (
                  <BookingCard key={b.id} booking={b} busy={actingId === b.id} onStatus={(s) => void setStatus(b.id, s)} />
                ))
              )}
            </div>
          )}
        </div>
      )}

      {tab === 'setup' && settings && (
        <div className="space-y-4">
          <SettingSection
            title="Booking page"
            description="Your public scheduling page — turn it on, name the link, share it anywhere."
            badge={settings.isEnabled ? <Badge className="text-[11px]">Live</Badge> : <Badge variant="secondary" className="text-[11px] font-normal">Paused</Badge>}
            actions={<Switch checked={settings.isEnabled} onCheckedChange={(v) => setSettings({ ...settings, isEnabled: v })} />}
          >
            <div className="flex flex-wrap items-center gap-2 rounded-xl bg-muted/40 p-3">
              <Link2 className="h-4 w-4 shrink-0 text-muted-foreground" />
              <code className="min-w-0 flex-1 truncate font-mono text-xs">{publicUrl}</code>
              <Button variant="outline" size="sm" className="h-8 gap-1" onClick={() => void copy(publicUrl)}>
                <Copy className="h-3.5 w-3.5" /> Copy
              </Button>
              <Button variant="outline" size="sm" className="h-8 gap-1" asChild>
                <a href={publicUrl} target="_blank" rel="noreferrer">
                  <ExternalLink className="h-3.5 w-3.5" /> Open
                </a>
              </Button>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label>Page address</Label>
                <Input value={settings.publicSlug} onChange={(e) => setSettings({ ...settings, publicSlug: e.target.value })} placeholder="my-business" className="font-mono text-sm" />
              </div>
              <div className="space-y-1.5">
                <Label>Timezone</Label>
                <Input value={settings.timezone} onChange={(e) => setSettings({ ...settings, timezone: e.target.value })} placeholder="Africa/Nairobi" />
              </div>
            </div>
            <div className="space-y-1.5">
              <PlanLimitBar used={usage.used} limit={usage.max} label="Monthly bookings" unit="bookings" />
            </div>
          </SettingSection>

          <SettingSection title="Availability" description="When customers can book.">
            <div className="space-y-2">
              {availability.map((row, idx) => (
                <div key={`${row.weekday}-${idx}`} className="flex items-center gap-2">
                  <Select
                    value={String(row.weekday)}
                    onValueChange={(v) => {
                      const next = [...availability]
                      next[idx] = { ...row, weekday: Number(v) }
                      setAvailability(next)
                    }}
                  >
                    <SelectTrigger className="w-32"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {WEEKDAYS_LONG.map((label, day) => (
                        <SelectItem key={label} value={String(day)}>{label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <Input
                    type="time"
                    value={row.startTime}
                    onChange={(e) => {
                      const next = [...availability]
                      next[idx] = { ...row, startTime: e.target.value }
                      setAvailability(next)
                    }}
                    className="w-28"
                  />
                  <span className="text-xs text-muted-foreground">to</span>
                  <Input
                    type="time"
                    value={row.endTime}
                    onChange={(e) => {
                      const next = [...availability]
                      next[idx] = { ...row, endTime: e.target.value }
                      setAvailability(next)
                    }}
                    className="w-28"
                  />
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="text-xs text-muted-foreground hover:text-destructive"
                    onClick={() => setAvailability(availability.filter((_, i) => i !== idx))}
                  >
                    Remove
                  </Button>
                </div>
              ))}
            </div>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setAvailability((prev) => [...prev, { weekday: 1, startTime: '09:00', endTime: '17:00' }])}
            >
              Add hours
            </Button>
            <div className="grid gap-3 rounded-xl bg-muted/40 p-4 sm:grid-cols-3">
              <div className="space-y-1.5">
                <Label>Slot length (min)</Label>
                <Input
                  type="number"
                  value={settings.defaultDurationMinutes}
                  onChange={(e) => setSettings({ ...settings, defaultDurationMinutes: Number(e.target.value) || 30 })}
                />
              </div>
              <div className="space-y-1.5">
                <Label>Buffer (min)</Label>
                <Input
                  type="number"
                  value={settings.bufferMinutes}
                  onChange={(e) => setSettings({ ...settings, bufferMinutes: Number(e.target.value) || 0 })}
                />
              </div>
              <div className="space-y-1.5">
                <Label>Bookable ahead (days)</Label>
                <Input
                  type="number"
                  value={settings.maxDaysAhead}
                  onChange={(e) => setSettings({ ...settings, maxDaysAhead: Number(e.target.value) || 30 })}
                />
              </div>
              <div className="space-y-1.5 sm:col-span-3">
                <Label>Minimum notice (min)</Label>
                <Input
                  type="number"
                  value={settings.minNoticeMinutes}
                  onChange={(e) => setSettings({ ...settings, minNoticeMinutes: Number(e.target.value) || 0 })}
                  className="sm:max-w-[200px]"
                />
              </div>
            </div>
          </SettingSection>

          <SettingSection title="Payments & WhatsApp" description="How booking payment and chat scheduling behave.">
            <SettingRow
              label="Payment"
              hint="Require upfront payment or pay at venue"
              control={
                <Select
                  value={settings.paymentRequirement ?? 'at_venue'}
                  onValueChange={(v) => setSettings({ ...settings, paymentRequirement: v as BookingSettings['paymentRequirement'] })}
                >
                  <SelectTrigger className="sm:w-56"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="at_venue">Pay at venue</SelectItem>
                    <SelectItem value="required">Pay upfront</SelectItem>
                    <SelectItem value="optional">Customer chooses</SelectItem>
                  </SelectContent>
                </Select>
              }
            />
            <SettingRow
              label="WhatsApp flow"
              hint="How the AI handles scheduling chats"
              control={
                <Select
                  value={settings.whatsappBookingMode ?? 'hybrid'}
                  onValueChange={(v) => setSettings({ ...settings, whatsappBookingMode: v as BookingSettings['whatsappBookingMode'] })}
                >
                  <SelectTrigger className="sm:w-56"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="hybrid">Chat + web link</SelectItem>
                    <SelectItem value="whatsapp_native">WhatsApp chat only</SelectItem>
                    <SelectItem value="web_link">Web link only</SelectItem>
                  </SelectContent>
                </Select>
              }
            />
          </SettingSection>

          <SettingSection title="Calendar sync" description="Pipe bookings into Google Calendar, Outlook, or Zapier.">
            <div className="flex flex-wrap items-center gap-2 rounded-xl bg-muted/40 p-3">
              <CalendarDays className="h-4 w-4 shrink-0 text-muted-foreground" />
              <code className="min-w-0 flex-1 truncate font-mono text-xs">{calendarUrl}</code>
              <Button variant="outline" size="sm" className="h-8 gap-1" onClick={() => void copy(calendarUrl)}>
                <Copy className="h-3.5 w-3.5" /> Copy feed
              </Button>
            </div>
            <div className="space-y-1.5">
              <Label>Webhook URL <span className="font-normal text-muted-foreground">(optional, for Zapier/Make)</span></Label>
              <Input
                value={settings.calendarWebhookUrl || ''}
                onChange={(e) => setSettings({ ...settings, calendarWebhookUrl: e.target.value })}
                placeholder="https://hooks.zapier.com/…"
              />
            </div>
            <SaveBar saving={saving} saved={saveOk} error={saveError} onSave={() => void saveSetup()} label="Save booking page" />
          </SettingSection>
        </div>
      )}
    </div>
  )
}

function BookingCard({
  booking: b,
  busy,
  onStatus,
}: {
  booking: BookingRow
  busy: boolean
  onStatus: (s: 'pending' | 'confirmed' | 'cancelled' | 'completed') => void
}) {
  const meta = statusOf(b)
  const actionable = b.status === 'pending' || b.status === 'confirmed'
  return (
    <div className="flex flex-col gap-3 rounded-2xl border border-border p-4 sm:flex-row sm:items-center">
      {/* Date block */}
      <div className="flex shrink-0 items-center gap-3 sm:w-20 sm:flex-col sm:gap-0 sm:rounded-xl sm:bg-muted/50 sm:py-2 sm:text-center">
        <span className="text-2xl font-bold leading-none text-foreground sm:mt-1">
          {new Date(b.startsAt).getDate()}
        </span>
        <span className="text-xs font-medium text-muted-foreground">
          {new Date(b.startsAt).toLocaleDateString([], { month: 'short' })}
        </span>
        <span className="text-[11px] text-muted-foreground sm:mb-1">
          {fmtTime(b.startsAt)}
        </span>
      </div>
      {/* Details */}
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2">
          <p className="truncate text-sm font-semibold text-foreground">{b.title || 'Appointment'}</p>
          <Badge variant={meta.badge} className="gap-1 text-[11px]">
            <span className={cn('h-1.5 w-1.5 rounded-full', meta.dot)} />
            {meta.label}
          </Badge>
        </div>
        <p className="mt-0.5 truncate text-[13px] text-muted-foreground">
          {b.customerName}{b.productName ? ` · ${b.productName}` : ''}
        </p>
        <p className="text-xs text-muted-foreground">
          {fmtDate(b.startsAt)} · {fmtTime(b.startsAt)} – {fmtTime(b.endsAt)}
        </p>
        {b.notes && <p className="mt-1 line-clamp-2 text-xs italic text-muted-foreground">“{b.notes}”</p>}
        <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
          {b.customerPhone && (
            <Button variant="ghost" size="sm" className="h-7 gap-1 px-2 text-xs" asChild>
              <a href={`tel:${b.customerPhone}`}>
                <Phone className="h-3 w-3" /> Call
              </a>
            </Button>
          )}
          {b.customerEmail && (
            <Button variant="ghost" size="sm" className="h-7 gap-1 px-2 text-xs" asChild>
              <a href={`mailto:${b.customerEmail}`}>
                <Mail className="h-3 w-3" /> Email
              </a>
            </Button>
          )}
          <Button variant="ghost" size="sm" className="h-7 gap-1 px-2 text-xs" asChild>
            <a href={b.googleCalendarUrl} target="_blank" rel="noreferrer">
              <ExternalLink className="h-3 w-3" /> Google Cal
            </a>
          </Button>
        </div>
      </div>
      {/* Actions */}
      <div className="flex shrink-0 items-center gap-1.5">
        {busy && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}
        {b.status === 'pending' && (
          <Button size="sm" onClick={() => onStatus('confirmed')} disabled={busy}>
            <Check className="mr-1 h-3.5 w-3.5" /> Confirm
          </Button>
        )}
        {b.status === 'confirmed' && (
          <Button size="sm" variant="outline" onClick={() => onStatus('completed')} disabled={busy}>
            <Check className="mr-1 h-3.5 w-3.5" /> Mark done
          </Button>
        )}
        {actionable && (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-8 w-8" disabled={busy}>
                <MoreHorizontal className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              {b.status === 'pending' && (
                <DropdownMenuItem onClick={() => onStatus('confirmed')}>Confirm booking</DropdownMenuItem>
              )}
              {b.status === 'confirmed' && (
                <DropdownMenuItem onClick={() => onStatus('completed')}>Mark completed</DropdownMenuItem>
              )}
              <DropdownMenuItem className="text-destructive focus:text-destructive" onClick={() => onStatus('cancelled')}>
                Cancel booking
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        )}
      </div>
    </div>
  )
}
