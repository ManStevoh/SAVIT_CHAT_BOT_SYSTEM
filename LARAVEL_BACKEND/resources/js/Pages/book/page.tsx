'use client'

import { FormEvent, useMemo, useState } from 'react'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

type Slot = { start: string; end: string }
type BookableProduct = {
  id: string
  name: string
  price: number
  durationMinutes: number
  description?: string | null
}

type Props = {
  company: { name: string }
  slug: string
  timezone: string
  slots: Slot[]
  products: BookableProduct[]
  selectedProductId?: string | null
  orderId?: number | null
  prefill?: { name?: string | null; phone?: string | null }
  errors?: Record<string, string>
}

export default function PublicBookPage({
  company,
  slug,
  timezone,
  slots,
  products,
  selectedProductId,
  orderId,
  prefill,
  errors = {},
}: Props) {
  const [productId, setProductId] = useState(selectedProductId || products[0]?.id || '')
  const [startsAt, setStartsAt] = useState(slots[0]?.start || '')
  const [name, setName] = useState(prefill?.name || '')
  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState(prefill?.phone || '')
  const [notes, setNotes] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const byDay = useMemo(() => {
    const map = new Map<string, Slot[]>()
    for (const slot of slots) {
      const day = new Date(slot.start).toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        timeZone: timezone,
      })
      const list = map.get(day) || []
      list.push(slot)
      map.set(day, list)
    }
    return Array.from(map.entries())
  }, [slots, timezone])

  const onSubmit = (e: FormEvent) => {
    e.preventDefault()
    if (!startsAt || !name.trim()) return
    setSubmitting(true)
    router.post(
      `/book/${slug}`,
      {
        startsAt,
        productId: productId || null,
        orderId: orderId || null,
        customerName: name,
        customerEmail: email || null,
        customerPhone: phone || null,
        notes: notes || null,
      },
      {
        onFinish: () => setSubmitting(false),
      }
    )
  }

  return (
    <div className="min-h-screen bg-gradient-to-b from-slate-50 to-white px-4 py-10 text-slate-900">
      <div className="mx-auto max-w-3xl space-y-8">
        <header className="space-y-2">
          <p className="text-sm uppercase tracking-[0.2em] text-slate-500">Book a meeting</p>
          <h1 className="text-3xl font-semibold tracking-tight">{company.name}</h1>
          <p className="text-slate-600">Times shown in {timezone}.</p>
        </header>

        <form onSubmit={onSubmit} className="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          {products.length > 0 && (
            <div className="space-y-2">
              <Label>Service</Label>
              <select
                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={productId}
                onChange={(e) => setProductId(e.target.value)}
              >
                {products.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name} · {p.durationMinutes} min
                    {p.price > 0 ? ` · ${p.price}` : ''}
                  </option>
                ))}
              </select>
            </div>
          )}

          <div className="space-y-3">
            <Label>Pick a time</Label>
            {byDay.length === 0 && (
              <p className="text-sm text-slate-500">No open slots right now. Check back later.</p>
            )}
            {byDay.map(([day, daySlots]) => (
              <div key={day} className="space-y-2">
                <p className="text-sm font-medium text-slate-700">{day}</p>
                <div className="flex flex-wrap gap-2">
                  {daySlots.map((slot) => {
                    const label = new Date(slot.start).toLocaleTimeString(undefined, {
                      hour: 'numeric',
                      minute: '2-digit',
                      timeZone: timezone,
                    })
                    const active = startsAt === slot.start
                    return (
                      <button
                        key={slot.start}
                        type="button"
                        onClick={() => setStartsAt(slot.start)}
                        className={`rounded-full border px-3 py-1.5 text-sm ${
                          active
                            ? 'border-slate-900 bg-slate-900 text-white'
                            : 'border-slate-200 bg-white hover:border-slate-400'
                        }`}
                      >
                        {label}
                      </button>
                    )
                  })}
                </div>
              </div>
            ))}
            {errors.startsAt && <p className="text-sm text-red-600">{errors.startsAt}</p>}
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <Label>Your name</Label>
              <Input value={name} onChange={(e) => setName(e.target.value)} required />
            </div>
            <div>
              <Label>Phone</Label>
              <Input value={phone} onChange={(e) => setPhone(e.target.value)} />
            </div>
            <div className="sm:col-span-2">
              <Label>Email</Label>
              <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
            </div>
            <div className="sm:col-span-2">
              <Label>Notes</Label>
              <Textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={3} />
            </div>
          </div>

          <Button type="submit" disabled={submitting || !startsAt} className="w-full sm:w-auto">
            {submitting ? 'Booking…' : 'Confirm booking'}
          </Button>
        </form>
      </div>
    </div>
  )
}
