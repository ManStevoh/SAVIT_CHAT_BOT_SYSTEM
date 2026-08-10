'use client'

import { useCallback, useEffect, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { apiRequest } from '@/lib/api-client'
import { Copy, ExternalLink, Loader2, Plus, Trash2 } from 'lucide-react'
import { Link } from '@inertiajs/react'
import { StorefrontCouponsCard } from '@/components/dashboard/StorefrontCouponsCard'

type BioLink = { label: string; url: string }

type SettingsResponse = {
  storeSlug: string | null
  storefrontEnabled: boolean
  storefrontUrl: string | null
  linkInBioEnabled: boolean
  linkInBioHeadline: string | null
  linkInBioBio: string | null
  linkInBioLinks: BioLink[]
  linkInBioUrl: string | null
  ordersAcceptCod?: boolean
  ordersAcceptBankTransfer?: boolean
  bankTransferInstructions?: string
  paymentRecoveryEnabled?: boolean
  abandonedCartRecoveryEnabled?: boolean
  storefrontWhatsappOrderNotify?: boolean
  abandonedCartTemplateName?: string
  birthdayAutomationEnabled?: boolean
  birthdayCouponPercent?: number
  winbackAutomationEnabled?: boolean
  winbackDaysInactive?: number
  spamOrderProtectionEnabled?: boolean
  spamMaxOrdersPerHour?: number
  spamMaxOrdersPerDay?: number
  dineInEnabled?: boolean
  storefrontAnnouncementBar?: string
}

export default function DashboardStorefrontPage() {
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)

  const [storeSlug, setStoreSlug] = useState('')
  const [storefrontEnabled, setStorefrontEnabled] = useState(false)
  const [storefrontUrl, setStorefrontUrl] = useState<string | null>(null)
  const [linkInBioEnabled, setLinkInBioEnabled] = useState(false)
  const [linkInBioHeadline, setLinkInBioHeadline] = useState('')
  const [linkInBioBio, setLinkInBioBio] = useState('')
  const [linkInBioLinks, setLinkInBioLinks] = useState<BioLink[]>([])
  const [linkInBioUrl, setLinkInBioUrl] = useState<string | null>(null)

  const [ordersAcceptCod, setOrdersAcceptCod] = useState(false)
  const [ordersAcceptBankTransfer, setOrdersAcceptBankTransfer] = useState(false)
  const [bankTransferInstructions, setBankTransferInstructions] = useState('')
  const [paymentRecoveryEnabled, setPaymentRecoveryEnabled] = useState(true)
  const [abandonedCartRecoveryEnabled, setAbandonedCartRecoveryEnabled] = useState(false)
  const [storefrontWhatsappOrderNotify, setStorefrontWhatsappOrderNotify] = useState(true)
  const [abandonedCartTemplateName, setAbandonedCartTemplateName] = useState('')
  const [birthdayAutomationEnabled, setBirthdayAutomationEnabled] = useState(false)
  const [birthdayCouponPercent, setBirthdayCouponPercent] = useState('10')
  const [winbackAutomationEnabled, setWinbackAutomationEnabled] = useState(false)
  const [winbackDaysInactive, setWinbackDaysInactive] = useState('30')
  const [spamOrderProtectionEnabled, setSpamOrderProtectionEnabled] = useState(true)
  const [spamMaxOrdersPerHour, setSpamMaxOrdersPerHour] = useState('5')
  const [spamMaxOrdersPerDay, setSpamMaxOrdersPerDay] = useState('20')
  const [dineInEnabled, setDineInEnabled] = useState(false)
  const [announcementBar, setAnnouncementBar] = useState('')

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await apiRequest<SettingsResponse>('/api/company/settings')
      setStoreSlug(data.storeSlug || '')
      setStorefrontEnabled(!!data.storefrontEnabled)
      setStorefrontUrl(data.storefrontUrl)
      setLinkInBioEnabled(!!data.linkInBioEnabled)
      setLinkInBioHeadline(data.linkInBioHeadline || '')
      setLinkInBioBio(data.linkInBioBio || '')
      setLinkInBioLinks(data.linkInBioLinks?.length ? data.linkInBioLinks : [])
      setLinkInBioUrl(data.linkInBioUrl)
      setOrdersAcceptCod(!!data.ordersAcceptCod)
      setOrdersAcceptBankTransfer(!!data.ordersAcceptBankTransfer)
      setBankTransferInstructions(data.bankTransferInstructions || '')
      setPaymentRecoveryEnabled(data.paymentRecoveryEnabled !== false)
      setAbandonedCartRecoveryEnabled(!!data.abandonedCartRecoveryEnabled)
      setStorefrontWhatsappOrderNotify(data.storefrontWhatsappOrderNotify !== false)
      setAbandonedCartTemplateName(data.abandonedCartTemplateName || '')
      setBirthdayAutomationEnabled(!!data.birthdayAutomationEnabled)
      setBirthdayCouponPercent(String(data.birthdayCouponPercent ?? 10))
      setWinbackAutomationEnabled(!!data.winbackAutomationEnabled)
      setWinbackDaysInactive(String(data.winbackDaysInactive ?? 30))
      setSpamOrderProtectionEnabled(data.spamOrderProtectionEnabled !== false)
      setSpamMaxOrdersPerHour(String(data.spamMaxOrdersPerHour ?? 5))
      setSpamMaxOrdersPerDay(String(data.spamMaxOrdersPerDay ?? 20))
      setDineInEnabled(!!data.dineInEnabled)
      setAnnouncementBar(data.storefrontAnnouncementBar || '')
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load settings')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const save = async () => {
    setSaving(true)
    setError(null)
    setSaved(false)
    try {
      const data = await apiRequest<{ success: boolean }>('/api/company/settings', {
        method: 'PUT',
        body: {
          storeSlug: storeSlug || null,
          storefrontEnabled,
          linkInBioEnabled,
          linkInBioHeadline: linkInBioHeadline || null,
          linkInBioBio: linkInBioBio || null,
          linkInBioLinks,
          ordersAcceptCod,
          ordersAcceptBankTransfer,
          bankTransferInstructions: bankTransferInstructions || null,
          paymentRecoveryEnabled,
          abandonedCartRecoveryEnabled,
          storefrontWhatsappOrderNotify,
          abandonedCartTemplateName: abandonedCartTemplateName || null,
          birthdayAutomationEnabled,
          birthdayCouponPercent: parseInt(birthdayCouponPercent, 10) || 10,
          winbackAutomationEnabled,
          winbackDaysInactive: parseInt(winbackDaysInactive, 10) || 30,
          spamOrderProtectionEnabled,
          spamMaxOrdersPerHour: parseInt(spamMaxOrdersPerHour, 10) || 5,
          spamMaxOrdersPerDay: parseInt(spamMaxOrdersPerDay, 10) || 20,
          dineInEnabled,
          storefrontAnnouncementBar: announcementBar || null,
        },
      })
      if (data.success) {
        setSaved(true)
        await load()
      }
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
        <Loader2 className="mr-2 h-5 w-5 animate-spin" /> Loading storefront settings…
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Storefront &amp; commerce</h1>
          <p className="text-sm text-muted-foreground">
            Public shop, link-in-bio, local payments, and order automations.
          </p>
        </div>
        <Button onClick={() => void save()} disabled={saving}>
          {saving ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
          Save changes
        </Button>
      </div>

      {error && <p className="text-sm text-destructive">{error}</p>}
      {saved && <p className="text-sm text-emerald-600">Saved.</p>}

      <Card>
        <CardHeader>
          <CardTitle>Public storefront</CardTitle>
          <CardDescription>
            Also manage{' '}
            <Link href="/dashboard/delivery" className="underline underline-offset-2">
              delivery fees
            </Link>{' '}
            and{' '}
            <Link href="/dashboard/dine-in" className="underline underline-offset-2">
              dine-in tables
            </Link>
            .
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={storefrontEnabled}
              onChange={(e) => setStorefrontEnabled(e.target.checked)}
            />
            Storefront enabled
          </label>
          <div>
            <Label>Store slug</Label>
            <Input
              value={storeSlug}
              onChange={(e) => setStoreSlug(e.target.value)}
              placeholder="my-store"
            />
            <p className="mt-1 text-xs text-muted-foreground">
              Leave blank to auto-generate from your business name when you enable the storefront.
            </p>
          </div>
          {storefrontUrl && (
            <div className="flex flex-wrap items-center gap-2">
              <Input value={storefrontUrl} readOnly className="min-w-[16rem] flex-1" />
              <Button variant="outline" size="icon" onClick={() => void copy(storefrontUrl)}>
                <Copy className="h-4 w-4" />
              </Button>
              <Button variant="outline" size="icon" asChild>
                <a href={storefrontUrl} target="_blank" rel="noreferrer">
                  <ExternalLink className="h-4 w-4" />
                </a>
              </Button>
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Link-in-bio</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={linkInBioEnabled}
              onChange={(e) => setLinkInBioEnabled(e.target.checked)}
            />
            Link-in-bio page enabled
          </label>
          <div>
            <Label>Headline</Label>
            <Input value={linkInBioHeadline} onChange={(e) => setLinkInBioHeadline(e.target.value)} />
          </div>
          <div>
            <Label>Bio</Label>
            <Textarea value={linkInBioBio} onChange={(e) => setLinkInBioBio(e.target.value)} rows={3} />
          </div>
          {linkInBioUrl && (
            <div className="flex flex-wrap items-center gap-2">
              <Input value={linkInBioUrl} readOnly className="min-w-[16rem] flex-1" />
              <Button variant="outline" size="icon" onClick={() => void copy(linkInBioUrl)}>
                <Copy className="h-4 w-4" />
              </Button>
              <Button variant="outline" size="icon" asChild>
                <a href={linkInBioUrl} target="_blank" rel="noreferrer">
                  <ExternalLink className="h-4 w-4" />
                </a>
              </Button>
            </div>
          )}

          <div className="space-y-2">
            <Label>Links</Label>
            {linkInBioLinks.map((link, idx) => (
              <div key={idx} className="grid grid-cols-[1fr_2fr_auto] gap-2">
                <Input
                  value={link.label}
                  placeholder="Label"
                  onChange={(e) => {
                    const next = [...linkInBioLinks]
                    next[idx] = { ...link, label: e.target.value }
                    setLinkInBioLinks(next)
                  }}
                />
                <Input
                  value={link.url}
                  placeholder="https://..."
                  onChange={(e) => {
                    const next = [...linkInBioLinks]
                    next[idx] = { ...link, url: e.target.value }
                    setLinkInBioLinks(next)
                  }}
                />
                <Button
                  variant="outline"
                  size="icon"
                  onClick={() => setLinkInBioLinks(linkInBioLinks.filter((_, i) => i !== idx))}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
            ))}
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setLinkInBioLinks((prev) => [...prev, { label: '', url: '' }])}
            >
              <Plus className="mr-1 h-4 w-4" /> Add link
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Local payments</CardTitle>
          <CardDescription>Cash on delivery and bank transfer options for WhatsApp + store checkout.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={ordersAcceptCod}
              onChange={(e) => setOrdersAcceptCod(e.target.checked)}
            />
            Accept cash on delivery (COD)
          </label>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Automations</CardTitle>
          <CardDescription>
            WhatsApp recovery for carts and unpaid orders, plus retention and spam protection.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={abandonedCartRecoveryEnabled}
              onChange={(e) => setAbandonedCartRecoveryEnabled(e.target.checked)}
            />
            Abandoned cart recovery (WhatsApp cart link)
          </label>
          <div>
            <Label>Announcement bar (sales banner)</Label>
            <Input
              value={announcementBar}
              onChange={(e) => setAnnouncementBar(e.target.value)}
              placeholder="Black Friday — 30% off with code BF30"
            />
            <p className="mt-1 text-xs text-muted-foreground">
              Shown at the top of your public storefront. Leave empty to hide.
            </p>
          </div>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={storefrontWhatsappOrderNotify}
              onChange={(e) => setStorefrontWhatsappOrderNotify(e.target.checked)}
            />
            WhatsApp order confirmation after storefront checkout
          </label>
          <div>
            <Label>Abandoned cart template name (optional)</Label>
            <Input
              value={abandonedCartTemplateName}
              onChange={(e) => setAbandonedCartTemplateName(e.target.value)}
              placeholder="Meta template name when outside 24h window"
            />
          </div>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={paymentRecoveryEnabled}
              onChange={(e) => setPaymentRecoveryEnabled(e.target.checked)}
            />
            Unpaid order payment recovery (WhatsApp nudges)
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={birthdayAutomationEnabled}
              onChange={(e) => setBirthdayAutomationEnabled(e.target.checked)}
            />
            Birthday wishes
          </label>
          <div>
            <Label>Birthday coupon %</Label>
            <Input
              type="number"
              min={0}
              max={100}
              value={birthdayCouponPercent}
              onChange={(e) => setBirthdayCouponPercent(e.target.value)}
            />
          </div>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={winbackAutomationEnabled}
              onChange={(e) => setWinbackAutomationEnabled(e.target.checked)}
            />
            Win-back messages for inactive customers
          </label>
          <div>
            <Label>Days inactive before win-back</Label>
            <Input
              type="number"
              min={7}
              value={winbackDaysInactive}
              onChange={(e) => setWinbackDaysInactive(e.target.value)}
            />
          </div>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={spamOrderProtectionEnabled}
              onChange={(e) => setSpamOrderProtectionEnabled(e.target.checked)}
            />
            Spam order protection
          </label>
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <Label>Max orders / hour</Label>
              <Input
                type="number"
                min={1}
                value={spamMaxOrdersPerHour}
                onChange={(e) => setSpamMaxOrdersPerHour(e.target.value)}
              />
            </div>
            <div>
              <Label>Max orders / day</Label>
              <Input
                type="number"
                min={1}
                value={spamMaxOrdersPerDay}
                onChange={(e) => setSpamMaxOrdersPerDay(e.target.value)}
              />
            </div>
          </div>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={dineInEnabled}
              onChange={(e) => setDineInEnabled(e.target.checked)}
            />
            Enable dine-in ordering
          </label>
        </CardContent>
      </Card>

      <StorefrontCouponsCard />
    </div>
  )
}
