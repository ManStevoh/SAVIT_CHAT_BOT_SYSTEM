'use client'

import { useCallback, useEffect, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { apiRequest } from '@/lib/api-client'
import { Calendar, Copy, ExternalLink, Loader2, RefreshCw } from 'lucide-react'

type AvailabilityRow = { weekday: number; startTime: string; endTime: string }

type BookingSettingsResponse = {
  settings: {
    timezone: string
    defaultDurationMinutes: number
    bufferMinutes: number
    minNoticeMinutes: number
    maxDaysAhead: number
    publicSlug: string
    calendarWebhookUrl: string | null
    isEnabled: boolean
  }
  availability: AvailabilityRow[]
  publicBookingUrl: string
  calendarFeedUrl: string
  maxBookingsPerMonth: number | null
  bookingsThisMonth: number
  success?: boolean
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
  googleCalendarUrl: string
  icsUrl: string
}

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const defaultAvailability: AvailabilityRow[] = [1, 2, 3, 4, 5].map((weekday) => ({
  weekday,
  startTime: '09:00',
  endTime: '17:00',
}))

export default function BookingsPage() {
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [blocked, setBlocked] = useState<string | null>(null)
  const [settings, setSettings] = useState<BookingSettingsResponse['settings'] | null>(null)
  const [availability, setAvailability] = useState<AvailabilityRow[]>(defaultAvailability)
  const [publicUrl, setPublicUrl] = useState('')
  const [calendarUrl, setCalendarUrl] = useState('')
  const [usage, setUsage] = useState({ used: 0, max: null as number | null })
  const [bookings, setBookings] = useState<BookingRow[]>([])
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await apiRequest<BookingSettingsResponse>('/api/company/bookings/settings')
      if ((data as { code?: string }).code === 'bookings_required') {
        setBlocked((data as { message?: string }).message || 'Bookings are not on your plan.')
        setSettings(null)
        return
      }
      setBlocked(null)
      setSettings(data.settings)
      setAvailability(data.availability?.length ? data.availability : defaultAvailability)
      setPublicUrl(data.publicBookingUrl)
      setCalendarUrl(data.calendarFeedUrl)
      setUsage({ used: data.bookingsThisMonth, max: data.maxBookingsPerMonth })

      const list = await apiRequest<{ bookings: BookingRow[] }>('/api/company/bookings?upcoming=1')
      setBookings(list.bookings || [])
    } catch (e) {
      const err = e as Error & { code?: string }
      if (err.code === 'bookings_required') {
        setBlocked(err.message || 'Bookings are not on your plan.')
      } else {
        const message = err.message || 'Failed to load bookings'
        setError(message)
      }
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const save = async () => {
    if (!settings) return
    setSaving(true)
    setError(null)
    try {
      const data = await apiRequest<BookingSettingsResponse>('/api/company/bookings/settings', {
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
          availability,
        },
      })
      setSettings(data.settings)
      setAvailability(data.availability?.length ? data.availability : availability)
      setPublicUrl(data.publicBookingUrl)
      setCalendarUrl(data.calendarFeedUrl)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to save')
    } finally {
      setSaving(false)
    }
  }

  const copy = async (value: string) => {
    try {
      await navigator.clipboard.writeText(value)
    } catch {
      /* ignore */
    }
  }

  if (loading) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-muted-foreground">
        <Loader2 className="mr-2 h-5 w-5 animate-spin" /> Loading bookings…
      </div>
    )
  }

  if (blocked) {
    return (
      <Card className="mx-auto max-w-xl">
        <CardHeader>
          <CardTitle>Bookings</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <p className="text-sm text-muted-foreground">{blocked}</p>
          <Button asChild>
            <a href="/dashboard/subscription">Upgrade plan</a>
          </Button>
        </CardContent>
      </Card>
    )
  }

  if (!settings) return null

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Bookings</h1>
          <p className="text-sm text-muted-foreground">
            Native scheduling with calendar feed. Customers book from your public page.
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => void load()}>
            <RefreshCw className="mr-2 h-4 w-4" /> Refresh
          </Button>
          <Button onClick={() => void save()} disabled={saving}>
            {saving ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
            Save settings
          </Button>
        </div>
      </div>

      {error && <p className="text-sm text-destructive">{error}</p>}

      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">This month</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-semibold">
              {usage.used}
              {usage.max != null ? ` / ${usage.max}` : ' · unlimited'}
            </p>
          </CardContent>
        </Card>
        <Card className="md:col-span-2">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Share & calendar</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <div className="flex flex-wrap items-center gap-2">
              <Input value={publicUrl} readOnly className="min-w-[16rem] flex-1" />
              <Button variant="outline" size="icon" onClick={() => void copy(publicUrl)}>
                <Copy className="h-4 w-4" />
              </Button>
              <Button variant="outline" size="icon" asChild>
                <a href={publicUrl} target="_blank" rel="noreferrer">
                  <ExternalLink className="h-4 w-4" />
                </a>
              </Button>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <Input value={calendarUrl} readOnly className="min-w-[16rem] flex-1" />
              <Button variant="outline" size="icon" onClick={() => void copy(calendarUrl)}>
                <Copy className="h-4 w-4" />
              </Button>
            </div>
            <p className="text-xs text-muted-foreground">
              Subscribe the calendar feed in Google Calendar / Outlook (Add by URL), or use webhook for Zapier/Make.
            </p>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Availability</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={settings.isEnabled}
                onChange={(e) => setSettings({ ...settings, isEnabled: e.target.checked })}
              />
              Booking page enabled
            </label>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label>Timezone</Label>
                <Input
                  value={settings.timezone}
                  onChange={(e) => setSettings({ ...settings, timezone: e.target.value })}
                />
              </div>
              <div>
                <Label>Public slug</Label>
                <Input
                  value={settings.publicSlug}
                  onChange={(e) => setSettings({ ...settings, publicSlug: e.target.value })}
                />
              </div>
              <div>
                <Label>Default duration (min)</Label>
                <Input
                  type="number"
                  value={settings.defaultDurationMinutes}
                  onChange={(e) =>
                    setSettings({ ...settings, defaultDurationMinutes: Number(e.target.value) || 30 })
                  }
                />
              </div>
              <div>
                <Label>Buffer (min)</Label>
                <Input
                  type="number"
                  value={settings.bufferMinutes}
                  onChange={(e) =>
                    setSettings({ ...settings, bufferMinutes: Number(e.target.value) || 0 })
                  }
                />
              </div>
              <div>
                <Label>Min notice (min)</Label>
                <Input
                  type="number"
                  value={settings.minNoticeMinutes}
                  onChange={(e) =>
                    setSettings({ ...settings, minNoticeMinutes: Number(e.target.value) || 0 })
                  }
                />
              </div>
              <div>
                <Label>Days ahead</Label>
                <Input
                  type="number"
                  value={settings.maxDaysAhead}
                  onChange={(e) =>
                    setSettings({ ...settings, maxDaysAhead: Number(e.target.value) || 30 })
                  }
                />
              </div>
            </div>
            <div>
              <Label>Calendar webhook (optional)</Label>
              <Input
                value={settings.calendarWebhookUrl || ''}
                onChange={(e) => setSettings({ ...settings, calendarWebhookUrl: e.target.value })}
                placeholder="https://hooks.zapier.com/..."
              />
            </div>
            <div className="space-y-2">
              <Label>Weekly hours</Label>
              {availability.map((row, idx) => (
                <div key={`${row.weekday}-${idx}`} className="grid grid-cols-3 gap-2">
                  <select
                    className="rounded-md border border-input bg-background px-2 text-sm"
                    value={row.weekday}
                    onChange={(e) => {
                      const next = [...availability]
                      next[idx] = { ...row, weekday: Number(e.target.value) }
                      setAvailability(next)
                    }}
                  >
                    {WEEKDAYS.map((label, day) => (
                      <option key={label} value={day}>
                        {label}
                      </option>
                    ))}
                  </select>
                  <Input
                    type="time"
                    value={row.startTime}
                    onChange={(e) => {
                      const next = [...availability]
                      next[idx] = { ...row, startTime: e.target.value }
                      setAvailability(next)
                    }}
                  />
                  <Input
                    type="time"
                    value={row.endTime}
                    onChange={(e) => {
                      const next = [...availability]
                      next[idx] = { ...row, endTime: e.target.value }
                      setAvailability(next)
                    }}
                  />
                </div>
              ))}
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() =>
                  setAvailability((prev) => [...prev, { weekday: 1, startTime: '09:00', endTime: '17:00' }])
                }
              >
                Add window
              </Button>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Calendar className="h-4 w-4" /> Upcoming
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {bookings.length === 0 && (
              <p className="text-sm text-muted-foreground">No upcoming bookings yet.</p>
            )}
            {bookings.map((b) => (
              <div key={b.id} className="rounded-lg border border-border p-3">
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <p className="font-medium">{b.title || 'Meeting'}</p>
                    <p className="text-sm text-muted-foreground">
                      {b.customerName}
                      {b.productName ? ` · ${b.productName}` : ''}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {new Date(b.startsAt).toLocaleString()} → {new Date(b.endsAt).toLocaleTimeString()}
                    </p>
                  </div>
                  <Badge variant="secondary">{b.status}</Badge>
                </div>
                <div className="mt-2 flex gap-2">
                  <Button size="sm" variant="outline" asChild>
                    <a href={b.googleCalendarUrl} target="_blank" rel="noreferrer">
                      Google Calendar
                    </a>
                  </Button>
                  <Button size="sm" variant="outline" asChild>
                    <a href={b.icsUrl}>ICS</a>
                  </Button>
                </div>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
