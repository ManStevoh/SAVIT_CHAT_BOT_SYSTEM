'use client'

import React, { useState, useEffect, useRef } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import {
  COLOR_PRESETS,
  FONT_OPTIONS,
  RADIUS_OPTIONS,
  type BrandTheme,
} from '@/lib/theme-utils'
import {
  Palette,
  Upload,
  Trash2,
  Check,
  Sparkles,
  Smartphone,
  Store,
  MessageSquare,
  Loader2,
  RefreshCw,
  Eye,
  Type,
  Maximize2,
  ShoppingBag,
  ExternalLink,
} from 'lucide-react'
import { apiRequest } from '@/lib/api-client'
import { useSWRConfig } from 'swr'

interface BrandCustomizationCardProps {
  initialLogo?: string | null
  initialTheme?: BrandTheme | null
  initialAnnouncementBar?: string | null
  initialFooterText?: string | null
  businessName?: string
  storeSlug?: string | null
  onSaved?: () => void
}

export function BrandCustomizationCard({
  initialLogo = null,
  initialTheme = {},
  initialAnnouncementBar = '',
  initialFooterText = '',
  businessName = 'My Brand',
  storeSlug = '',
  onSaved,
}: BrandCustomizationCardProps) {
  const { mutate } = useSWRConfig()
  const fileInputRef = useRef<HTMLInputElement>(null)

  // Current State
  const [logoUrl, setLogoUrl] = useState<string | null>(initialLogo)
  const [logoFile, setLogoFile] = useState<File | null>(null)
  const [logoRemoved, setLogoRemoved] = useState(false)

  const [primaryColor, setPrimaryColor] = useState(initialTheme?.primary_color || '#2563eb')
  const [accentColor, setAccentColor] = useState(initialTheme?.accent_color || '#3b82f6')
  const [bgColor, setBgColor] = useState(initialTheme?.bg_color || '#ffffff')
  const [fontFamily, setFontFamily] = useState(initialTheme?.font_family || 'sans')
  const [borderRadius, setBorderRadius] = useState(initialTheme?.border_radius || 'md')
  const [announcementBar, setAnnouncementBar] = useState(initialTheme?.announcement_bar || initialAnnouncementBar || '')
  const [announcementBg, setAnnouncementBg] = useState(initialTheme?.announcement_bar_bg || '')
  const [announcementText, setAnnouncementText] = useState(initialTheme?.announcement_bar_text || '')
  const [footerText, setFooterText] = useState(initialTheme?.footer_text || initialFooterText || '')
  const [whatsappBtnText, setWhatsappBtnText] = useState(initialTheme?.whatsapp_btn_text || '')
  const [heroEnabled, setHeroEnabled] = useState(Boolean(initialTheme?.hero_enabled))
  const [heroHeadline, setHeroHeadline] = useState(initialTheme?.hero_headline || '')
  const [heroSubhead, setHeroSubhead] = useState(initialTheme?.hero_subhead || '')
  const [heroCtaLabel, setHeroCtaLabel] = useState(initialTheme?.hero_cta_label || 'Shop Catalog')
  const [heroCtaHref, setHeroCtaHref] = useState(initialTheme?.hero_cta_href || '#catalog')

  const [previewTab, setPreviewTab] = useState<'storefront' | 'bio' | 'widget'>('storefront')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState(false)

  // Sync initial props if they update from parent fetch
  useEffect(() => {
    if (initialLogo !== undefined) setLogoUrl(initialLogo)
    if (initialTheme?.primary_color) setPrimaryColor(initialTheme.primary_color)
    if (initialTheme?.accent_color) setAccentColor(initialTheme.accent_color)
    if (initialTheme?.bg_color) setBgColor(initialTheme.bg_color)
    if (initialTheme?.font_family) setFontFamily(initialTheme.font_family)
    if (initialTheme?.border_radius) setBorderRadius(initialTheme.border_radius)
    if (initialTheme?.announcement_bar || initialAnnouncementBar) {
      setAnnouncementBar(initialTheme?.announcement_bar || initialAnnouncementBar || '')
    }
    if (initialTheme?.announcement_bar_bg) setAnnouncementBg(initialTheme.announcement_bar_bg)
    if (initialTheme?.announcement_bar_text) setAnnouncementText(initialTheme.announcement_bar_text)
    if (initialTheme?.footer_text || initialFooterText) {
      setFooterText(initialTheme?.footer_text || initialFooterText || '')
    }
    if (initialTheme?.whatsapp_btn_text) setWhatsappBtnText(initialTheme.whatsapp_btn_text)
    if (initialTheme?.hero_enabled !== undefined) setHeroEnabled(Boolean(initialTheme.hero_enabled))
    if (initialTheme?.hero_headline) setHeroHeadline(initialTheme.hero_headline)
    if (initialTheme?.hero_subhead) setHeroSubhead(initialTheme.hero_subhead)
    if (initialTheme?.hero_cta_label) setHeroCtaLabel(initialTheme.hero_cta_label)
    if (initialTheme?.hero_cta_href) setHeroCtaHref(initialTheme.hero_cta_href)
  }, [initialLogo, initialTheme, initialAnnouncementBar, initialFooterText])

  const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return

    if (!file.type.startsWith('image/')) {
      setError('Please upload a valid image file (PNG, JPG, SVG, WebP).')
      return
    }

    if (file.size > 2 * 1024 * 1024) {
      setError('Logo size should be under 2MB.')
      return
    }

    setError(null)
    setLogoFile(file)
    setLogoRemoved(false)
    setLogoUrl(URL.createObjectURL(file))
  }

  const handleRemoveLogo = () => {
    setLogoFile(null)
    setLogoUrl(null)
    setLogoRemoved(true)
    if (fileInputRef.current) {
      fileInputRef.current.value = ''
    }
  }

  const applyPreset = (preset: (typeof COLOR_PRESETS)[0]) => {
    setPrimaryColor(preset.primary)
    setAccentColor(preset.accent)
  }

  const handleSave = async () => {
    setSaving(true)
    setError(null)
    setSuccess(false)

    try {
      // 1. If logo changed or removed, send via FormData
      if (logoFile || logoRemoved) {
        const formData = new FormData()
        if (logoFile) {
          formData.append('logo', logoFile)
        }
        if (logoRemoved) {
          formData.append('removeLogo', '1')
        }

        await apiRequest('/api/company/settings', {
          method: 'POST',
          body: formData,
        })
      }

      // 2. Save theme settings JSON
      const themePayload: BrandTheme = {
        primary_color: primaryColor || null,
        accent_color: accentColor || null,
        bg_color: bgColor || null,
        font_family: fontFamily || null,
        border_radius: borderRadius || null,
        announcement_bar: announcementBar || null,
        announcement_bar_bg: announcementBg || null,
        announcement_bar_text: announcementText || null,
        footer_text: footerText || null,
        whatsapp_btn_text: whatsappBtnText || null,
        hero_enabled: heroEnabled,
        hero_headline: heroHeadline || null,
        hero_subhead: heroSubhead || null,
        hero_cta_label: heroCtaLabel || null,
        hero_cta_href: heroCtaHref || null,
      }

      const res = await apiRequest<{ success: boolean }>('/api/company/settings', {
        method: 'PUT',
        body: {
          storefrontTheme: themePayload,
          storefrontAnnouncementBar: announcementBar || null,
        },
      })

      if (res.success) {
        setSuccess(true)
        setLogoFile(null)
        setLogoRemoved(false)
        await mutate('company-settings')
        if (onSaved) onSaved()
        setTimeout(() => setSuccess(false), 4000)
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save brand customization')
    } finally {
      setSaving(false)
    }
  }

  // Active font and radius styles for preview
  const selectedFont = FONT_OPTIONS.find((f) => f.value === fontFamily)
  const selectedRadius = RADIUS_OPTIONS.find((r) => r.value === borderRadius)

  const activeFontFamily = selectedFont?.fontFamily || "'Inter', system-ui, sans-serif"
  const activeRadius = selectedRadius?.radius || '12px'

  return (
    <div className="space-y-8">
      {/* Header and Controls */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-xl font-semibold tracking-tight">Brand &amp; Appearance Customization</h2>
            <Badge variant="outline" className="gap-1 border-primary/30 text-primary">
              <Sparkles className="h-3 w-3" /> Live
            </Badge>
          </div>
          <p className="text-sm text-muted-foreground mt-0.5">
            Customize logos, color palettes, fonts, and styling across your Storefront, Link-in-Bio, and Web Chatbot.
          </p>
        </div>

        <div className="flex items-center gap-2">
          {storeSlug && (
            <Button variant="outline" size="sm" asChild className="gap-1.5">
              <a href={`/s/${storeSlug}`} target="_blank" rel="noreferrer">
                <ExternalLink className="h-3.5 w-3.5" /> View Live Store
              </a>
            </Button>
          )}
          <Button onClick={handleSave} disabled={saving} className="gap-2">
            {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
            Save Brand Settings
          </Button>
        </div>
      </div>

      {error && (
        <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
          {error}
        </div>
      )}

      {success && (
        <div className="rounded-lg border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm text-emerald-600 font-medium flex items-center gap-2">
          <Check className="h-4 w-4" /> Brand customization saved successfully! All customer surfaces have been updated.
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        {/* Left Column: Customization Controls (7 cols) */}
        <div className="lg:col-span-7 space-y-6">
          {/* Logo & Brand Identity */}
          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center gap-2">
                <Store className="h-5 w-5 text-muted-foreground" />
                <div>
                  <CardTitle className="text-base">Brand Logo</CardTitle>
                  <CardDescription>Upload your brand logo for headers, invoices, bio links, and chat</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex flex-col sm:flex-row items-center gap-5 p-4 rounded-xl border bg-muted/20">
                <div className="relative shrink-0 flex items-center justify-center h-20 w-20 rounded-2xl border-2 border-dashed border-border/80 bg-background overflow-hidden shadow-sm">
                  {logoUrl ? (
                    <img src={logoUrl} alt={businessName} className="h-full w-full object-contain p-1" />
                  ) : (
                    <div
                      className="flex h-full w-full items-center justify-center font-bold text-2xl text-white select-none transition-colors"
                      style={{ background: primaryColor }}
                    >
                      {businessName?.charAt(0)?.toUpperCase() || 'B'}
                    </div>
                  )}
                </div>

                <div className="space-y-2 flex-1 text-center sm:text-left">
                  <div className="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => fileInputRef.current?.click()}
                      className="gap-1.5"
                    >
                      <Upload className="h-4 w-4" />
                      {logoUrl ? 'Replace Logo' : 'Upload Logo'}
                    </Button>
                    {logoUrl && (
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={handleRemoveLogo}
                        className="text-destructive hover:text-destructive gap-1.5"
                      >
                        <Trash2 className="h-4 w-4" /> Remove
                      </Button>
                    )}
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Recommended: PNG or SVG with transparent background (min 200×200px, max 2MB).
                  </p>
                  <input
                    type="file"
                    ref={fileInputRef}
                    onChange={handleLogoChange}
                    accept="image/png,image/jpeg,image/svg+xml,image/webp"
                    className="hidden"
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Color Palette Studio */}
          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center gap-2">
                <Palette className="h-5 w-5 text-muted-foreground" />
                <div>
                  <CardTitle className="text-base">Color Palette &amp; Themes</CardTitle>
                  <CardDescription>Define your primary brand colors or pick from curated palettes</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-6">
              {/* Primary & Accent Inputs */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="primaryColor" className="text-sm font-medium">
                    Primary Brand Color
                  </Label>
                  <div className="flex items-center gap-2">
                    <div className="relative h-10 w-12 shrink-0 rounded-lg border overflow-hidden shadow-xs">
                      <input
                        type="color"
                        id="primaryColorPicker"
                        value={primaryColor}
                        onChange={(e) => setPrimaryColor(e.target.value)}
                        className="absolute -inset-2 h-14 w-16 cursor-pointer border-0 p-0"
                      />
                    </div>
                    <Input
                      id="primaryColor"
                      value={primaryColor}
                      onChange={(e) => setPrimaryColor(e.target.value)}
                      placeholder="#2563eb"
                      className="font-mono text-sm"
                    />
                  </div>
                  <p className="text-xs text-muted-foreground">Used on primary buttons, headers, accents &amp; CTAs.</p>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="accentColor" className="text-sm font-medium">
                    Secondary / Accent Color
                  </Label>
                  <div className="flex items-center gap-2">
                    <div className="relative h-10 w-12 shrink-0 rounded-lg border overflow-hidden shadow-xs">
                      <input
                        type="color"
                        id="accentColorPicker"
                        value={accentColor}
                        onChange={(e) => setAccentColor(e.target.value)}
                        className="absolute -inset-2 h-14 w-16 cursor-pointer border-0 p-0"
                      />
                    </div>
                    <Input
                      id="accentColor"
                      value={accentColor}
                      onChange={(e) => setAccentColor(e.target.value)}
                      placeholder="#3b82f6"
                      className="font-mono text-sm"
                    />
                  </div>
                  <p className="text-xs text-muted-foreground">Used on highlights, banners, badges &amp; secondary links.</p>
                </div>
              </div>

              {/* 1-Click Curated Presets */}
              <div className="space-y-2.5 pt-2">
                <div className="flex items-center justify-between">
                  <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    1-Click Brand Presets
                  </Label>
                  <span className="text-xs text-muted-foreground">Click to apply</span>
                </div>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                  {COLOR_PRESETS.map((preset) => {
                    const isSelected =
                      primaryColor.toLowerCase() === preset.primary.toLowerCase() &&
                      accentColor.toLowerCase() === preset.accent.toLowerCase()

                    return (
                      <button
                        key={preset.name}
                        type="button"
                        onClick={() => applyPreset(preset)}
                        className={`group relative flex flex-col items-start gap-1.5 p-3 rounded-xl border text-left transition-all hover:shadow-xs ${
                          isSelected
                            ? 'border-primary bg-primary/5 ring-1 ring-primary'
                            : 'border-border/70 hover:border-border hover:bg-muted/40'
                        }`}
                      >
                        <div className="flex items-center gap-1.5">
                          <div
                            className="h-4 w-4 rounded-full shadow-xs ring-1 ring-black/10 shrink-0"
                            style={{ background: preset.primary }}
                          />
                          <div
                            className="h-3 w-3 rounded-full shadow-xs ring-1 ring-black/10 shrink-0 -ml-1"
                            style={{ background: preset.accent }}
                          />
                          <span className="text-xs font-medium truncate">{preset.name}</span>
                          {isSelected && <Check className="h-3 w-3 text-primary ml-auto shrink-0" />}
                        </div>
                        <span className="text-[11px] text-muted-foreground leading-tight line-clamp-1">
                          {preset.description}
                        </span>
                      </button>
                    )
                  })}
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Typography & Shape Geometry */}
          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center gap-2">
                <Type className="h-5 w-5 text-muted-foreground" />
                <div>
                  <CardTitle className="text-base">Typography &amp; Button Style</CardTitle>
                  <CardDescription>Set the typeface and component border radius for your storefront</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-6">
              {/* Font Family Selection */}
              <div className="space-y-2.5">
                <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                  Font Family
                </Label>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                  {FONT_OPTIONS.map((font) => (
                    <button
                      key={font.value}
                      type="button"
                      onClick={() => setFontFamily(font.value)}
                      className={`flex items-center justify-between p-3 rounded-xl border text-left transition-all ${
                        fontFamily === font.value
                          ? 'border-primary bg-primary/5 ring-1 ring-primary'
                          : 'border-border/70 hover:border-border hover:bg-muted/40'
                      }`}
                    >
                      <div className="space-y-0.5">
                        <p className="text-xs font-semibold">{font.label}</p>
                        <p className="text-xs text-muted-foreground" style={{ fontFamily: font.fontFamily }}>
                          The quick brown fox jumps
                        </p>
                      </div>
                      {fontFamily === font.value && <Check className="h-4 w-4 text-primary shrink-0 ml-2" />}
                    </button>
                  ))}
                </div>
              </div>

              {/* Corner Radius / Geometry */}
              <div className="space-y-2.5">
                <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                  Button &amp; Card Corners
                </Label>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                  {RADIUS_OPTIONS.map((radius) => (
                    <button
                      key={radius.value}
                      type="button"
                      onClick={() => setBorderRadius(radius.value)}
                      className={`flex flex-col items-center gap-2 p-3 border text-center transition-all ${radius.className} ${
                        borderRadius === radius.value
                          ? 'border-primary bg-primary/5 ring-1 ring-primary'
                          : 'border-border/70 hover:border-border hover:bg-muted/40'
                      }`}
                    >
                      <div
                        className={`h-6 w-12 border-2 border-primary/60 bg-primary/20 ${radius.className}`}
                      />
                      <span className="text-xs font-medium">{radius.label}</span>
                    </button>
                  ))}
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Announcement Bar & Custom Colors */}
          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center gap-2">
                <Sparkles className="h-5 w-5 text-muted-foreground" />
                <div>
                  <CardTitle className="text-base">Storefront Announcement Banner</CardTitle>
                  <CardDescription>Top sales messages, offers, or notice across all store pages</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="announcementBar">Top Announcement Banner Text</Label>
                <Input
                  id="announcementBar"
                  value={announcementBar}
                  onChange={(e) => setAnnouncementBar(e.target.value)}
                  placeholder="e.g. 🎉 Free express delivery on all orders over $50 this weekend!"
                  maxLength={200}
                />
                <p className="text-xs text-muted-foreground">
                  Displays across your public store header. Leave empty to hide banner.
                </p>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                <div className="space-y-2">
                  <Label htmlFor="announcementBg" className="text-xs">Banner Background (Optional)</Label>
                  <div className="flex items-center gap-2">
                    <div className="relative h-9 w-10 shrink-0 rounded-lg border overflow-hidden shadow-xs">
                      <input
                        type="color"
                        value={announcementBg || accentColor}
                        onChange={(e) => setAnnouncementBg(e.target.value)}
                        className="absolute -inset-2 h-14 w-14 cursor-pointer border-0 p-0"
                      />
                    </div>
                    <Input
                      id="announcementBg"
                      value={announcementBg}
                      onChange={(e) => setAnnouncementBg(e.target.value)}
                      placeholder={accentColor}
                      className="font-mono text-xs h-9"
                    />
                  </div>
                  <p className="text-[11px] text-muted-foreground">Defaults to accent color if empty.</p>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="announcementText" className="text-xs">Banner Text Color (Optional)</Label>
                  <div className="flex items-center gap-2">
                    <div className="relative h-9 w-10 shrink-0 rounded-lg border overflow-hidden shadow-xs">
                      <input
                        type="color"
                        value={announcementText || '#ffffff'}
                        onChange={(e) => setAnnouncementText(e.target.value)}
                        className="absolute -inset-2 h-14 w-14 cursor-pointer border-0 p-0"
                      />
                    </div>
                    <Input
                      id="announcementText"
                      value={announcementText}
                      onChange={(e) => setAnnouncementText(e.target.value)}
                      placeholder="#ffffff"
                      className="font-mono text-xs h-9"
                    />
                  </div>
                  <p className="text-[11px] text-muted-foreground">Defaults to crisp white.</p>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Promotional Hero Banner & Floating CTA */}
          <Card>
            <CardHeader className="pb-4">
              <div className="flex items-center gap-2">
                <Store className="h-5 w-5 text-muted-foreground" />
                <div>
                  <CardTitle className="text-base">Promotional Hero &amp; Actions</CardTitle>
                  <CardDescription>Featured hero section and floating customer action buttons</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <label className="flex items-center gap-2 text-sm font-medium">
                <input
                  type="checkbox"
                  checked={heroEnabled}
                  onChange={(e) => setHeroEnabled(e.target.checked)}
                  className="h-4 w-4 rounded border-input"
                />
                Enable promotional hero banner on store homepage
              </label>

              {heroEnabled && (
                <div className="space-y-3 rounded-xl border bg-muted/20 p-3.5">
                  <div className="space-y-1.5">
                    <Label className="text-xs">Hero Headline</Label>
                    <Input
                      value={heroHeadline}
                      onChange={(e) => setHeroHeadline(e.target.value)}
                      placeholder={`Welcome to ${businessName}`}
                      maxLength={120}
                      className="h-8 text-xs"
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label className="text-xs">Hero Subtitle / Description</Label>
                    <Input
                      value={heroSubhead}
                      onChange={(e) => setHeroSubhead(e.target.value)}
                      placeholder="Browse our new collection and enjoy fast WhatsApp ordering & instant checkout."
                      maxLength={255}
                      className="h-8 text-xs"
                    />
                  </div>
                  <div className="grid grid-cols-2 gap-2">
                    <div className="space-y-1.5">
                      <Label className="text-xs">CTA Button Label</Label>
                      <Input
                        value={heroCtaLabel}
                        onChange={(e) => setHeroCtaLabel(e.target.value)}
                        placeholder="Shop Now"
                        maxLength={64}
                        className="h-8 text-xs"
                      />
                    </div>
                    <div className="space-y-1.5">
                      <Label className="text-xs">CTA Button Target</Label>
                      <Input
                        value={heroCtaHref}
                        onChange={(e) => setHeroCtaHref(e.target.value)}
                        placeholder="#catalog"
                        maxLength={255}
                        className="h-8 text-xs"
                      />
                    </div>
                  </div>
                </div>
              )}

              <div className="space-y-2 pt-2 border-t">
                <Label htmlFor="whatsappBtnText">Custom WhatsApp Floating Chat Button Text</Label>
                <Input
                  id="whatsappBtnText"
                  value={whatsappBtnText}
                  onChange={(e) => setWhatsappBtnText(e.target.value)}
                  placeholder="Chat on WhatsApp"
                  maxLength={64}
                />
                <p className="text-xs text-muted-foreground">
                  Customizes the floating green WhatsApp button text on public store pages. Defaults to "Chat on WhatsApp".
                </p>
              </div>

              <div className="space-y-2 pt-2 border-t">
                <Label htmlFor="footerText">Custom Footer Copyright / Tagline</Label>
                <Input
                  id="footerText"
                  value={footerText}
                  onChange={(e) => setFooterText(e.target.value)}
                  placeholder={`© ${new Date().getFullYear()} ${businessName}. All rights reserved.`}
                  maxLength={255}
                />
                <p className="text-xs text-muted-foreground">
                  Shown in the footer across public storefront, product, cart, and checkout pages.
                </p>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Right Column: Real-Time Live Preview (5 cols) */}
        <div className="lg:col-span-5 sticky top-6 space-y-4">
          <Card className="overflow-hidden border-2 shadow-md">
            <CardHeader className="bg-muted/40 pb-3 border-b">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Eye className="h-4 w-4 text-primary" />
                  <CardTitle className="text-sm font-semibold">Real-Time Brand Preview</CardTitle>
                </div>
                <div className="flex items-center rounded-lg border bg-background p-0.5">
                  <button
                    type="button"
                    onClick={() => setPreviewTab('storefront')}
                    className={`px-2.5 py-1 text-xs font-medium rounded-md transition-colors flex items-center gap-1 ${
                      previewTab === 'storefront'
                        ? 'bg-primary text-primary-foreground shadow-xs'
                        : 'text-muted-foreground hover:text-foreground'
                    }`}
                  >
                    <Store className="h-3 w-3" /> Store
                  </button>
                  <button
                    type="button"
                    onClick={() => setPreviewTab('bio')}
                    className={`px-2.5 py-1 text-xs font-medium rounded-md transition-colors flex items-center gap-1 ${
                      previewTab === 'bio'
                        ? 'bg-primary text-primary-foreground shadow-xs'
                        : 'text-muted-foreground hover:text-foreground'
                    }`}
                  >
                    <Smartphone className="h-3 w-3" /> Bio
                  </button>
                  <button
                    type="button"
                    onClick={() => setPreviewTab('widget')}
                    className={`px-2.5 py-1 text-xs font-medium rounded-md transition-colors flex items-center gap-1 ${
                      previewTab === 'widget'
                        ? 'bg-primary text-primary-foreground shadow-xs'
                        : 'text-muted-foreground hover:text-foreground'
                    }`}
                  >
                    <MessageSquare className="h-3 w-3" /> Widget
                  </button>
                </div>
              </div>
            </CardHeader>

            <CardContent className="p-0">
              {/* Storefront Mockup Preview */}
              {previewTab === 'storefront' && (
                <div
                  className="p-4 space-y-3 bg-white text-slate-900 transition-all text-xs"
                  style={{ fontFamily: activeFontFamily }}
                >
                  {/* Announcement Banner */}
                  {announcementBar && (
                    <div
                      className="px-3 py-1.5 text-center text-[11px] font-medium shadow-xs"
                      style={{
                        background: announcementBg || accentColor,
                        color: announcementText || '#ffffff',
                        borderRadius: activeRadius,
                      }}
                    >
                      {announcementBar}
                    </div>
                  )}

                  {/* Store Header Mockup */}
                  <div className="flex items-center justify-between border-b pb-3 pt-1">
                    <div className="flex items-center gap-2">
                      {logoUrl ? (
                        <img
                          src={logoUrl}
                          alt="Logo"
                          className="h-7 w-7 object-contain"
                          style={{ borderRadius: activeRadius }}
                        />
                      ) : (
                        <div
                          className="flex h-7 w-7 items-center justify-center font-bold text-xs text-white"
                          style={{ background: primaryColor, borderRadius: activeRadius }}
                        >
                          {businessName.charAt(0)}
                        </div>
                      )}
                      <span className="font-bold text-sm tracking-tight">{businessName}</span>
                    </div>

                    <button
                      type="button"
                      className="px-2.5 py-1 text-[11px] font-semibold border flex items-center gap-1 shadow-xs"
                      style={{
                        borderColor: primaryColor,
                        color: primaryColor,
                        borderRadius: activeRadius,
                      }}
                    >
                      <ShoppingBag className="h-3 w-3" /> Cart (1)
                    </button>
                  </div>

                  {/* Hero Banner Mockup if enabled */}
                  {heroEnabled && (
                    <div
                      className="p-4 rounded-xl text-white space-y-1.5 shadow-sm"
                      style={{ background: `linear-gradient(135deg, ${primaryColor}, ${accentColor})`, borderRadius: activeRadius }}
                    >
                      <p className="font-extrabold text-sm">{heroHeadline || `Welcome to ${businessName}`}</p>
                      <p className="text-[11px] text-white/80 leading-relaxed">
                        {heroSubhead || 'Explore our featured products and order online.'}
                      </p>
                      <div
                        className="inline-block px-3 py-1 text-[10px] font-bold bg-white text-slate-900 shadow-xs"
                        style={{ borderRadius: activeRadius }}
                      >
                        {heroCtaLabel || 'Shop Now'}
                      </div>
                    </div>
                  )}

                  {/* Hero / Catalog Search mockup */}
                  <div className="p-3 bg-slate-50 border rounded-xl space-y-2">
                    <p className="text-xs font-semibold text-slate-800">Featured Products</p>
                    <div className="grid grid-cols-2 gap-2.5">
                      {/* Product Card 1 */}
                      <div className="border rounded-xl bg-white p-2 space-y-2 shadow-xs">
                        <div className="h-20 w-full bg-slate-100 rounded-lg flex items-center justify-center text-slate-400 text-[10px]">
                          Product Photo
                        </div>
                        <div className="space-y-1">
                          <p className="font-medium text-xs truncate">Signature Item</p>
                          <p className="font-bold text-xs" style={{ color: primaryColor }}>
                            $24.00
                          </p>
                        </div>
                        <button
                          type="button"
                          className="w-full py-1 text-[10px] font-semibold text-white shadow-xs transition-opacity hover:opacity-90"
                          style={{ background: primaryColor, borderRadius: activeRadius }}
                        >
                          Add to Cart
                        </button>
                      </div>

                      {/* Product Card 2 */}
                      <div className="border rounded-xl bg-white p-2 space-y-2 shadow-xs">
                        <div className="h-20 w-full bg-slate-100 rounded-lg flex items-center justify-center text-slate-400 text-[10px]">
                          Product Photo
                        </div>
                        <div className="space-y-1">
                          <p className="font-medium text-xs truncate">Special Offer</p>
                          <p className="font-bold text-xs" style={{ color: primaryColor }}>
                            $45.00
                          </p>
                        </div>
                        <button
                          type="button"
                          className="w-full py-1 text-[10px] font-semibold text-white shadow-xs transition-opacity hover:opacity-90"
                          style={{ background: primaryColor, borderRadius: activeRadius }}
                        >
                          Add to Cart
                        </button>
                      </div>
                    </div>
                  </div>

                  {/* Floating WhatsApp preview */}
                  <div className="flex justify-end pt-1">
                    <div
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold text-white shadow-sm"
                      style={{ background: '#128C7E', borderRadius: '9999px' }}
                    >
                      <MessageSquare className="h-3 w-3" /> {whatsappBtnText || 'Chat on WhatsApp'}
                    </div>
                  </div>

                  {/* Footer */}
                  <div className="pt-2 text-center text-[10px] text-muted-foreground border-t">
                    {footerText || `Powered by ${businessName}`}
                  </div>
                </div>
              )}

              {/* Link-in-Bio Mockup Preview */}
              {previewTab === 'bio' && (
                <div
                  className="p-6 bg-gradient-to-b from-slate-50 to-white text-center space-y-4"
                  style={{ fontFamily: activeFontFamily }}
                >
                  <div className="mx-auto flex h-16 w-16 items-center justify-center overflow-hidden border-2 shadow-sm" style={{ borderRadius: activeRadius }}>
                    {logoUrl ? (
                      <img src={logoUrl} alt="Logo" className="h-full w-full object-contain" />
                    ) : (
                      <div
                        className="flex h-full w-full items-center justify-center font-bold text-xl text-white"
                        style={{ background: primaryColor }}
                      >
                        {businessName.charAt(0)}
                      </div>
                    )}
                  </div>

                  <div className="space-y-0.5">
                    <h3 className="font-bold text-sm text-slate-900">{businessName}</h3>
                    <p className="text-[11px] text-slate-500">Official catalog, ordering &amp; links</p>
                  </div>

                  <div className="space-y-2 max-w-xs mx-auto">
                    <div
                      className="p-2.5 text-xs font-semibold text-white shadow-xs flex items-center justify-center gap-2"
                      style={{ background: primaryColor, borderRadius: activeRadius }}
                    >
                      <Store className="h-3.5 w-3.5" /> Visit Online Store
                    </div>

                    <div
                      className="p-2.5 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-300 shadow-xs flex items-center justify-center gap-2"
                      style={{ borderRadius: activeRadius }}
                    >
                      <MessageSquare className="h-3.5 w-3.5 text-emerald-600" /> Order on WhatsApp
                    </div>

                    <div
                      className="p-2.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 shadow-xs"
                      style={{ borderRadius: activeRadius }}
                    >
                      Special Promotions &amp; Discounts
                    </div>
                  </div>
                </div>
              )}

              {/* Chatbot Widget Preview */}
              {previewTab === 'widget' && (
                <div
                  className="p-4 bg-slate-100 flex flex-col items-end gap-3 min-h-[300px] justify-end"
                  style={{ fontFamily: activeFontFamily }}
                >
                  {/* Chat Panel Mockup */}
                  <div
                    className="w-full max-w-[280px] bg-white border border-slate-200 shadow-lg overflow-hidden space-y-2 text-xs"
                    style={{ borderRadius: activeRadius }}
                  >
                    {/* Header */}
                    <div
                      className="p-3 text-white font-semibold flex items-center gap-2 shadow-xs"
                      style={{ background: primaryColor }}
                    >
                      {logoUrl ? (
                        <img src={logoUrl} alt="Logo" className="h-5 w-5 rounded-full object-cover bg-white" />
                      ) : (
                        <div className="h-5 w-5 rounded-full bg-white/20 flex items-center justify-center text-[10px] font-bold">
                          {businessName.charAt(0)}
                        </div>
                      )}
                      <span className="truncate">{businessName} Support</span>
                    </div>

                    {/* Chat Messages */}
                    <div className="p-3 space-y-2 bg-slate-50/50 min-h-[120px]">
                      <div
                        className="bg-white border text-slate-800 p-2 text-[11px] shadow-2xs max-w-[85%]"
                        style={{ borderRadius: activeRadius }}
                      >
                        Hello! Welcome to {businessName}. How can we assist you today?
                      </div>

                      <div
                        className="text-white p-2 text-[11px] shadow-2xs max-w-[85%] ml-auto"
                        style={{ background: primaryColor, borderRadius: activeRadius }}
                      >
                        I want to view your top products
                      </div>
                    </div>

                    {/* Input Mockup */}
                    <div className="p-2 border-t flex gap-1 bg-white">
                      <div className="flex-1 bg-slate-100 rounded-md px-2 py-1 text-[11px] text-slate-400">
                        Type a message...
                      </div>
                      <div
                        className="px-2 py-1 text-white font-medium text-[10px] flex items-center justify-center"
                        style={{ background: primaryColor, borderRadius: activeRadius }}
                      >
                        Send
                      </div>
                    </div>
                  </div>

                  {/* Launcher Bubble */}
                  <div
                    className="h-11 w-11 rounded-full text-white shadow-lg flex items-center justify-center text-lg font-bold"
                    style={{ background: primaryColor }}
                  >
                    💬
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}
