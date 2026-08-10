'use client'

import { useCallback, useEffect, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  createStorefrontCoupon,
  deleteStorefrontCoupon,
  listStorefrontCoupons,
  updateStorefrontCoupon,
  type StorefrontCoupon,
} from '@/lib/api-actions'
import { Loader2, Plus, Trash2 } from 'lucide-react'

export function StorefrontCouponsCard() {
  const [coupons, setCoupons] = useState<StorefrontCoupon[]>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [code, setCode] = useState('')
  const [type, setType] = useState<'percent' | 'fixed'>('percent')
  const [value, setValue] = useState('10')
  const [minOrder, setMinOrder] = useState('')
  const [endsAt, setEndsAt] = useState('')

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      setCoupons(await listStorefrontCoupons())
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load coupons')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const create = async () => {
    if (!code.trim()) {
      setError('Coupon code is required')
      return
    }
    setSaving(true)
    setError(null)
    const res = await createStorefrontCoupon({
      code: code.trim(),
      type,
      value: parseFloat(value) || 0,
      minOrder: minOrder.trim() === '' ? null : parseFloat(minOrder),
      endsAt: endsAt.trim() === '' ? null : endsAt,
      isActive: true,
    })
    setSaving(false)
    if (!res.success) {
      setError(res.message || 'Could not create coupon')
      return
    }
    setCode('')
    setValue('10')
    setMinOrder('')
    setEndsAt('')
    await load()
  }

  const toggle = async (coupon: StorefrontCoupon) => {
    await updateStorefrontCoupon(coupon.id, { isActive: !coupon.isActive })
    await load()
  }

  const remove = async (id: string) => {
    await deleteStorefrontCoupon(id)
    await load()
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Sales coupons</CardTitle>
        <CardDescription>
          Create Black Friday / promo codes for storefront checkout. WhatsApp AI can mention active codes.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {error && <p className="text-sm text-destructive">{error}</p>}
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <div>
            <Label>Code</Label>
            <Input value={code} onChange={(e) => setCode(e.target.value.toUpperCase())} placeholder="BF50" />
          </div>
          <div>
            <Label>Type</Label>
            <select
              className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
              value={type}
              onChange={(e) => setType(e.target.value as 'percent' | 'fixed')}
            >
              <option value="percent">Percent off</option>
              <option value="fixed">Fixed amount off</option>
            </select>
          </div>
          <div>
            <Label>Value</Label>
            <Input type="number" min={0} value={value} onChange={(e) => setValue(e.target.value)} />
          </div>
          <div>
            <Label>Min order (optional)</Label>
            <Input type="number" min={0} value={minOrder} onChange={(e) => setMinOrder(e.target.value)} />
          </div>
          <div>
            <Label>Ends at (optional)</Label>
            <Input type="datetime-local" value={endsAt} onChange={(e) => setEndsAt(e.target.value)} />
          </div>
        </div>
        <Button type="button" onClick={() => void create()} disabled={saving}>
          {saving ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Plus className="mr-2 h-4 w-4" />}
          Add coupon
        </Button>

        {loading ? (
          <div className="flex justify-center py-6">
            <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
          </div>
        ) : coupons.length === 0 ? (
          <p className="text-sm text-muted-foreground">No coupons yet. Add BF50 or WEEKEND20 for a flash sale.</p>
        ) : (
          <div className="space-y-2">
            {coupons.map((c) => (
              <div
                key={c.id}
                className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border px-3 py-2 text-sm"
              >
                <div>
                  <p className="font-semibold">
                    {c.code}{' '}
                    <span className="font-normal text-muted-foreground">
                      — {c.type === 'percent' ? `${c.value}% off` : `${c.value} off`}
                      {c.minOrder != null ? ` · min ${c.minOrder}` : ''}
                      {c.endsAt ? ` · ends ${new Date(c.endsAt).toLocaleString()}` : ''}
                    </span>
                  </p>
                  <p className="text-xs text-muted-foreground">
                    {c.isCurrentlyValid ? 'Valid now' : c.isActive ? 'Scheduled / expired / limit reached' : 'Inactive'} · redeemed{' '}
                    {c.redeemedCount ?? 0}
                    {c.maxRedemptions != null ? ` / ${c.maxRedemptions}` : ''}
                  </p>
                </div>
                <div className="flex gap-2">
                  <Button type="button" variant="outline" size="sm" onClick={() => void toggle(c)}>
                    {c.isActive ? 'Disable' : 'Enable'}
                  </Button>
                  <Button type="button" variant="ghost" size="sm" onClick={() => void remove(c.id)}>
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
