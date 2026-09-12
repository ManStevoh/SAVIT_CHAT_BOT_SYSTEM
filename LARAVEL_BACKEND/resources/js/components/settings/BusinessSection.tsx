"use client"

import { useEffect, useMemo, useState } from "react"
import { ShoppingBag, CalendarCheck, UtensilsCrossed, LayoutGrid } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Switch } from "@/components/ui/switch"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Label } from "@/components/ui/label"
import { cn } from "@/lib/utils"
import { useCompanySettings } from "@/lib/api-hooks"
import { updateSettings } from "@/lib/api-actions"
import { useSWRConfig } from "swr"
import { getTimezoneGroups, getTimezoneOptions } from "@/lib/timezones"
import {
  CATALOG_CURRENCY_OPTIONS,
  normalizeCurrencyCode,
  pairedDecimalForThousands,
  formatCurrencyAmount,
} from "@/lib/format-currency"
import { SettingSection, SettingRow, SaveBar } from "./shared"

type BusinessMode = "retail" | "services" | "restaurant" | "hybrid"
type Industry = "retail" | "restaurant" | "services" | "other"

const PRESETS: {
  id: BusinessMode
  title: string
  desc: string
  icon: typeof ShoppingBag
  apply: { mode: BusinessMode; catalog: boolean; bookings: boolean; dineIn: boolean }
}[] = [
  {
    id: "retail",
    title: "Retail",
    desc: "Sell products online",
    icon: ShoppingBag,
    apply: { mode: "retail", catalog: true, bookings: false, dineIn: false },
  },
  {
    id: "services",
    title: "Services",
    desc: "Take appointments",
    icon: CalendarCheck,
    apply: { mode: "services", catalog: true, bookings: true, dineIn: false },
  },
  {
    id: "restaurant",
    title: "Restaurant",
    desc: "Dine-in QR ordering",
    icon: UtensilsCrossed,
    apply: { mode: "restaurant", catalog: true, bookings: false, dineIn: true },
  },
  {
    id: "hybrid",
    title: "Everything",
    desc: "All sales channels",
    icon: LayoutGrid,
    apply: { mode: "hybrid", catalog: true, bookings: true, dineIn: true },
  },
]

