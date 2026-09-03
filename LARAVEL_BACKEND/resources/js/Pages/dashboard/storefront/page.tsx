'use client'

import { useCallback, useEffect, useState } from 'react'
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
  Copy,
  ExternalLink,
  Loader2,
  Plus,
  Trash2,
  Store,
  Palette,
  Share2,
  Bot,
  CreditCard,
  Search,
  Sparkles,
  MessageSquare,
  ShieldCheck,
  Check,
  Globe,
  UtensilsCrossed,
  Truck,
  ShoppingCart,
  Zap,
  Gift,
  Eye,
} from 'lucide-react'
import { Link } from '@inertiajs/react'
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
  storefrontSeoTitle?: string
  storefrontSeoDescription?: string
  storefrontOgImage?: string
  storefrontGoogleSiteVerification?: string
  storefrontBusinessType?: string
  storefrontTheme?: BrandTheme
}

export default function DashboardStorefrontPage() {
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)
  const [copiedUrl, setCopiedUrl] = useState(false)
  const [activeTab, setActiveTab] = useState('brand')

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
  const [storefrontSeoTitle, setStorefrontSeoTitle] = useState('')
  const [storefrontSeoDescription, setStorefrontSeoDescription] = useState('')
  const [storefrontOgImage, setStorefrontOgImage] = useState('')
  const [storefrontGoogleSiteVerification, setStorefrontGoogleSiteVerification] = useState('')
  const [storefrontBusinessType, setStorefrontBusinessType] = useState('OnlineStore')

  // Baseline state snapshot for dirty check
  const [initialSnapshot, setInitialSnapshot] = useState<string>('')

  const currentSnapshot = JSON.stringify({
    storeSlug,
    storefrontEnabled,
    linkInBioEnabled,
    linkInBioHeadline,
    linkInBioBio,
    linkInBioLinks,
    ordersAcceptCod,
    ordersAcceptBankTransfer,
    bankTransferInstructions,
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
    dineInEnabled,
    storefrontSeoTitle,
    storefrontSeoDescription,
    storefrontOgImage,
    storefrontGoogleSiteVerification,
    storefrontBusinessType,
  })

  const isDirty = initialSnapshot !== '' && initialSnapshot !== currentSnapshot

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await apiRequest<SettingsResponse>('/api/company/settings')
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
      setStorefrontSeoTitle(data.storefrontSeoTitle || '')
      setStorefrontSeoDescription(data.storefrontSeoDescription || '')
      setStorefrontOgImage(data.storefrontOgImage || '')
      setStorefrontGoogleSiteVerification(data.storefrontGoogleSiteVerification || '')
      setStorefrontBusinessType(data.storefrontBusinessType || 'OnlineStore')

      setInitialSnapshot(JSON.stringify({
        storeSlug: data.storeSlug || '',
        storefrontEnabled: !!data.storefrontEnabled,
        linkInBioEnabled: !!data.linkInBioEnabled,
        linkInBioHeadline: data.linkInBioHeadline || '',
        linkInBioBio: data.linkInBioBio || '',
        linkInBioLinks: data.linkInBioLinks?.length ? data.linkInBioLinks : [],
        ordersAcceptCod: !!data.ordersAcceptCod,
        ordersAcceptBankTransfer: !!data.ordersAcceptBankTransfer,
        bankTransferInstructions: data.bankTransferInstructions || '',
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
        dineInEnabled: !!data.dineInEnabled,
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
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const discardChanges = () => {
    if (initialSnapshot) {
      try {
        const snap = JSON.parse(initialSnapshot)
        setStoreSlug(snap.storeSlug)
        setStorefrontEnabled(snap.storefrontEnabled)
        setLinkInBioEnabled(snap.linkInBioEnabled)
        setLinkInBioHeadline(snap.linkInBioHeadline)
        setLinkInBioBio(snap.linkInBioBio)
        setLinkInBioLinks(snap.linkInBioLinks)
        setOrdersAcceptCod(snap.ordersAcceptCod)
        setOrdersAcceptBankTransfer(snap.ordersAcceptBankTransfer)
        setBankTransferInstructions(snap.bankTransferInstructions)
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
        setDineInEnabled(snap.dineInEnabled)
        setStorefrontSeoTitle(snap.storefrontSeoTitle)
        setStorefrontSeoDescription(snap.storefrontSeoDescription)
        setStorefrontOgImage(snap.storefrontOgImage)
        setStorefrontGoogleSiteVerification(snap.storefrontGoogleSiteVerification)
        setStorefrontBusinessType(snap.storefrontBusinessType)
      } catch {
        void load()
      }
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
        <p className="text-sm font-medium">Loading storefront command center…</p>
      </div>
    )
  }

  return (
    <div className="space-y-6 pb-16">
      {/* Top Header & Command Bar */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
        <div>
          <div className="flex items-center gap-2.5">
            <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Storefront &amp; Commerce</h1>
            <Badge
              variant={storefrontEnabled ? 'default' : 'secondary'}
              className={`font-semibold ${storefrontEnabled ? 'bg-emerald-600 text-white' : 'text-slate-500'}`}
            >
              {storefrontEnabled ? '● Live' : '○ Offline'}
            </Badge>
          </div>
          <p className="mt-1 text-sm text-muted-foreground">
            Configure your customer-facing shop, brand visuals, link-in-bio, and automated WhatsApp commerce.
          </p>
        </div>

        <div className="flex items-center gap-2.5">
          {storefrontUrl && storefrontEnabled && (
            <Button variant="outline" size="sm" asChild className="gap-1.5 shadow-xs">
              <a href={storefrontUrl} target="_blank" rel="noreferrer">
                <ExternalLink className="h-3.5 w-3.5" /> Visit Store
              </a>
            </Button>
          )}

          <Button onClick={() => void save()} disabled={saving} className="gap-1.5 shadow-xs">
            {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
            Save Changes
          </Button>
        </div>
      </div>

      {/* Alert Messages */}
      {error && (
        <div className="rounded-xl border border-destructive/20 bg-destructive/10 p-3.5 text-sm font-medium text-destructive">
          {error}
        </div>
      )}

      {saved && (
        <div className="flex items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3.5 text-sm font-medium text-emerald-600 dark:text-emerald-400">
          <Check className="h-4 w-4" /> All storefront settings saved successfully!
        </div>
      )}

      {/* Quick Status & Live Link Hero Banner */}
      <div className="rounded-2xl border bg-gradient-to-r from-slate-50 to-slate-100/60 p-5 shadow-xs dark:from-slate-900/60 dark:to-slate-900/30">
        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <Store className="h-4 w-4 text-primary" />
              <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Storefront Status</span>
            </div>
            <div className="flex items-center gap-3">
              <h2 className="text-lg font-bold text-slate-900 dark:text-white">
                {storefrontEnabled ? `${companyName} Storefront is Active` : `${companyName} Storefront is Paused`}
              </h2>
              <Switch
                checked={storefrontEnabled}
                onCheckedChange={(checked) => setStorefrontEnabled(checked)}
              />
            </div>
            <p className="text-xs text-muted-foreground">
              {storefrontEnabled
                ? 'Your public catalog is live and receiving visitor orders.'
                : 'Your public catalog is currently offline. Turn on to allow customer orders.'}
            </p>
          </div>

          {storefrontUrl && (
            <div className="flex flex-wrap items-center gap-2">
              <div className="flex items-center gap-1.5 rounded-xl border bg-background px-3 py-1.5 text-xs font-mono shadow-2xs">
                <Globe className="h-3.5 w-3.5 text-muted-foreground" />
                <span className="truncate max-w-[18rem]">{storefrontUrl}</span>
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={() => void copy(storefrontUrl)}
                className="gap-1 shadow-2xs"
              >
                {copiedUrl ? <Check className="h-3.5 w-3.5 text-emerald-600" /> : <Copy className="h-3.5 w-3.5" />}
                {copiedUrl ? 'Copied!' : 'Copy'}
              </Button>
              <Button variant="outline" size="sm" asChild className="gap-1 shadow-2xs">
                <a href={storefrontUrl} target="_blank" rel="noreferrer">
                  <ExternalLink className="h-3.5 w-3.5" /> Preview
                </a>
              </Button>
            </div>
          )}
        </div>

        {/* Quick Nav Shortcut Pills */}
        <div className="mt-4 flex flex-wrap items-center gap-2 border-t pt-3.5 text-xs text-muted-foreground">
          <span className="font-semibold text-foreground">Related Commerce Tools:</span>
          <Link
            href="/dashboard/delivery"
            className="inline-flex items-center gap-1 rounded-lg border bg-background/80 px-2.5 py-1 text-slate-700 transition-colors hover:bg-background hover:text-foreground dark:text-slate-300"
          >
            <Truck className="h-3 w-3 text-primary" /> Delivery Zones &amp; Fees
          </Link>
          <Link
            href="/dashboard/dine-in"
            className="inline-flex items-center gap-1 rounded-lg border bg-background/80 px-2.5 py-1 text-slate-700 transition-colors hover:bg-background hover:text-foreground dark:text-slate-300"
          >
            <UtensilsCrossed className="h-3 w-3 text-primary" /> Dine-In Table QR Ordering
          </Link>
        </div>
      </div>

      {/* Main Tabbed Navigation Center */}
      <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
        <TabsList className="grid h-11 w-full grid-cols-2 md:grid-cols-5 p-1 bg-muted/70 rounded-xl">
          <TabsTrigger value="brand" className="gap-2 rounded-lg text-xs font-semibold">
            <Palette className="h-3.5 w-3.5" />
            <span>Brand &amp; Design</span>
          </TabsTrigger>
          <TabsTrigger value="settings" className="gap-2 rounded-lg text-xs font-semibold">
            <Search className="h-3.5 w-3.5" />
            <span>Slug &amp; SEO</span>
          </TabsTrigger>
          <TabsTrigger value="automations" className="gap-2 rounded-lg text-xs font-semibold">
            <Bot className="h-3.5 w-3.5" />
            <span>Automations</span>
          </TabsTrigger>
          <TabsTrigger value="bio" className="gap-2 rounded-lg text-xs font-semibold">
            <Share2 className="h-3.5 w-3.5" />
            <span>Link-in-Bio</span>
          </TabsTrigger>
          <TabsTrigger value="checkout" className="gap-2 rounded-lg text-xs font-semibold">
            <CreditCard className="h-3.5 w-3.5" />
            <span>Payments &amp; Coupons</span>
          </TabsTrigger>
        </TabsList>

        {/* TAB 1: Brand & Design */}
        <TabsContent value="brand" className="space-y-6 outline-none">
          <BrandCustomizationCard
            initialLogo={companyLogo}
            initialTheme={storefrontTheme}
            initialAnnouncementBar={announcementBar}
            initialFooterText={storefrontTheme?.footer_text}
            businessName={companyName}
            storeSlug={storeSlug}
            onSaved={load}
          />
        </TabsContent>

        {/* TAB 2: Slug & SEO Settings */}
        <TabsContent value="settings" className="space-y-6 outline-none">
          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center gap-2">
                <Globe className="h-5 w-5 text-primary" />
                <div>
                  <CardTitle className="text-base">Store Identity &amp; URL</CardTitle>
                  <CardDescription>Choose how customers access your public online shop</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="storeSlug">Public Store Slug</Label>
                <div className="flex items-center gap-2">
                  <span className="text-xs text-muted-foreground font-mono">relayiq.com/s/</span>
                  <Input
                    id="storeSlug"
                    value={storeSlug}
                    onChange={(e) => setStoreSlug(e.target.value)}
                    placeholder="my-brand"
                    className="font-mono text-sm max-w-sm"
                  />
                </div>
                <p className="text-xs text-muted-foreground">
                  Leave empty to auto-generate based on your registered business name.
                </p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center gap-2">
                <Search className="h-5 w-5 text-primary" />
                <div>
                  <CardTitle className="text-base">Search Engine Optimization (SEO) &amp; Social Previews</CardTitle>
                  <CardDescription>Optimize rankings on Google and card rich previews when shared on WhatsApp &amp; socials</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <Label htmlFor="seoTitle">SEO Title Tag</Label>
                    <span className={`text-[11px] ${storefrontSeoTitle.length > 60 ? 'text-amber-500 font-semibold' : 'text-muted-foreground'}`}>
                      {storefrontSeoTitle.length}/60 chars
                    </span>
                  </div>
                  <Input
                    id="seoTitle"
                    value={storefrontSeoTitle}
                    onChange={(e) => setStorefrontSeoTitle(e.target.value)}
                    placeholder={`${companyName} — Official Store`}
                    maxLength={90}
                  />
                  <p className="text-[11px] text-muted-foreground">Displayed on Google Search results and browser tabs.</p>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="businessType">Google Structured Data Schema</Label>
                  <select
                    id="businessType"
                    value={storefrontBusinessType}
                    onChange={(e) => setStorefrontBusinessType(e.target.value)}
                    className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                  >
                    <option value="OnlineStore">Online Store (E-Commerce)</option>
                    <option value="LocalBusiness">Local Business (General)</option>
                    <option value="Restaurant">Restaurant / Food &amp; Beverage</option>
                    <option value="HealthAndBeautyBusiness">Health, Salon &amp; Beauty</option>
                    <option value="ProfessionalService">Professional Services &amp; Consulting</option>
                  </select>
                  <p className="text-[11px] text-muted-foreground">Helps Google categorize your entity for Local Search.</p>
                </div>
              </div>

              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label htmlFor="seoDesc">Meta Description</Label>
                  <span className={`text-[11px] ${storefrontSeoDescription.length > 160 ? 'text-amber-500 font-semibold' : 'text-muted-foreground'}`}>
                    {storefrontSeoDescription.length}/160 chars
                  </span>
                </div>
                <Textarea
                  id="seoDesc"
                  value={storefrontSeoDescription}
                  onChange={(e) => setStorefrontSeoDescription(e.target.value)}
                  rows={2}
                  maxLength={320}
                  placeholder={`Shop ${companyName} online. Browse our catalog and order for fast delivery or pickup.`}
                  className="text-sm"
                />
                <p className="text-[11px] text-muted-foreground">
                  Snippet shown under your store link on Google search results. Keep between 120–160 characters for best CTR.
                </p>
              </div>

              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="ogImage">Social Share Banner URL (OG Image)</Label>
                  <Input
                    id="ogImage"
                    value={storefrontOgImage}
                    onChange={(e) => setStorefrontOgImage(e.target.value)}
                    placeholder="https://.../banner-1200x630.jpg"
                  />
                  <p className="text-[11px] text-muted-foreground">Optimal size 1200x630px. Used when shared across WhatsApp or socials.</p>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="gVerify">Google Site Verification Token</Label>
                  <Input
                    id="gVerify"
                    value={storefrontGoogleSiteVerification}
                    onChange={(e) => setStorefrontGoogleSiteVerification(e.target.value)}
                    placeholder="google-site-verification token or code"
                  />
                  <p className="text-[11px] text-muted-foreground">Paste from Google Search Console to index your storefront domain.</p>
                </div>
              </div>

              {/* Live Search & Social Previews */}
              <div className="space-y-4 pt-4 border-t">
                <div className="flex items-center gap-2">
                  <Eye className="h-4 w-4 text-primary" />
                  <h4 className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Live Search &amp; Social Previews</h4>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                  {/* Google SERP Snippet Preview */}
                  <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center gap-1.5 text-xs text-slate-500 mb-2 font-semibold">
                      <span>Google Search Result Snippet</span>
                    </div>
                    <div className="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                      <div className="flex h-4 w-4 items-center justify-center rounded-full bg-slate-200 text-[9px] font-bold text-slate-700">
                        {companyName.charAt(0)}
                      </div>
                      <span className="font-medium text-slate-800 dark:text-slate-200">{companyName}</span>
                      <span>›</span>
                      <span className="truncate text-slate-500">{storeSlug || 'store'}</span>
                    </div>
                    <h5 className="mt-1 text-base font-medium text-[#1a0dab] hover:underline dark:text-[#8ab4f8] line-clamp-1 cursor-pointer">
                      {storefrontSeoTitle || `${companyName} — Shop`}
                    </h5>
                    <p className="mt-1 text-xs text-slate-600 dark:text-slate-300 line-clamp-2">
                      {storefrontSeoDescription || `Shop ${companyName} online. Browse products and order for delivery or pickup with instant checkout.`}
                    </p>
                  </div>

                  {/* WhatsApp Social Card Preview */}
                  <div className="rounded-xl border border-slate-200 bg-[#f0f2f5] p-3 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center gap-1.5 text-xs text-slate-500 mb-2 font-semibold">
                      <span className="text-emerald-700 dark:text-emerald-400">WhatsApp Link Preview Card</span>
                    </div>
                    <div className="overflow-hidden rounded-lg border border-slate-300/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-950">
                      {storefrontOgImage || companyLogo ? (
                        <img
                          src={storefrontOgImage || companyLogo || ''}
                          alt="Preview"
                          className="h-28 w-full object-cover bg-slate-100"
                        />
                      ) : (
                        <div className="flex h-20 w-full items-center justify-center bg-slate-100 text-xs text-slate-400 dark:bg-slate-900">
                          Upload an OG Image or Logo for rich preview card
                        </div>
                      )}
                      <div className="p-2.5">
                        <p className="text-xs font-semibold text-slate-900 line-clamp-1 dark:text-white">
                          {storefrontSeoTitle || `${companyName} — Shop`}
                        </p>
                        <p className="mt-0.5 text-[11px] text-slate-500 line-clamp-2 dark:text-slate-400">
                          {storefrontSeoDescription || `Shop ${companyName} online.`}
                        </p>
                        <p className="mt-1 text-[10px] uppercase tracking-wider text-slate-400 font-mono">
                          {storeSlug ? `relayiq.com/s/${storeSlug}` : 'relayiq.com'}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* TAB 3: Automations & WhatsApp */}
        <TabsContent value="automations" className="space-y-6 outline-none">
          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center gap-2">
                <Bot className="h-5 w-5 text-primary" />
                <div>
                  <CardTitle className="text-base">WhatsApp Order &amp; Recovery Automations</CardTitle>
                  <CardDescription>Recover abandoned checkouts and keep shoppers notified via WhatsApp</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-5">
              {/* Feature 1: Abandoned Cart Recovery */}
              <div className="flex items-start justify-between gap-4 rounded-xl border p-4 bg-muted/15">
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <ShoppingCart className="h-4 w-4 text-primary" />
                    <span className="font-semibold text-sm">Abandoned Cart WhatsApp Recovery</span>
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Automatically send a direct WhatsApp nudge with their pre-filled cart link to complete checkout.
                  </p>
                  {abandonedCartRecoveryEnabled && (
                    <div className="mt-3 space-y-1 max-w-sm">
                      <Label className="text-xs">Meta Template Name (Optional)</Label>
                      <Input
                        value={abandonedCartTemplateName}
                        onChange={(e) => setAbandonedCartTemplateName(e.target.value)}
                        placeholder="e.g. cart_recovery_reminder"
                        className="h-8 text-xs font-mono"
                      />
                      <p className="text-[10px] text-muted-foreground">Used for messages outside the 24-hour customer window.</p>
                    </div>
                  )}
                </div>
                <Switch
                  checked={abandonedCartRecoveryEnabled}
                  onCheckedChange={(checked) => setAbandonedCartRecoveryEnabled(checked)}
                />
              </div>

              {/* Feature 2: Post-Order WhatsApp Confirmation */}
              <div className="flex items-start justify-between gap-4 rounded-xl border p-4 bg-muted/15">
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <MessageSquare className="h-4 w-4 text-emerald-600" />
                    <span className="font-semibold text-sm">WhatsApp Order Confirmation</span>
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Instantly send an order receipt and live tracking link to the customer’s phone upon storefront checkout.
                  </p>
                </div>
                <Switch
                  checked={storefrontWhatsappOrderNotify}
                  onCheckedChange={(checked) => setStorefrontWhatsappOrderNotify(checked)}
                />
              </div>

              {/* Feature 3: Unpaid Order Recovery */}
              <div className="flex items-start justify-between gap-4 rounded-xl border p-4 bg-muted/15">
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <Zap className="h-4 w-4 text-amber-500" />
                    <span className="font-semibold text-sm">Unpaid Order Payment Reminders</span>
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Send follow-up WhatsApp reminders with payment links for orders pending bank transfer or mobile money.
                  </p>
                </div>
                <Switch
                  checked={paymentRecoveryEnabled}
                  onCheckedChange={(checked) => setPaymentRecoveryEnabled(checked)}
                />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center gap-2">
                <Gift className="h-5 w-5 text-primary" />
                <div>
                  <CardTitle className="text-base">Customer Retention &amp; Protection</CardTitle>
                  <CardDescription>Automatic reward campaigns and protection against abusive automated orders</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-5">
              {/* Retention: Birthday Automation */}
              <div className="flex items-start justify-between gap-4 rounded-xl border p-4 bg-muted/15">
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <Sparkles className="h-4 w-4 text-pink-500" />
                    <span className="font-semibold text-sm">Birthday Celebration Messages</span>
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Send personalized greeting and special discount coupon to customers on their birthday.
                  </p>
                  {birthdayAutomationEnabled && (
                    <div className="mt-3 flex items-center gap-2">
                      <Label className="text-xs whitespace-nowrap">Birthday Discount %</Label>
                      <Input
                        type="number"
                        min={0}
                        max={100}
                        value={birthdayCouponPercent}
                        onChange={(e) => setBirthdayCouponPercent(e.target.value)}
                        className="h-8 w-24 text-xs"
                      />
                    </div>
                  )}
                </div>
                <Switch
                  checked={birthdayAutomationEnabled}
                  onCheckedChange={(checked) => setBirthdayAutomationEnabled(checked)}
                />
              </div>

              {/* Retention: Winback Inactive Customers */}
              <div className="flex items-start justify-between gap-4 rounded-xl border p-4 bg-muted/15">
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <Store className="h-4 w-4 text-indigo-500" />
                    <span className="font-semibold text-sm">Customer Win-Back Campaigns</span>
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Automatically re-engage shoppers who haven’t placed an order in a set number of days.
                  </p>
                  {winbackAutomationEnabled && (
                    <div className="mt-3 flex items-center gap-2">
                      <Label className="text-xs whitespace-nowrap">Days Inactive</Label>
                      <Input
                        type="number"
                        min={7}
                        value={winbackDaysInactive}
                        onChange={(e) => setWinbackDaysInactive(e.target.value)}
                        className="h-8 w-24 text-xs"
                      />
                      <span className="text-xs text-muted-foreground">days</span>
                    </div>
                  )}
                </div>
                <Switch
                  checked={winbackAutomationEnabled}
                  onCheckedChange={(checked) => setWinbackAutomationEnabled(checked)}
                />
              </div>

              {/* Security: Spam Order Protection */}
              <div className="flex items-start justify-between gap-4 rounded-xl border p-4 bg-muted/15">
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <ShieldCheck className="h-4 w-4 text-emerald-600" />
                    <span className="font-semibold text-sm">Spam Order Rate Limiting</span>
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Block abusive bots and test scripts by restricting how many orders can be submitted from one IP/client.
                  </p>
                  {spamOrderProtectionEnabled && (
                    <div className="mt-3 grid grid-cols-2 gap-3 max-w-sm">
                      <div>
                        <Label className="text-xs">Max orders / hour</Label>
                        <Input
                          type="number"
                          min={1}
                          value={spamMaxOrdersPerHour}
                          onChange={(e) => setSpamMaxOrdersPerHour(e.target.value)}
                          className="h-8 text-xs mt-1"
                        />
                      </div>
                      <div>
                        <Label className="text-xs">Max orders / day</Label>
                        <Input
                          type="number"
                          min={1}
                          value={spamMaxOrdersPerDay}
                          onChange={(e) => setSpamMaxOrdersPerDay(e.target.value)}
                          className="h-8 text-xs mt-1"
                        />
                      </div>
                    </div>
                  )}
                </div>
                <Switch
                  checked={spamOrderProtectionEnabled}
                  onCheckedChange={(checked) => setSpamOrderProtectionEnabled(checked)}
                />
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* TAB 4: Link-in-Bio */}
        <TabsContent value="bio" className="space-y-6 outline-none">
          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Share2 className="h-5 w-5 text-primary" />
                  <div>
                    <CardTitle className="text-base">Link-in-Bio Landing Page</CardTitle>
                    <CardDescription>Social media link hub for Instagram, TikTok, and WhatsApp</CardDescription>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-xs font-semibold text-muted-foreground">Enable Page</span>
                  <Switch
                    checked={linkInBioEnabled}
                    onCheckedChange={(checked) => setLinkInBioEnabled(checked)}
                  />
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-5">
              {linkInBioUrl && (
                <div className="flex flex-wrap items-center gap-2 rounded-xl border bg-muted/20 p-3">
                  <span className="text-xs font-semibold text-muted-foreground">Bio Link:</span>
                  <Input value={linkInBioUrl} readOnly className="font-mono text-xs max-w-md h-8" />
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
                  <Label htmlFor="bioHeadline">Profile Headline</Label>
                  <Input
                    id="bioHeadline"
                    value={linkInBioHeadline}
                    onChange={(e) => setLinkInBioHeadline(e.target.value)}
                    placeholder={companyName}
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="bioText">Short Bio / Tagline</Label>
                  <Textarea
                    id="bioText"
                    value={linkInBioBio}
                    onChange={(e) => setLinkInBioBio(e.target.value)}
                    rows={2}
                    placeholder="Welcome to our official links. Tap below to shop, contact support, or view offers."
                  />
                </div>
              </div>

              {/* Custom Action Links */}
              <div className="space-y-3 pt-2 border-t">
                <div className="flex items-center justify-between">
                  <Label className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Action Links</Label>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setLinkInBioLinks((prev) => [...prev, { label: '', url: '' }])}
                    className="gap-1 text-xs h-8"
                  >
                    <Plus className="h-3.5 w-3.5" /> Add New Link
                  </Button>
                </div>

                {linkInBioLinks.length === 0 ? (
                  <div className="rounded-xl border border-dashed p-6 text-center text-xs text-muted-foreground">
                    No custom links added yet. Click &quot;Add New Link&quot; to highlight products, WhatsApp support, or menus.
                  </div>
                ) : (
                  <div className="space-y-2.5">
                    {linkInBioLinks.map((link, idx) => (
                      <div key={idx} className="flex items-center gap-2 rounded-xl border bg-card p-2.5 shadow-2xs">
                        <Input
                          value={link.label}
                          placeholder="Button Label (e.g. Shop Best Sellers)"
                          onChange={(e) => {
                            const next = [...linkInBioLinks]
                            next[idx] = { ...link, label: e.target.value }
                            setLinkInBioLinks(next)
                          }}
                          className="h-9 text-xs flex-1"
                        />
                        <Input
                          value={link.url}
                          placeholder="https://..."
                          onChange={(e) => {
                            const next = [...linkInBioLinks]
                            next[idx] = { ...link, url: e.target.value }
                            setLinkInBioLinks(next)
                          }}
                          className="h-9 text-xs flex-2 font-mono"
                        />
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          onClick={() => setLinkInBioLinks(linkInBioLinks.filter((_, i) => i !== idx))}
                          className="h-9 w-9 text-slate-400 hover:text-rose-600 shrink-0"
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

        {/* TAB 5: Local Payments, Dine-In & Coupons */}
        <TabsContent value="checkout" className="space-y-6 outline-none">
          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center gap-2">
                <CreditCard className="h-5 w-5 text-primary" />
                <div>
                  <CardTitle className="text-base">Local Payment Options</CardTitle>
                  <CardDescription>Configure payment methods available on public store checkout</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center justify-between rounded-xl border p-4 bg-muted/15">
                <div className="space-y-0.5">
                  <span className="font-semibold text-sm">Cash on Delivery (COD)</span>
                  <p className="text-xs text-muted-foreground">
                    Allow shoppers to pay with physical cash upon receiving their order.
                  </p>
                </div>
                <Switch
                  checked={ordersAcceptCod}
                  onCheckedChange={(checked) => setOrdersAcceptCod(checked)}
                />
              </div>

              <div className="space-y-3 rounded-xl border p-4 bg-muted/15">
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <span className="font-semibold text-sm">Direct Bank Transfer / Offline Mobile Money</span>
                    <p className="text-xs text-muted-foreground">
                      Display manual account details for customer wire transfers before fulfillment.
                    </p>
                  </div>
                  <Switch
                    checked={ordersAcceptBankTransfer}
                    onCheckedChange={(checked) => setOrdersAcceptBankTransfer(checked)}
                  />
                </div>

                {ordersAcceptBankTransfer && (
                  <div className="space-y-1.5 pt-2 border-t">
                    <Label className="text-xs">Bank Transfer Instructions / Account Info</Label>
                    <Textarea
                      rows={3}
                      value={bankTransferInstructions}
                      onChange={(e) => setBankTransferInstructions(e.target.value)}
                      placeholder="e.g. Bank: Standard Chartered | Acc: 123456789 | Reference: Your Order #"
                      className="text-xs"
                    />
                  </div>
                )}
              </div>

              <div className="flex items-center justify-between rounded-xl border p-4 bg-muted/15">
                <div className="space-y-0.5">
                  <span className="font-semibold text-sm">Dine-In Table Ordering</span>
                  <p className="text-xs text-muted-foreground">
                    Allow seated restaurant customers to order directly from table QR codes.
                  </p>
                </div>
                <Switch
                  checked={dineInEnabled}
                  onCheckedChange={(checked) => setDineInEnabled(checked)}
                />
              </div>
            </CardContent>
          </Card>

          {/* Embedded Coupons Management */}
          <StorefrontCouponsCard />
        </TabsContent>
      </Tabs>

      {/* Floating Sticky Unsaved Changes Action Bar (Shopify / Stripe UX Pattern) */}
      <div
        className={`fixed bottom-6 inset-x-0 mx-auto max-w-2xl px-4 z-40 transition-all duration-300 pointer-events-none ${
          isDirty ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'
        }`}
      >
        <div className="flex items-center justify-between gap-3 rounded-2xl border border-slate-800/80 bg-slate-900/95 p-3.5 text-white shadow-2xl backdrop-blur-md pointer-events-auto dark:border-slate-700/80 dark:bg-slate-950/95">
          <div className="flex items-center gap-2.5 pl-1.5">
            <span className="relative flex h-2.5 w-2.5">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
              <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
            </span>
            <span className="text-xs font-semibold text-slate-200">You have unsaved storefront changes</span>
          </div>

          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={discardChanges}
              disabled={saving}
              className="h-8 rounded-xl px-3 text-xs font-medium text-slate-300 hover:bg-slate-800 hover:text-white"
            >
              Discard
            </Button>
            <Button
              type="button"
              size="sm"
              onClick={() => void save()}
              disabled={saving}
              className="h-8 rounded-xl bg-primary px-4 text-xs font-semibold text-primary-foreground shadow-md transition-all hover:opacity-95"
            >
              {saving ? <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" /> : <Check className="mr-1.5 h-3.5 w-3.5" />}
              Save Changes
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}

