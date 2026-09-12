'use client'

import { useCallback, useEffect, useState } from 'react'
import Link from 'next/link'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { apiRequest } from '@/lib/api-client'
import {
  Bot,
  Check,
  Copy,
  CreditCard,
  ExternalLink,
  Eye,
  Gift,
  Globe,
  Link2,
  Loader2,
  MessageSquare,
  Palette,
  Plus,
  Search,
  Share2,
  ShieldCheck,
  ShoppingCart,
  SlidersHorizontal,
  Sparkles,
  Store,
  Trash2,
  Zap,
} from 'lucide-react'
import { StorefrontCouponsCard } from '@/components/dashboard/StorefrontCouponsCard'
import { BrandCustomizationCard } from '@/components/dashboard/BrandCustomizationCard'
import type { BrandTheme } from '@/lib/theme-utils'

type BioLink = { label: string; url: string }

type SettingsResponse = {
  companyName?: string
  logo?: string | null
  storeSlug: string | null
  storefrontEnabled: boolean
  storefrontUrl: string | null
  linkInBioEnabled: boolean
  linkInBioHeadline: string | null
  linkInBioBio: string | null
  linkInBioLinks: BioLink[]
  linkInBioUrl: string | null
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
  storefrontAnnouncementBar?: string
  storefrontSeoTitle?: string
  storefrontSeoDescription?: string
  storefrontOgImage?: string
  storefrontGoogleSiteVerification?: string
  storefrontBusinessType?: string
  storefrontTheme?: BrandTheme
}

function AutomationRow({
  icon: Icon,
  iconClass,
  title,
  desc,
  checked,
  onCheckedChange,
  children,
}: {
  icon: typeof Bot
  iconClass?: string
  title: string
  desc: string
  checked: boolean
  onCheckedChange: (v: boolean) => void
  children?: React.ReactNode
}) {
  return (
    <div className="flex items-start justify-between gap-4 rounded-xl border p-4">
      <div className="min-w-0 flex-1 space-y-1">
        <div className="flex items-center gap-2">
          <Icon className={`h-4 w-4 ${iconClass ?? 'text-primary'}`} />
          <span className="text-sm font-semibold">{title}</span>
        </div>
        <p className="text-xs text-muted-foreground">{desc}</p>
        {checked && children}
      </div>
      <Switch checked={checked} onCheckedChange={onCheckedChange} className="shrink-0" />
    </div>
  )
}