export function BusinessSection() {
  const { mutate } = useSWRConfig()
  const { data: settings } = useCompanySettings()

  const [businessName, setBusinessName] = useState("")
  const [industry, setIndustry] = useState<Industry>("other")
  const [businessMode, setBusinessMode] = useState<BusinessMode>("hybrid")
  const [enableCatalog, setEnableCatalog] = useState(true)
  const [enableBookings, setEnableBookings] = useState(true)
  const [enableDineIn, setEnableDineIn] = useState(false)
  const [email, setEmail] = useState("")
  const [phone, setPhone] = useState("")
  const [address, setAddress] = useState("")
  const [timezone, setTimezone] = useState("UTC")
  const [displayCurrency, setDisplayCurrency] = useState("KES")
  const [currencySymbol, setCurrencySymbol] = useState("")
  const [thousandsSeparator, setThousandsSeparator] = useState(",")
  const [decimalSeparator, setDecimalSeparator] = useState(".")
  const [retentionDays, setRetentionDays] = useState("")

  const [saving, setSaving] = useState(false)
  const [saved, setSaved] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!settings) return
    if (settings.companyName != null) setBusinessName(settings.companyName)
    if (settings.industry) setIndustry(settings.industry)
    if (settings.businessMode) setBusinessMode(settings.businessMode)
    if (settings.enableProductsCatalog != null) setEnableCatalog(settings.enableProductsCatalog)
    if (settings.enableBookings != null) setEnableBookings(settings.enableBookings)
    if (settings.enableDineIn != null) setEnableDineIn(settings.enableDineIn)
    if (settings.email != null) setEmail(settings.email)
    if (settings.phone != null) setPhone(settings.phone)
    if (settings.address != null) setAddress(settings.address)
    if (settings.displayCurrency) setDisplayCurrency(normalizeCurrencyCode(settings.displayCurrency))
    if (settings.currencySymbol != null) setCurrencySymbol(String(settings.currencySymbol))
    if (settings.thousandsSeparator) setThousandsSeparator(settings.thousandsSeparator)
    if (settings.decimalSeparator) setDecimalSeparator(settings.decimalSeparator)
    if (settings.timezone?.trim()) setTimezone(String(settings.timezone).trim())
    setRetentionDays(settings.attributionRetentionDays != null ? String(settings.attributionRetentionDays) : "")
  }, [settings])

  const timezoneGroups = useMemo(() => {
    const groups = getTimezoneGroups()
    const valid = new Set(getTimezoneOptions().map((o) => o.value))
    if (timezone && !valid.has(timezone)) {
      return [
        { label: "Saved timezone", options: [{ value: timezone, label: timezone.replace(/_/g, " "), region: "Other" }] },
        ...groups,
      ]
    }
    return groups
  }, [timezone])

  const currencyOptions = useMemo(() => {
    if (displayCurrency && !CATALOG_CURRENCY_OPTIONS.some((o) => o.code === displayCurrency)) {
      return [{ code: displayCurrency, label: `${displayCurrency} (current)` }, ...CATALOG_CURRENCY_OPTIONS]
    }
    return CATALOG_CURRENCY_OPTIONS
  }, [displayCurrency])

  const applyPreset = (id: BusinessMode) => {
    const p = PRESETS.find((x) => x.id === id)
    if (!p) return
    setBusinessMode(p.apply.mode)
    setEnableCatalog(p.apply.catalog)
    setEnableBookings(p.apply.bookings)
    setEnableDineIn(p.apply.dineIn)
  }

  const save = async () => {
    setSaving(true)
    setError(null)
    setSaved(false)
    const result = await updateSettings({
      companyName: businessName,
      email,
      phone,
      address,
      displayCurrency: normalizeCurrencyCode(displayCurrency),
      currencySymbol: currencySymbol.trim() || null,
      thousandsSeparator,
      decimalSeparator,
      timezone,
      industry,
      businessMode,
      enableProductsCatalog: enableCatalog,
      enableBookings,
      enableDineIn,
      attributionRetentionDays: retentionDays.trim()
        ? Math.min(730, Math.max(30, parseInt(retentionDays, 10) || 365))
        : null,
    })
    setSaving(false)
    if (!result.success) {
      setError(result.message ?? "Couldn't save business settings.")
      return
    }
    setSaved(true)
    setTimeout(() => setSaved(false), 3000)
    mutate("company-settings")
  }

  return (
    <div className="space-y-4">
      <SettingSection title="Business profile" description="Your name, contact details, and how customers reach you.">
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="space-y-1.5">
            <Label htmlFor="biz-name">Business name</Label>
            <Input id="biz-name" value={businessName} onChange={(e) => setBusinessName(e.target.value)} placeholder="e.g. QuickBite Restaurant" />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="biz-industry">Industry</Label>
            <Select value={industry} onValueChange={(v) => setIndustry(v as Industry)}>
              <SelectTrigger id="biz-industry"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="retail">Retail</SelectItem>
                <SelectItem value="restaurant">Restaurant / Food</SelectItem>
                <SelectItem value="services">Services</SelectItem>
                <SelectItem value="other">Other</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="biz-email">Email</Label>
            <Input id="biz-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="hello@business.com" />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="biz-phone">Phone</Label>
            <Input id="biz-phone" value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="+254 7XX XXX XXX" />
          </div>
          <div className="space-y-1.5 sm:col-span-2">
            <Label htmlFor="biz-address">Address</Label>
            <Textarea id="biz-address" value={address} onChange={(e) => setAddress(e.target.value)} rows={2} placeholder="Street, city" />
          </div>
        </div>
      </SettingSection>

      <SettingSection
        title="How you sell"
        description="Pick a preset or fine-tune. This shapes your dashboard, checkout, and what the AI can do."
      >
        <div className="grid grid-cols-2 gap-2 lg:grid-cols-4">
          {PRESETS.map((p) => {
            const active = businessMode === p.id
            return (
              <button
                key={p.id}
                type="button"
                onClick={() => applyPreset(p.id)}
                className={cn(
                  "rounded-xl border p-3 text-left transition-colors",
                  active
                    ? "border-primary bg-primary/[0.06]"
                    : "border-border hover:border-muted-foreground/40 hover:bg-muted/40"
                )}
              >
                <p.icon className={cn("h-4 w-4", active ? "text-primary" : "text-muted-foreground")} />
                <p className="mt-2 text-sm font-semibold text-foreground">{p.title}</p>
                <p className="text-xs text-muted-foreground">{p.desc}</p>
              </button>
            )
          })}
        </div>
        <div className="divide-y divide-border rounded-xl border border-border">
          <div className="px-4 py-1">
            <SettingRow
              label="Product catalog"
              hint="Physical and digital goods"
              control={<Switch checked={enableCatalog} onCheckedChange={setEnableCatalog} />}
            />
          </div>
          <div className="px-4 py-1">
            <SettingRow
              label="Bookings"
              hint="Appointment scheduling"
              control={<Switch checked={enableBookings} onCheckedChange={setEnableBookings} />}
            />
          </div>
          <div className="px-4 py-1">
            <SettingRow
              label="Dine-in QR"
              hint="Table ordering and tabs"
              control={<Switch checked={enableDineIn} onCheckedChange={setEnableDineIn} />}
            />
          </div>
        </div>
      </SettingSection>

      <SettingSection title="Region & currency" description="Timezone for bookings and how prices display everywhere.">
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="space-y-1.5 sm:col-span-2">
            <Label>Timezone</Label>
            <Select value={timezone} onValueChange={setTimezone}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent className="max-h-72">
                {timezoneGroups.map((g) => (
                  <div key={g.label}>
                    <p className="px-2 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{g.label}</p>
                    {g.options.map((o) => (
                      <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                    ))}
                  </div>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label>Currency</Label>
            <Select value={displayCurrency} onValueChange={(v) => setDisplayCurrency(normalizeCurrencyCode(v))}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent className="max-h-72">
                {currencyOptions.map((o) => (
                  <SelectItem key={o.code} value={o.code}>{o.code} — {o.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label>Symbol override <span className="font-normal text-muted-foreground">(optional)</span></Label>
            <Input value={currencySymbol} onChange={(e) => setCurrencySymbol(e.target.value)} placeholder="e.g. KSh" maxLength={16} />
          </div>
          <div className="space-y-1.5">
            <Label>Thousands separator</Label>
            <Select
              value={thousandsSeparator}
              onValueChange={(v) => {
                setThousandsSeparator(v)
                setDecimalSeparator(pairedDecimalForThousands(v))
              }}
            >
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value=",">Comma (1,000.00)</SelectItem>
                <SelectItem value=".">Dot (1.000,00)</SelectItem>
                <SelectItem value=" ">Space (1 000,00)</SelectItem>
                <SelectItem value="'">Apostrophe (1'000.00)</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label>Decimal separator</Label>
            <Select value={decimalSeparator} onValueChange={setDecimalSeparator}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value=".">Dot (…00.50)</SelectItem>
                <SelectItem value=",">Comma (…00,50)</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
        <p className="rounded-lg bg-muted/50 px-3 py-2 text-[13px] text-muted-foreground">
          Preview:{" "}
          <span className="font-semibold text-foreground">
            {formatCurrencyAmount(1234567.89, normalizeCurrencyCode(displayCurrency), {
              symbol: currencySymbol.trim() || undefined,
              thousandsSeparator,
              decimalSeparator,
            })}
          </span>
        </p>
      </SettingSection>

      <SettingSection title="Advanced" description="Rarely needs changing.">
        <SettingRow
          label="Attribution data retention"
          hint="Days to keep marketing attribution (30–730). Empty uses the platform default."
          control={
            <Input
              type="number"
              min={30}
              max={730}
              value={retentionDays}
              onChange={(e) => setRetentionDays(e.target.value)}
              placeholder="Default"
              className="sm:w-36"
            />
          }
        />
        <SaveBar saving={saving} saved={saved} error={error} onSave={save} />
      </SettingSection>
    </div>
  )
}