export default function DashboardStorefrontPage() {
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)
  const [copiedUrl, setCopiedUrl] = useState(false)
  const [activeTab, setActiveTab] = useState('design')

  const [companyName, setCompanyName] = useState('My Brand')
  const [companyLogo, setCompanyLogo] = useState<string | null>(null)
  const [storefrontTheme, setStorefrontTheme] = useState<BrandTheme | null>(null)
  const [storeSlug, setStoreSlug] = useState('')
  const [storefrontEnabled, setStorefrontEnabled] = useState(false)
  const [storefrontUrl, setStorefrontUrl] = useState<string | null>(null)
  const [linkInBioEnabled, setLinkInBioEnabled] = useState(false)
  const [linkInBioHeadline, setLinkInBioHeadline] = useState('')
  const [linkInBioBio, setLinkInBioBio] = useState('')
  const [linkInBioLinks, setLinkInBioLinks] = useState<BioLink[]>([])
  const [linkInBioUrl, setLinkInBioUrl] = useState<string | null>(null)

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
  const [announcementBar, setAnnouncementBar] = useState('')
  const [storefrontSeoTitle, setStorefrontSeoTitle] = useState('')
  const [storefrontSeoDescription, setStorefrontSeoDescription] = useState('')
  const [storefrontOgImage, setStorefrontOgImage] = useState('')
  const [storefrontGoogleSiteVerification, setStorefrontGoogleSiteVerification] = useState('')
  const [storefrontBusinessType, setStorefrontBusinessType] = useState('OnlineStore')

  const [initialSnapshot, setInitialSnapshot] = useState<string>('')

  const snapshotOf = (s: {
    storeSlug: string
    storefrontEnabled: boolean
    linkInBioEnabled: boolean
    linkInBioHeadline: string
    linkInBioBio: string
    linkInBioLinks: BioLink[]
    paymentRecoveryEnabled: boolean
    abandonedCartRecoveryEnabled: boolean
    storefrontWhatsappOrderNotify: boolean
    abandonedCartTemplateName: string
    birthdayAutomationEnabled: boolean
    birthdayCouponPercent: string
    winbackAutomationEnabled: boolean
    winbackDaysInactive: string
    spamOrderProtectionEnabled: boolean
    spamMaxOrdersPerHour: string
    spamMaxOrdersPerDay: string
    storefrontSeoTitle: string
    storefrontSeoDescription: string
    storefrontOgImage: string
    storefrontGoogleSiteVerification: string
    storefrontBusinessType: string
  }) => JSON.stringify(s)

  const currentSnapshot = snapshotOf({
    storeSlug,
    storefrontEnabled,
    linkInBioEnabled,
    linkInBioHeadline,
    linkInBioBio,
    linkInBioLinks,
    paymentRecoveryEnabled,
    abandonedCartRecoveryEnabled,
    storefrontWhatsappOrderNotify,
    abandonedCartTemplateName,
    birthdayAutomationEnabled,
    birthdayCouponPercent,
    winbackAutomationEnabled,
    winbackDaysInactive,
    spamOrderProtectionEnabled,
    spamMaxOrdersPerHour,
    spamMaxOrdersPerDay,
    storefrontSeoTitle,
    storefrontSeoDescription,
    storefrontOgImage,
    storefrontGoogleSiteVerification,
    storefrontBusinessType,
  })

  const isDirty = initialSnapshot !== '' && initialSnapshot !== currentSnapshot

  const applyData = (data: SettingsResponse) => {
    setCompanyName(data.companyName || 'My Brand')
    setCompanyLogo(data.logo || null)
    setStorefrontTheme(data.storefrontTheme || null)
    setStoreSlug(data.storeSlug || '')
    setStorefrontEnabled(!!data.storefrontEnabled)
    setStorefrontUrl(data.storefrontUrl)
    setLinkInBioEnabled(!!data.linkInBioEnabled)
    setLinkInBioHeadline(data.linkInBioHeadline || '')
    setLinkInBioBio(data.linkInBioBio || '')
    setLinkInBioLinks(data.linkInBioLinks?.length ? data.linkInBioLinks : [])
    setLinkInBioUrl(data.linkInBioUrl)
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
    setAnnouncementBar(data.storefrontAnnouncementBar || '')
    setStorefrontSeoTitle(data.storefrontSeoTitle || '')
    setStorefrontSeoDescription(data.storefrontSeoDescription || '')
    setStorefrontOgImage(data.storefrontOgImage || '')
    setStorefrontGoogleSiteVerification(data.storefrontGoogleSiteVerification || '')
    setStorefrontBusinessType(data.storefrontBusinessType || 'OnlineStore')
  }

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await apiRequest<SettingsResponse>('/api/company/settings')
      applyData(data)
      setInitialSnapshot(snapshotOf({
        storeSlug: data.storeSlug || '',
        storefrontEnabled: !!data.storefrontEnabled,
        linkInBioEnabled: !!data.linkInBioEnabled,
        linkInBioHeadline: data.linkInBioHeadline || '',
        linkInBioBio: data.linkInBioBio || '',
        linkInBioLinks: data.linkInBioLinks?.length ? data.linkInBioLinks : [],
        paymentRecoveryEnabled: data.paymentRecoveryEnabled !== false,
        abandonedCartRecoveryEnabled: !!data.abandonedCartRecoveryEnabled,
        storefrontWhatsappOrderNotify: data.storefrontWhatsappOrderNotify !== false,
        abandonedCartTemplateName: data.abandonedCartTemplateName || '',
        birthdayAutomationEnabled: !!data.birthdayAutomationEnabled,
        birthdayCouponPercent: String(data.birthdayCouponPercent ?? 10),
        winbackAutomationEnabled: !!data.winbackAutomationEnabled,
        winbackDaysInactive: String(data.winbackDaysInactive ?? 30),
        spamOrderProtectionEnabled: data.spamOrderProtectionEnabled !== false,
        spamMaxOrdersPerHour: String(data.spamMaxOrdersPerHour ?? 5),
        spamMaxOrdersPerDay: String(data.spamMaxOrdersPerDay ?? 20),
        storefrontSeoTitle: data.storefrontSeoTitle || '',
        storefrontSeoDescription: data.storefrontSeoDescription || '',
        storefrontOgImage: data.storefrontOgImage || '',
        storefrontGoogleSiteVerification: data.storefrontGoogleSiteVerification || '',
        storefrontBusinessType: data.storefrontBusinessType || 'OnlineStore',
      }))
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load settings')
    } finally {
      setLoading(false)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const discardChanges = () => {
    if (!initialSnapshot) {
      void load()
      return
    }
    try {
      const snap = JSON.parse(initialSnapshot)
      setStoreSlug(snap.storeSlug)
      setStorefrontEnabled(snap.storefrontEnabled)
      setLinkInBioEnabled(snap.linkInBioEnabled)
      setLinkInBioHeadline(snap.linkInBioHeadline)
      setLinkInBioBio(snap.linkInBioBio)
      setLinkInBioLinks(snap.linkInBioLinks)
      setPaymentRecoveryEnabled(snap.paymentRecoveryEnabled)
      setAbandonedCartRecoveryEnabled(snap.abandonedCartRecoveryEnabled)
      setStorefrontWhatsappOrderNotify(snap.storefrontWhatsappOrderNotify)
      setAbandonedCartTemplateName(snap.abandonedCartTemplateName)
      setBirthdayAutomationEnabled(snap.birthdayAutomationEnabled)
      setBirthdayCouponPercent(snap.birthdayCouponPercent)
      setWinbackAutomationEnabled(snap.winbackAutomationEnabled)
      setWinbackDaysInactive(snap.winbackDaysInactive)
      setSpamOrderProtectionEnabled(snap.spamOrderProtectionEnabled)
      setSpamMaxOrdersPerHour(snap.spamMaxOrdersPerHour)
      setSpamMaxOrdersPerDay(snap.spamMaxOrdersPerDay)
      setStorefrontSeoTitle(snap.storefrontSeoTitle)
      setStorefrontSeoDescription(snap.storefrontSeoDescription)
      setStorefrontOgImage(snap.storefrontOgImage)
      setStorefrontGoogleSiteVerification(snap.storefrontGoogleSiteVerification)
      setStorefrontBusinessType(snap.storefrontBusinessType)
    } catch {
      void load()
    }
  }

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
          storefrontAnnouncementBar: announcementBar || null,
          storefrontSeoTitle: storefrontSeoTitle || null,
          storefrontSeoDescription: storefrontSeoDescription || null,
          storefrontOgImage: storefrontOgImage || null,
          storefrontGoogleSiteVerification: storefrontGoogleSiteVerification || null,
          storefrontBusinessType: storefrontBusinessType || 'OnlineStore',
        },
      })
      if (data.success) {
        setSaved(true)
        setTimeout(() => setSaved(false), 3500)
        await load()
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to save settings')
    } finally {
      setSaving(false)
    }
  }

  const copy = async (value: string) => {
    try {
      await navigator.clipboard.writeText(value)
      setCopiedUrl(true)
      setTimeout(() => setCopiedUrl(false), 2000)
    } catch {
      /* ignore */
    }
  }

  if (loading) {
    return (
      <div className="flex min-h-[50vh] flex-col items-center justify-center gap-3 text-muted-foreground">
        <Loader2 className="h-8 w-8 animate-spin text-primary" />
        <p className="text-sm font-medium">Loading storefront…</p>
      </div>
    )
  }

  return (
    <div className="w-full space-y-6 pb-28">
      {/* Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2.5">
            <h1 className="text-2xl font-bold tracking-tight text-foreground">Storefront</h1>
            <Badge variant={storefrontEnabled ? 'default' : 'secondary'}>
              {storefrontEnabled ? 'Live' : 'Paused'}
            </Badge>
          </div>
          <p className="mt-1 text-sm text-muted-foreground">
            How your shop looks, where it lives, and what happens after checkout.
          </p>
        </div>
        <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
          {storefrontUrl && storefrontEnabled && (
            <Button variant="outline" size="sm" asChild className="gap-1.5">
              <a href={storefrontUrl} target="_blank" rel="noreferrer">
                <ExternalLink className="h-3.5 w-3.5" /> Visit store
              </a>
            </Button>
          )}
          <Button onClick={() => void save()} disabled={saving} size="sm" className="gap-1.5">
            {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
            Save changes
          </Button>
        </div>
      </div>

      {error && (
        <div className="rounded-xl border border-destructive/20 bg-destructive/10 p-3.5 text-sm font-medium text-destructive">
          {error}
        </div>
      )}
      {saved && (
        <div className="flex items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3.5 text-sm font-medium text-emerald-600 dark:text-emerald-400">
          <Check className="h-4 w-4" /> Storefront saved.
        </div>
      )}

      {/* Status strip */}
      <div className="flex flex-col gap-3 rounded-2xl border bg-muted/40 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-background text-primary">
            <Store className="h-4 w-4" />
          </span>
          <div>
            <div className="flex items-center gap-2.5">
              <p className="text-sm font-semibold text-foreground">
                {storefrontEnabled ? `${companyName} is open` : `${companyName} is paused`}
              </p>
              <Switch checked={storefrontEnabled} onCheckedChange={setStorefrontEnabled} />
            </div>
            <p className="text-xs text-muted-foreground">
              {storefrontEnabled ? 'Customers can browse and order.' : 'Turn on to start receiving orders.'}
            </p>
          </div>
        </div>
        {storefrontUrl && (
          <div className="flex items-center gap-2">
            <code className="max-w-[16rem] truncate rounded-lg border bg-background px-2.5 py-1.5 font-mono text-xs">
              {storefrontUrl}
            </code>
            <Button variant="outline" size="sm" onClick={() => void copy(storefrontUrl)} className="h-8 gap-1">
              {copiedUrl ? <Check className="h-3.5 w-3.5 text-emerald-600" /> : <Copy className="h-3.5 w-3.5" />}
              {copiedUrl ? 'Copied' : 'Copy'}
            </Button>
          </div>
        )}
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
        <TabsList className="inline-flex h-auto max-w-full gap-1 overflow-x-auto rounded-2xl border border-border bg-muted/50 p-1.5">
          <TabsTrigger value="design" className="gap-2 rounded-xl px-4 py-2 text-[13px] font-medium">
            <Palette className="h-4 w-4" />
            Design
          </TabsTrigger>
          <TabsTrigger value="link" className="gap-2 rounded-xl px-4 py-2 text-[13px] font-medium">
            <Link2 className="h-4 w-4" />
            Store link & settings
          </TabsTrigger>
          <TabsTrigger value="advanced" className="gap-2 rounded-xl px-4 py-2 text-[13px] font-medium">
            <SlidersHorizontal className="h-4 w-4" />
            Advanced settings
          </TabsTrigger>
        </TabsList>

        {/* TAB 1: how it looks */}
        <TabsContent value="design" className="space-y-4 outline-none">
          <BrandCustomizationCard
            initialLogo={companyLogo}
            initialTheme={storefrontTheme}
            initialAnnouncementBar={announcementBar}
            initialFooterText={storefrontTheme?.footer_text}
            businessName={companyName}
            storeSlug={storeSlug}
            onSaved={load}
          />

          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Share2 className="h-5 w-5 text-primary" />
                  <div>
                    <CardTitle className="text-base">Link-in-bio page</CardTitle>
                    <CardDescription>One link for Instagram, TikTok and WhatsApp</CardDescription>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-xs font-medium text-muted-foreground">Enabled</span>
                  <Switch checked={linkInBioEnabled} onCheckedChange={setLinkInBioEnabled} />
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              {linkInBioUrl && (
                <div className="flex flex-wrap items-center gap-2 rounded-xl border bg-muted/20 p-3">
                  <Input value={linkInBioUrl} readOnly className="h-8 max-w-md flex-1 font-mono text-xs" />
                  <Button variant="outline" size="sm" onClick={() => void copy(linkInBioUrl)} className="h-8 gap-1">
                    <Copy className="h-3.5 w-3.5" /> Copy
                  </Button>
                  <Button variant="outline" size="sm" asChild className="h-8 gap-1">
                    <a href={linkInBioUrl} target="_blank" rel="noreferrer">
                      <ExternalLink className="h-3.5 w-3.5" /> Open
                    </a>
                  </Button>
                </div>
              )}
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="bioHeadline">Headline</Label>
                  <Input
                    id="bioHeadline"
                    value={linkInBioHeadline}
                    onChange={(e) => setLinkInBioHeadline(e.target.value)}
                    placeholder={companyName}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="bioText">Tagline</Label>
                  <Textarea
                    id="bioText"
                    value={linkInBioBio}
                    onChange={(e) => setLinkInBioBio(e.target.value)}
                    rows={2}
                    placeholder="Tap below to shop, chat, or view offers."
                  />
                </div>
              </div>
              <div className="space-y-3 border-t pt-4">
                <div className="flex items-center justify-between">
                  <Label className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Links</Label>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setLinkInBioLinks((prev) => [...prev, { label: '', url: '' }])}
                    className="h-8 gap-1 text-xs"
                  >
                    <Plus className="h-3.5 w-3.5" /> Add link
                  </Button>
                </div>
                {linkInBioLinks.length === 0 ? (
                  <div className="rounded-xl border border-dashed p-6 text-center text-xs text-muted-foreground">
                    No links yet — highlight best sellers, support chat, or your menu.
                  </div>
                ) : (
                  <div className="space-y-2.5">
                    {linkInBioLinks.map((link, idx) => (
                      <div key={idx} className="flex items-center gap-2 rounded-xl border p-2.5">
                        <Input
                          value={link.label}
                          placeholder="Button label"
                          onChange={(e) => {
                            const next = [...linkInBioLinks]
                            next[idx] = { ...link, label: e.target.value }
                            setLinkInBioLinks(next)
                          }}
                          className="h-9 flex-1 text-xs"
                        />
                        <Input
                          value={link.url}
                          placeholder="https://…"
                          onChange={(e) => {
                            const next = [...linkInBioLinks]
                            next[idx] = { ...link, url: e.target.value }
                            setLinkInBioLinks(next)
                          }}
                          className="h-9 flex-1 font-mono text-xs"
                        />
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          onClick={() => setLinkInBioLinks(linkInBioLinks.filter((_, i) => i !== idx))}
                          className="h-9 w-9 shrink-0 text-muted-foreground hover:text-destructive"
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* TAB 2: address, findability, offers */}
        <TabsContent value="link" className="space-y-4 outline-none">
          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center gap-2">
                <Globe className="h-5 w-5 text-primary" />
                <div>
                  <CardTitle className="text-base">Store address & Google</CardTitle>
                  <CardDescription>Your link, and how it shows up on search and social</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-5">
              <div className="space-y-2">
                <Label htmlFor="storeSlug">Store link</Label>
                <div className="flex items-center gap-2">
                  <span className="font-mono text-xs text-muted-foreground">relayiq.com/s/</span>
                  <Input
                    id="storeSlug"
                    value={storeSlug}
                    onChange={(e) => setStoreSlug(e.target.value)}
                    placeholder="my-brand"
                    className="max-w-sm font-mono text-sm"
                  />
                </div>
                <p className="text-xs text-muted-foreground">Empty = auto-generated from your business name.</p>
              </div>

              <div className="space-y-2 border-t pt-5">
                <div className="flex items-center justify-between">
                  <Label htmlFor="seoTitle">Page title</Label>
                  <span className={`text-[11px] ${storefrontSeoTitle.length > 60 ? 'font-semibold text-amber-500' : 'text-muted-foreground'}`}>
                    {storefrontSeoTitle.length}/60
                  </span>
                </div>
                <Input
                  id="seoTitle"
                  value={storefrontSeoTitle}
                  onChange={(e) => setStorefrontSeoTitle(e.target.value)}
                  placeholder={`${companyName} — Official Store`}
                  maxLength={90}
                />
              </div>

              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label htmlFor="seoDesc">Search description</Label>
                  <span className={`text-[11px] ${storefrontSeoDescription.length > 160 ? 'font-semibold text-amber-500' : 'text-muted-foreground'}`}>
                    {storefrontSeoDescription.length}/160
                  </span>
                </div>
                <Textarea
                  id="seoDesc"
                  value={storefrontSeoDescription}
                  onChange={(e) => setStorefrontSeoDescription(e.target.value)}
                  rows={2}
                  maxLength={320}
                  placeholder={`Shop ${companyName} online — browse the catalog and order for delivery or pickup.`}
                  className="text-sm"
                />
              </div>

              <div className="space-y-3 border-t pt-4">
                <p className="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                  <Eye className="h-3.5 w-3.5 text-primary" /> Live preview
                </p>
                <div className="grid gap-4 lg:grid-cols-2">
                  <div className="rounded-xl border bg-white p-4 dark:bg-slate-900">
                    <p className="mb-2 text-xs font-semibold text-slate-500">Google result</p>
                    <p className="truncate text-xs text-slate-500">
                      <span className="font-medium text-slate-800 dark:text-slate-200">{companyName}</span>
                      {' › '}{storeSlug || 'store'}
                    </p>
                    <p className="mt-1 line-clamp-1 cursor-pointer text-base font-medium text-[#1a0dab] dark:text-[#8ab4f8]">
                      {storefrontSeoTitle || `${companyName} — Shop`}
                    </p>
                    <p className="mt-1 line-clamp-2 text-xs text-slate-600 dark:text-slate-300">
                      {storefrontSeoDescription || `Shop ${companyName} online.`}
                    </p>
                  </div>
                  <div className="rounded-xl border bg-[#f0f2f5] p-3 dark:bg-slate-900">
                    <p className="mb-2 text-xs font-semibold text-emerald-700 dark:text-emerald-400">WhatsApp preview</p>
                    <div className="overflow-hidden rounded-lg border bg-white dark:bg-slate-950">
                      {storefrontOgImage || companyLogo ? (
                        <img src={storefrontOgImage || companyLogo || ''} alt="Preview" className="h-28 w-full bg-slate-100 object-cover" />
                      ) : (
                        <div className="flex h-20 w-full items-center justify-center bg-slate-100 text-xs text-slate-400">
                          Add a share image in Advanced settings
                        </div>
                      )}
                      <div className="p-2.5">
                        <p className="line-clamp-1 text-xs font-semibold text-slate-900 dark:text-white">
                          {storefrontSeoTitle || `${companyName} — Shop`}
                        </p>
                        <p className="mt-0.5 line-clamp-2 text-[11px] text-slate-500">
                          {storefrontSeoDescription || `Shop ${companyName} online.`}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <StorefrontCouponsCard />

          <Link
            href="/dashboard/settings?tab=order-payments"
            className="flex items-center gap-3 rounded-2xl border px-4 py-3.5 transition-colors hover:bg-muted/40"
          >
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-muted">
              <CreditCard className="h-4 w-4" />
            </span>
            <span className="flex-1">
              <span className="block text-sm font-semibold">Checkout payments</span>
              <span className="block text-xs text-muted-foreground">M-Pesa, cards, cash and delivery live in Settings → Payments.</span>
            </span>
          </Link>
        </TabsContent>

        {/* TAB 3: power tools */}
        <TabsContent value="advanced" className="space-y-4 outline-none">
          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center gap-2">
                <Search className="h-5 w-5 text-primary" />
                <div>
                  <CardTitle className="text-base">Discoverability extras</CardTitle>
                  <CardDescription>For when basic SEO isn't enough</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="businessType">Business type</Label>
                  <select
                    id="businessType"
                    value={storefrontBusinessType}
                    onChange={(e) => setStorefrontBusinessType(e.target.value)}
                    className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
                  >
                    <option value="OnlineStore">Online store</option>
                    <option value="LocalBusiness">Local business</option>
                    <option value="Restaurant">Restaurant / food</option>
                    <option value="HealthAndBeautyBusiness">Health, salon & beauty</option>
                    <option value="ProfessionalService">Professional services</option>
                  </select>
                  <p className="text-[11px] text-muted-foreground">Helps Google categorize you for local search.</p>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="ogImage">Share image URL</Label>
                  <Input
                    id="ogImage"
                    value={storefrontOgImage}
                    onChange={(e) => setStorefrontOgImage(e.target.value)}
                    placeholder="https://…/banner-1200x630.jpg"
                  />
                  <p className="text-[11px] text-muted-foreground">1200×630 works best on WhatsApp and socials.</p>
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="gVerify">Google verification <span className="font-normal text-muted-foreground">(optional)</span></Label>
                <Input
                  id="gVerify"
                  value={storefrontGoogleSiteVerification}
                  onChange={(e) => setStorefrontGoogleSiteVerification(e.target.value)}
                  placeholder="Token from Search Console"
                  className="max-w-md"
                />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center gap-2">
                <MessageSquare className="h-5 w-5 text-primary" />
                <div>
                  <CardTitle className="text-base">Order messages</CardTitle>
                  <CardDescription>Automatic WhatsApp follow-ups that recover sales</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-3">
              <AutomationRow
                icon={ShoppingCart}
                title="Abandoned cart recovery"
                desc="Nudge shoppers back with their pre-filled cart link."
                checked={abandonedCartRecoveryEnabled}
                onCheckedChange={setAbandonedCartRecoveryEnabled}
              >
                <div className="mt-3 max-w-sm space-y-1">
                  <Label className="text-xs">Meta template (optional)</Label>
                  <Input
                    value={abandonedCartTemplateName}
                    onChange={(e) => setAbandonedCartTemplateName(e.target.value)}
                    placeholder="e.g. cart_recovery_reminder"
                    className="h-8 font-mono text-xs"
                  />
                  <p className="text-[11px] text-muted-foreground">Needed for messages outside the 24-hour window.</p>
                </div>
              </AutomationRow>
              <AutomationRow
                icon={MessageSquare}
                iconClass="text-emerald-600"
                title="Order confirmation"
                desc="Instant receipt + tracking link after checkout."
                checked={storefrontWhatsappOrderNotify}
                onCheckedChange={setStorefrontWhatsappOrderNotify}
              />
              <AutomationRow
                icon={Zap}
                iconClass="text-amber-500"
                title="Unpaid order reminders"
                desc="Follow-ups with a payment link for pending orders."
                checked={paymentRecoveryEnabled}
                onCheckedChange={setPaymentRecoveryEnabled}
              />
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center gap-2">
                <Gift className="h-5 w-5 text-primary" />
                <div>
                  <CardTitle className="text-base">Win customers back</CardTitle>
                  <CardDescription>Birthdays and quiet spells turn into repeat orders</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-3">
              <AutomationRow
                icon={Sparkles}
                iconClass="text-pink-500"
                title="Birthday treat"
                desc="Greeting + discount coupon on their birthday."
                checked={birthdayAutomationEnabled}
                onCheckedChange={setBirthdayAutomationEnabled}
              >
                <div className="mt-3 flex items-center gap-2">
                  <Label className="whitespace-nowrap text-xs">Discount %</Label>
                  <Input
                    type="number"
                    min={0}
                    max={100}
                    value={birthdayCouponPercent}
                    onChange={(e) => setBirthdayCouponPercent(e.target.value)}
                    className="h-8 w-24 text-xs"
                  />
                </div>
              </AutomationRow>
              <AutomationRow
                icon={Store}
                iconClass="text-indigo-500"
                title="Win-back campaign"
                desc="Re-engage shoppers gone quiet."
                checked={winbackAutomationEnabled}
                onCheckedChange={setWinbackAutomationEnabled}
              >
                <div className="mt-3 flex items-center gap-2">
                  <Label className="whitespace-nowrap text-xs">After</Label>
                  <Input
                    type="number"
                    min={7}
                    value={winbackDaysInactive}
                    onChange={(e) => setWinbackDaysInactive(e.target.value)}
                    className="h-8 w-24 text-xs"
                  />
                  <span className="text-xs text-muted-foreground">days quiet</span>
                </div>
              </AutomationRow>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center gap-2">
                <ShieldCheck className="h-5 w-5 text-primary" />
                <div>
                  <CardTitle className="text-base">Spam protection</CardTitle>
                  <CardDescription>Block bots by limiting orders per IP</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <AutomationRow
                icon={ShieldCheck}
                iconClass="text-emerald-600"
                title="Rate limiting"
                desc="Cap how many orders one source can place."
                checked={spamOrderProtectionEnabled}
                onCheckedChange={setSpamOrderProtectionEnabled}
              >
                <div className="mt-3 grid max-w-sm grid-cols-2 gap-3">
                  <div>
                    <Label className="text-xs">Max / hour</Label>
                    <Input
                      type="number"
                      min={1}
                      value={spamMaxOrdersPerHour}
                      onChange={(e) => setSpamMaxOrdersPerHour(e.target.value)}
                      className="mt-1 h-8 text-xs"
                    />
                  </div>
                  <div>
                    <Label className="text-xs">Max / day</Label>
                    <Input
                      type="number"
                      min={1}
                      value={spamMaxOrdersPerDay}
                      onChange={(e) => setSpamMaxOrdersPerDay(e.target.value)}
                      className="mt-1 h-8 text-xs"
                    />
                  </div>
                </div>
              </AutomationRow>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {isDirty && (
        <div className="pointer-events-none fixed inset-x-0 bottom-0 z-50 px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:bottom-6 sm:px-4 sm:pb-0">
          <div className="pointer-events-auto mx-auto flex max-w-2xl flex-col gap-3 rounded-2xl border bg-slate-900/95 p-3 text-white shadow-2xl backdrop-blur-md sm:flex-row sm:items-center sm:justify-between sm:p-3.5 dark:bg-slate-950/95">
            <div className="flex min-w-0 items-center gap-2.5">
              <span className="relative flex h-2.5 w-2.5 shrink-0">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
                <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-amber-500"></span>
              </span>
              <span className="text-xs font-semibold leading-snug text-slate-200">
                Unsaved storefront changes
              </span>
            </div>
            <div className="flex items-center gap-2">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={discardChanges}
                disabled={saving}
                className="h-9 flex-1 rounded-xl px-3 text-xs font-medium text-slate-300 hover:bg-slate-800 hover:text-white sm:h-8 sm:flex-none"
              >
                Discard
              </Button>
              <Button
                type="button"
                size="sm"
                onClick={() => void save()}
                disabled={saving}
                className="h-9 flex-1 rounded-xl bg-primary px-4 text-xs font-semibold text-primary-foreground sm:h-8 sm:flex-none"
              >
                {saving ? <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" /> : <Check className="mr-1.5 h-3.5 w-3.5" />}
                Save changes
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
