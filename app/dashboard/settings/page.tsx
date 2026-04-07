"use client"

import { useState, useEffect, useMemo } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Switch } from "@/components/ui/switch"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Building2, MessageSquare, Bot, Users, Bell, Plus, Trash2, Check, CreditCard } from "lucide-react"

function isMasked(val: unknown): boolean {
  return typeof val === "string" && val.startsWith("••••")
}

function mpesaSecretKey(field: "passkey" | "consumer_secret") {
  return `mpesa:${field}`
}
// API: GET /api/company/settings (useCompanySettings), PUT /api/company/settings (updateSettings)
import { useCompanySettings, useCompanyTeam, useWhatsAppNumbers } from "@/lib/api-hooks"
import { CATALOG_CURRENCY_OPTIONS, normalizeCurrencyCode } from "@/lib/format-currency"
import { useSWRConfig } from "swr"
import {
  updateSettings,
  connectWhatsApp,
  getWhatsAppStatus,
  disconnectWhatsApp,
  getWhatsAppEmbeddedConfig,
  completeWhatsAppEmbeddedSignup,
  type WhatsAppStatus,
} from "@/lib/api-actions"
import { getTimezoneGroups, getTimezoneOptions } from "@/lib/timezones"

declare global {
  interface Window {
    FB?: {
      init: (params: { appId: string; cookie?: boolean; xfbml?: boolean; version: string }) => void
      login: (
        callback: (response: { status?: string; authResponse?: { code?: string } }) => void,
        options?: Record<string, unknown>
      ) => void
    }
  }
}

export default function SettingsPage() {
  const { mutate } = useSWRConfig()
  const { data: settings } = useCompanySettings()
  const { data: teamMembers = [] } = useCompanyTeam()
  const { data: whatsappNumbers = [] } = useWhatsAppNumbers()
  const [activeTab, setActiveTab] = useState("profile")
  const [profileSaving, setProfileSaving] = useState(false)
  const [profileError, setProfileError] = useState<string | null>(null)
  const [profileSuccess, setProfileSuccess] = useState(false)
  const [businessName, setBusinessName] = useState("QuickBite Restaurant")
  const [email, setEmail] = useState("contact@quickbite.com")
  const [phone, setPhone] = useState("+1 555-0100")
  const [address, setAddress] = useState("123 Main Street, New York, NY 10001")
  const [displayCurrency, setDisplayCurrency] = useState("USD")
  const [timezone, setTimezone] = useState("UTC")

  const timezoneGroupsForSelect = useMemo(() => {
    const groups = getTimezoneGroups()
    const valid = new Set(getTimezoneOptions().map((o) => o.value))
    if (timezone && !valid.has(timezone)) {
      return [
        {
          label: "Saved timezone",
          options: [{ value: timezone, label: timezone.replace(/_/g, " "), region: "Other" }],
        },
        ...groups,
      ]
    }
    return groups
  }, [timezone])

  const catalogCurrencySelectOptions = useMemo(() => {
    const base = [...CATALOG_CURRENCY_OPTIONS]
    if (displayCurrency && !base.some((o) => o.code === displayCurrency)) {
      return [{ code: displayCurrency, label: `${displayCurrency} (current)` }, ...base]
    }
    return base
  }, [displayCurrency])

  const [waStatus, setWaStatus] = useState<WhatsAppStatus | null>(null)
  const [waLoading, setWaLoading] = useState(false)
  const [waConnectLoading, setWaConnectLoading] = useState(false)
  const [waPhoneNumberId, setWaPhoneNumberId] = useState("")
  const [waAccessToken, setWaAccessToken] = useState("")
  const [waDisplayNumber, setWaDisplayNumber] = useState("")
  const [waMessage, setWaMessage] = useState<string | null>(null)
  const [waEmbeddedLoading, setWaEmbeddedLoading] = useState(false)

  const loadWhatsAppStatus = async () => {
    setWaLoading(true)
    try {
      const s = await getWhatsAppStatus()
      setWaStatus(s)
    } finally {
      setWaLoading(false)
    }
  }

  const loadFacebookSdk = async (): Promise<void> => {
    if (typeof window === "undefined") return
    if (window.FB) return
    await new Promise<void>((resolve, reject) => {
      const existing = document.getElementById("facebook-jssdk") as HTMLScriptElement | null
      if (existing) {
        existing.addEventListener("load", () => resolve(), { once: true })
        existing.addEventListener("error", () => reject(new Error("Failed to load Facebook SDK")), { once: true })
        return
      }
      const script = document.createElement("script")
      script.id = "facebook-jssdk"
      script.src = "https://connect.facebook.net/en_US/sdk.js"
      script.async = true
      script.defer = true
      script.onload = () => resolve()
      script.onerror = () => reject(new Error("Failed to load Facebook SDK"))
      document.body.appendChild(script)
    })
  }

  const waitForEmbeddedSignupFinish = async (): Promise<{
    phoneNumberId?: string
    whatsappBusinessAccountId?: string
  } | null> => {
    if (typeof window === "undefined") return null
    return await new Promise((resolve) => {
      const timeout = window.setTimeout(() => {
        cleanup()
        resolve(null)
      }, 120000)
      const cleanup = () => {
        window.clearTimeout(timeout)
        window.removeEventListener("message", onMessage)
      }
      const onMessage = (event: MessageEvent) => {
        if (typeof event.origin !== "string" || !event.origin.includes("facebook.com")) return
        let payload: unknown = event.data
        if (typeof payload === "string") {
          try {
            payload = JSON.parse(payload)
          } catch {
            return
          }
        }
        if (!payload || typeof payload !== "object") return
        const obj = payload as Record<string, unknown>
        if (obj.type !== "WA_EMBEDDED_SIGNUP") return
        const data = (obj.data ?? {}) as Record<string, unknown>
        if (data.event !== "FINISH") return
        cleanup()
        resolve({
          phoneNumberId: typeof data.phone_number_id === "string" ? data.phone_number_id : undefined,
          whatsappBusinessAccountId: typeof data.waba_id === "string" ? data.waba_id : undefined,
        })
      }
      window.addEventListener("message", onMessage)
    })
  }

  useEffect(() => {
    if (activeTab === "whatsapp") loadWhatsAppStatus()
  }, [activeTab])

  const handleWhatsAppConnect = async (e: React.FormEvent) => {
    e.preventDefault()
    setWaMessage(null)
    setWaConnectLoading(true)
    const result = await connectWhatsApp({
      phoneNumberId: waPhoneNumberId.trim(),
      accessToken: waAccessToken.trim(),
      displayPhoneNumber: waDisplayNumber.trim() || undefined,
    })
    setWaConnectLoading(false)
    setWaMessage(result.message ?? (result.success ? "Connected." : "Failed."))
    if (result.success) {
      setWaAccessToken("")
      loadWhatsAppStatus()
    }
  }

  const handleWhatsAppDisconnect = async () => {
    setWaMessage(null)
    setWaLoading(true)
    const result = await disconnectWhatsApp()
    setWaMessage(result.message ?? (result.success ? "Disconnected." : "Failed."))
    loadWhatsAppStatus()
  }

  const handleEmbeddedSignup = async () => {
    setWaMessage(null)
    setWaEmbeddedLoading(true)
    try {
      const cfg = await getWhatsAppEmbeddedConfig()
      if (!cfg.enabled || !cfg.appId || !cfg.configId) {
        setWaMessage("Embedded signup is not enabled yet. Ask admin to configure Meta App ID and Config ID.")
        return
      }

      await loadFacebookSdk()
      if (!window.FB) {
        setWaMessage("Facebook SDK is unavailable. Refresh and try again.")
        return
      }

      window.FB.init({
        appId: cfg.appId,
        cookie: true,
        xfbml: false,
        version: cfg.graphVersion || "v21.0",
      })

      const finishPromise = waitForEmbeddedSignupFinish()
      const code = await new Promise<string | null>((resolve) => {
        window.FB?.login(
          (response) => {
            resolve(response?.authResponse?.code ?? null)
          },
          {
            config_id: cfg.configId,
            response_type: "code",
            override_default_response_type: true,
            extras: { setup: {} },
          }
        )
      })
      const finishData = await finishPromise

      if (!code && !finishData?.phoneNumberId) {
        setWaMessage("Signup was cancelled or no data was returned.")
        return
      }

      const result = await completeWhatsAppEmbeddedSignup({
        code: code ?? undefined,
        phoneNumberId: finishData?.phoneNumberId,
        whatsappBusinessAccountId: finishData?.whatsappBusinessAccountId,
      })
      setWaMessage(result.message ?? (result.success ? "WhatsApp connected via embedded signup." : "Failed to connect."))
      if (result.success) {
        await loadWhatsAppStatus()
      }
    } catch (e) {
      setWaMessage(e instanceof Error ? e.message : "Embedded signup failed.")
    } finally {
      setWaEmbeddedLoading(false)
    }
  }

  const [ordersCollectPaymentEnabled, setOrdersCollectPaymentEnabled] = useState(true)
  const [orderPaymentManualInstructions, setOrderPaymentManualInstructions] = useState('')
  const [ordersAcceptMpesa, setOrdersAcceptMpesa] = useState(false)
  const [ordersAcceptStripe, setOrdersAcceptStripe] = useState(false)
  const [orderPaymentsSaving, setOrderPaymentsSaving] = useState(false)
  const [orderPaymentsMessage, setOrderPaymentsMessage] = useState<string | null>(null)
  const [mpesaType, setMpesaType] = useState<'paybill' | 'till'>('paybill')
  const [mpesaShortcode, setMpesaShortcode] = useState('')
  const [mpesaPasskey, setMpesaPasskey] = useState('')
  const [mpesaConsumerKey, setMpesaConsumerKey] = useState('')
  const [mpesaConsumerSecret, setMpesaConsumerSecret] = useState('')
  const [mpesaEnv, setMpesaEnv] = useState<'sandbox' | 'production'>('sandbox')
  const [stripeSecret, setStripeSecret] = useState('')
  const [stripeCurrency, setStripeCurrency] = useState('usd')
  /** User clicked Replace on a masked M-Pesa / Stripe secret field */
  const [replacingMpesaSecret, setReplacingMpesaSecret] = useState<Record<string, boolean>>({})
  const [replacingStripeSecret, setReplacingStripeSecret] = useState(false)

  /** AI tab — persisted via PUT /api/company/settings (aiGreeting, aiTone, booleans). Model is platform-wide, not saved here. */
  const [aiGreeting, setAiGreeting] = useState(
    'You are a friendly and helpful customer service assistant for a restaurant. Be polite, professional, and helpful.'
  )
  const [aiTone, setAiTone] = useState('balanced')
  const [autoReplyEnabled, setAutoReplyEnabled] = useState(false)
  const [learnFromConversations, setLearnFromConversations] = useState(true)
  const [notificationsEnabled, setNotificationsEnabled] = useState(false)
  const [aiSaving, setAiSaving] = useState(false)
  const [aiMessage, setAiMessage] = useState<string | null>(null)

  // Load initial values from GET /api/company/settings when available
  useEffect(() => {
    if (settings) {
      if (settings.companyName != null) setBusinessName(settings.companyName)
      if (settings.email != null) setEmail(settings.email)
      if (settings.phone != null) setPhone(settings.phone)
      if (settings.address != null) setAddress(settings.address)
      if (settings.displayCurrency != null && settings.displayCurrency !== "") {
        setDisplayCurrency(normalizeCurrencyCode(settings.displayCurrency))
      }
      if (settings.timezone != null && String(settings.timezone).trim() !== "") {
        setTimezone(String(settings.timezone).trim())
      }
      if (settings.ordersCollectPaymentEnabled != null) setOrdersCollectPaymentEnabled(settings.ordersCollectPaymentEnabled)
      if (settings.orderPaymentManualInstructions != null) setOrderPaymentManualInstructions(settings.orderPaymentManualInstructions)
      if (settings.ordersAcceptMpesa != null) setOrdersAcceptMpesa(settings.ordersAcceptMpesa)
      if (settings.ordersAcceptStripe != null) setOrdersAcceptStripe(settings.ordersAcceptStripe)
      if (settings.aiGreeting != null && settings.aiGreeting.trim() !== '') setAiGreeting(settings.aiGreeting)
      if (settings.aiTone != null && settings.aiTone.trim() !== '') {
        const t = settings.aiTone.trim().toLowerCase()
        if (t === 'formal' || t === 'balanced' || t === 'casual') setAiTone(t)
      }
      if (settings.autoReplyEnabled != null) setAutoReplyEnabled(settings.autoReplyEnabled)
      if (settings.learnFromConversations != null) setLearnFromConversations(settings.learnFromConversations)
      if (settings.notificationsEnabled != null) setNotificationsEnabled(settings.notificationsEnabled)
      const mpc = settings.orderPaymentMpesaConfig
      if (mpc) {
        if (mpc.type === "till" || mpc.type === "paybill") setMpesaType(mpc.type)
        if (mpc.shortcode != null && mpc.shortcode !== "") setMpesaShortcode(mpc.shortcode)
        if (mpc.passkey != null && mpc.passkey !== "") setMpesaPasskey(mpc.passkey)
        setMpesaConsumerKey(mpc.consumer_key != null && mpc.consumer_key !== "" ? mpc.consumer_key : "")
        setMpesaConsumerSecret(
          mpc.consumer_secret != null && mpc.consumer_secret !== "" ? mpc.consumer_secret : ""
        )
        if (mpc.env === "production" || mpc.env === "sandbox") setMpesaEnv(mpc.env)
      } else if (settings.orderPaymentMpesaConfigured === false) {
        setMpesaShortcode("")
        setMpesaPasskey("")
        setMpesaConsumerKey("")
        setMpesaConsumerSecret("")
        setMpesaType("paybill")
        setMpesaEnv("sandbox")
      }
      const st = settings.orderPaymentStripeConfig
      if (st) {
        if (st.secret != null && st.secret !== "") setStripeSecret(st.secret)
        if (st.currency != null && st.currency !== "") setStripeCurrency(st.currency)
      } else if (settings.orderPaymentStripeConfigured === false) {
        setStripeSecret("")
        setStripeCurrency("usd")
      }
    }
  }, [settings])

  const handleAiSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setAiMessage(null)
    setAiSaving(true)
    const result = await updateSettings({
      aiGreeting: aiGreeting.trim(),
      aiTone: aiTone.trim(),
      autoReplyEnabled,
      learnFromConversations,
      notificationsEnabled,
    })
    setAiSaving(false)
    setAiMessage(result.success ? 'AI settings saved.' : (result.message ?? 'Failed to save.'))
    if (result.success) mutate('company-settings')
  }

  const handleProfileSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setProfileError(null)
    setProfileSuccess(false)
    setProfileSaving(true)
    const result = await updateSettings({
      companyName: businessName,
      email,
      phone,
      address,
      displayCurrency: normalizeCurrencyCode(displayCurrency),
      timezone,
    })
    setProfileSaving(false)
    if (!result.success) {
      setProfileError(result.message ?? "Failed to save")
      return
    }
    setProfileSuccess(true)
    mutate("company-settings")
  }

  const handleOrderPaymentsSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setOrderPaymentsMessage(null)
    setOrderPaymentsSaving(true)
    const payload: Parameters<typeof updateSettings>[0] = {
      ordersCollectPaymentEnabled,
      orderPaymentManualInstructions: orderPaymentManualInstructions.trim() || null,
      ordersAcceptMpesa,
      ordersAcceptStripe,
    }
    if (mpesaShortcode.trim()) {
      payload.orderPaymentMpesaConfig = {
        type: mpesaType,
        shortcode: mpesaShortcode.trim(),
        passkey: mpesaPasskey.trim(),
        consumer_key: mpesaConsumerKey.trim() || undefined,
        consumer_secret: mpesaConsumerSecret.trim() || undefined,
        env: mpesaEnv,
      }
    }
    if (stripeSecret.trim() || settings?.orderPaymentStripeConfigured) {
      payload.orderPaymentStripeConfig = {
        secret: stripeSecret.trim(),
        currency: stripeCurrency.trim() || "usd",
      }
    }
    const result = await updateSettings(payload)
    setOrderPaymentsSaving(false)
    setOrderPaymentsMessage(result.success ? 'Saved. Customers can now choose these payment methods when placing orders.' : (result.message ?? 'Failed to save.'))
    if (result.success) {
      setReplacingMpesaSecret({})
      setReplacingStripeSecret(false)
      mutate("company-settings")
    }
  }

  const handleClearMpesaConfig = async () => {
    setOrderPaymentsMessage(null)
    const result = await updateSettings({ orderPaymentMpesaConfig: null })
    setOrderPaymentsMessage(result.success ? 'M-Pesa config cleared. Platform default will be used.' : (result.message ?? 'Failed.'))
    if (result.success) {
      setMpesaShortcode("")
      setMpesaPasskey("")
      setMpesaConsumerKey("")
      setMpesaConsumerSecret("")
      setReplacingMpesaSecret({})
      mutate("company-settings")
    }
  }

  const handleClearStripeConfig = async () => {
    setOrderPaymentsMessage(null)
    const result = await updateSettings({ orderPaymentStripeConfig: null })
    setOrderPaymentsMessage(result.success ? 'Stripe config cleared. Platform default will be used.' : (result.message ?? 'Failed.'))
    if (result.success) {
      setStripeSecret("")
      setReplacingStripeSecret(false)
      mutate("company-settings")
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Settings</h1>
        <p className="text-muted-foreground">Manage your account and preferences</p>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
        <TabsList className="flex-wrap h-auto gap-2">
          <TabsTrigger value="profile" className="gap-2">
            <Building2 className="h-4 w-4" />
            Business Profile
          </TabsTrigger>
          <TabsTrigger value="whatsapp" className="gap-2">
            <MessageSquare className="h-4 w-4" />
            WhatsApp Setup
          </TabsTrigger>
          <TabsTrigger value="ai" className="gap-2">
            <Bot className="h-4 w-4" />
            AI Settings
          </TabsTrigger>
          <TabsTrigger value="team" className="gap-2">
            <Users className="h-4 w-4" />
            Staff Management
          </TabsTrigger>
          <TabsTrigger value="notifications" className="gap-2">
            <Bell className="h-4 w-4" />
            Notifications
          </TabsTrigger>
          <TabsTrigger value="order-payments" className="gap-2">
            <CreditCard className="h-4 w-4" />
            Order Payments
          </TabsTrigger>
        </TabsList>

        {/* Business Profile — API: PUT /api/company/settings (companyName, email, phone) */}
        <TabsContent value="profile">
          <Card>
            <CardHeader>
              <CardTitle>Business Profile</CardTitle>
              <CardDescription>Update your business information</CardDescription>
            </CardHeader>
            <CardContent>
              <form className="space-y-6" onSubmit={handleProfileSubmit}>
                {profileError && (
                  <div className="rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-2 text-sm text-destructive">
                    {profileError}
                  </div>
                )}
                {profileSuccess && (
                  <div className="rounded-lg border border-primary/50 bg-primary/10 px-4 py-2 text-sm text-primary">
                    Settings saved successfully.
                  </div>
                )}
                <FieldGroup>
                  <Field>
                    <FieldLabel htmlFor="businessName">Business Name</FieldLabel>
                    <Input id="businessName" value={businessName} onChange={(e) => setBusinessName(e.target.value)} />
                  </Field>

                  <div className="grid gap-4 md:grid-cols-2">
                    <Field>
                      <FieldLabel htmlFor="email">Email</FieldLabel>
                      <Input id="email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
                    </Field>
                    <Field>
                      <FieldLabel htmlFor="phone">Phone</FieldLabel>
                      <Input id="phone" value={phone} onChange={(e) => setPhone(e.target.value)} />
                    </Field>
                  </div>

                  <Field>
                    <FieldLabel htmlFor="address">Address</FieldLabel>
                    <Textarea id="address" value={address} onChange={(e) => setAddress(e.target.value)} rows={2} />
                  </Field>

                  <Field>
                    <FieldLabel htmlFor="displayCurrency">Catalog currency</FieldLabel>
                    <p className="text-sm text-muted-foreground mb-2">
                      Prices and totals use this currency in the dashboard, WhatsApp catalog, and AI replies (ISO 4217 code).
                    </p>
                    <Select value={displayCurrency} onValueChange={setDisplayCurrency}>
                      <SelectTrigger id="displayCurrency">
                        <SelectValue placeholder="Select currency" />
                      </SelectTrigger>
                      <SelectContent className="max-h-[280px]">
                        {catalogCurrencySelectOptions.map((o) => (
                          <SelectItem key={o.code} value={o.code}>
                            {o.code} — {o.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </Field>

                  <Field>
                    <FieldLabel htmlFor="timezone">Timezone</FieldLabel>
                    <p className="text-sm text-muted-foreground mb-2">
                      Used for business hours, away messages, and timestamps. Pick your region (e.g. Nairobi for East Africa Time, EAT).
                    </p>
                    <Select value={timezone} onValueChange={setTimezone}>
                      <SelectTrigger id="timezone" className="w-full max-w-md">
                        <SelectValue placeholder="Select timezone" />
                      </SelectTrigger>
                      <SelectContent className="max-h-[min(360px,70vh)]">
                        {timezoneGroupsForSelect.map((group) => (
                          <SelectGroup key={group.label}>
                            <SelectLabel>{group.label}</SelectLabel>
                            {group.options.map((opt) => (
                              <SelectItem key={opt.value} value={opt.value}>
                                {opt.label}
                              </SelectItem>
                            ))}
                          </SelectGroup>
                        ))}
                      </SelectContent>
                    </Select>
                  </Field>
                </FieldGroup>

                <Button type="submit" disabled={profileSaving}>{profileSaving ? "Saving..." : "Save Changes"}</Button>
              </form>
            </CardContent>
          </Card>
        </TabsContent>

        {/* WhatsApp Setup — Meta Cloud API */}
        <TabsContent value="whatsapp">
          <Card>
            <CardHeader>
              <CardTitle>WhatsApp Business (Meta Cloud API)</CardTitle>
              <CardDescription>Connect your WhatsApp Business number to receive and send messages. Get Phone Number ID and Access Token from Meta for Developers.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              {waLoading && !waStatus ? (
                <p className="text-sm text-muted-foreground">Loading status…</p>
              ) : waStatus?.connected ? (
                <div className="space-y-4">
                  <div className="flex items-center gap-2">
                    <Badge variant="default" className="gap-1">
                      <Check className="h-3 w-3" />
                      Connected
                    </Badge>
                    {waStatus.displayPhoneNumber && (
                      <span className="text-sm text-muted-foreground">{waStatus.displayPhoneNumber}</span>
                    )}
                    {waStatus.phoneNumberId && (
                      <span className="text-xs text-muted-foreground">ID: {waStatus.phoneNumberId}</span>
                    )}
                  </div>
                  <p className="text-sm text-muted-foreground">
                    Webhook URL (set in Meta App → WhatsApp → Configuration):{" "}
                    <code className="rounded bg-muted px-1">
                      {process.env.NEXT_PUBLIC_API_URL ?? "https://your-backend.com"}/api/whatsapp/webhook
                    </code>
                  </p>
                  {whatsappNumbers.length > 0 && (
                    <div className="rounded-lg border border-border p-3 space-y-2">
                      <p className="text-sm font-medium text-foreground">Connected numbers</p>
                      <ul className="text-sm text-muted-foreground space-y-1">
                        {whatsappNumbers.map((n) => (
                          <li key={n.id}>{n.displayPhoneNumber || n.phoneNumberId} {n.status !== "active" && `(${n.status})`}</li>
                        ))}
                      </ul>
                    </div>
                  )}
                  <Button variant="outline" onClick={handleWhatsAppDisconnect} disabled={waLoading}>
                    Disconnect WhatsApp
                  </Button>
                </div>
              ) : (
                <div className="space-y-5">
                  <div className="rounded-lg border border-border bg-muted/30 p-4 space-y-3">
                    <p className="text-sm font-medium text-foreground">Recommended: One-click Meta signup (OTP inside Meta)</p>
                    <p className="text-sm text-muted-foreground">
                      Click connect, sign into Facebook, select/create your WhatsApp Business account, then verify your number by OTP.
                      Once Meta confirms, your number is linked here automatically.
                    </p>
                    <Button type="button" onClick={handleEmbeddedSignup} disabled={waEmbeddedLoading}>
                      {waEmbeddedLoading ? "Opening Meta signup…" : "Connect with Facebook"}
                    </Button>
                  </div>

                  <div className="rounded-lg border border-border p-4 space-y-2">
                    <p className="text-sm font-medium text-foreground">Need help? Quick checklist</p>
                    <ul className="text-sm text-muted-foreground list-disc pl-5 space-y-1">
                      <li>Use a phone number that can receive SMS/voice OTP.</li>
                      <li>Do not use a number already connected to another WhatsApp API provider.</li>
                      <li>Finish all Meta popup steps without closing the window.</li>
                      <li>If popup blocks, allow popups for this site and retry.</li>
                    </ul>
                  </div>

                  <div className="pt-1">
                    <p className="text-sm font-medium text-foreground mb-3">Fallback: Manual connect</p>
                    <form onSubmit={handleWhatsAppConnect} className="space-y-4">
                      <Field>
                        <FieldLabel>Phone Number ID</FieldLabel>
                        <Input
                          placeholder="From Meta App → WhatsApp → API Setup"
                          value={waPhoneNumberId}
                          onChange={(e) => setWaPhoneNumberId(e.target.value)}
                          required
                        />
                      </Field>
                      <Field>
                        <FieldLabel>Access Token</FieldLabel>
                        <Input
                          type="password"
                          placeholder="Permanent token from Meta"
                          value={waAccessToken}
                          onChange={(e) => setWaAccessToken(e.target.value)}
                          required
                        />
                      </Field>
                      <Field>
                        <FieldLabel>Display phone (optional)</FieldLabel>
                        <Input
                          placeholder="e.g. +201234567890"
                          value={waDisplayNumber}
                          onChange={(e) => setWaDisplayNumber(e.target.value)}
                        />
                      </Field>
                      <Button type="submit" disabled={waConnectLoading}>
                        {waConnectLoading ? "Connecting…" : "Connect manually"}
                      </Button>
                    </form>
                  </div>
                </div>
              )}
              {waMessage && <p className="text-sm text-muted-foreground">{waMessage}</p>}
            </CardContent>
          </Card>
        </TabsContent>

        {/* AI Settings — PUT /api/company/settings */}
        <TabsContent value="ai">
          <Card>
            <CardHeader>
              <CardTitle>AI Configuration</CardTitle>
              <CardDescription>Configure your AI assistant behavior</CardDescription>
            </CardHeader>
            <CardContent>
              <form className="space-y-6" onSubmit={handleAiSubmit}>
                {aiMessage && (
                  <p className={`text-sm ${aiMessage.startsWith('AI settings saved') ? 'text-primary' : 'text-destructive'}`}>
                    {aiMessage}
                  </p>
                )}
                <Field>
                  <FieldLabel htmlFor="aiModel">AI Model</FieldLabel>
                  <Select value="platform" disabled>
                    <SelectTrigger id="aiModel">
                      <SelectValue placeholder="Platform default" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="platform">Platform default (OpenAI)</SelectItem>
                    </SelectContent>
                  </Select>
                  <p className="text-xs text-muted-foreground mt-1">
                    The chat model is set in platform admin settings, not per company.
                  </p>
                </Field>

                <Field>
                  <FieldLabel htmlFor="personality">AI Personality</FieldLabel>
                  <Textarea
                    id="personality"
                    value={aiGreeting}
                    onChange={(e) => setAiGreeting(e.target.value)}
                    rows={3}
                  />
                  <p className="text-xs text-muted-foreground mt-1">
                    Used for greeting-style messages and context. Tone for all replies is set below.
                  </p>
                </Field>

                <Field>
                  <FieldLabel htmlFor="responseStyle">Response Style</FieldLabel>
                  <Select value={aiTone} onValueChange={setAiTone}>
                    <SelectTrigger id="responseStyle">
                      <SelectValue placeholder="Select style" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="formal">Formal</SelectItem>
                      <SelectItem value="balanced">Balanced</SelectItem>
                      <SelectItem value="casual">Casual</SelectItem>
                    </SelectContent>
                  </Select>
                </Field>

                <div className="space-y-4 pt-4 border-t border-border">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="font-medium text-foreground">Auto-reply</p>
                      <p className="text-sm text-muted-foreground">Enable automated AI replies where configured</p>
                    </div>
                    <Switch checked={autoReplyEnabled} onCheckedChange={setAutoReplyEnabled} />
                  </div>

                  <div className="flex items-center justify-between">
                    <div>
                      <p className="font-medium text-foreground">Learn from conversations</p>
                      <p className="text-sm text-muted-foreground">Use past chats to improve consistency</p>
                    </div>
                    <Switch checked={learnFromConversations} onCheckedChange={setLearnFromConversations} />
                  </div>

                  <div className="flex items-center justify-between">
                    <div>
                      <p className="font-medium text-foreground">Notifications</p>
                      <p className="text-sm text-muted-foreground">In-app / email notifications for your team</p>
                    </div>
                    <Switch checked={notificationsEnabled} onCheckedChange={setNotificationsEnabled} />
                  </div>
                </div>

                <Button type="submit" disabled={aiSaving}>
                  {aiSaving ? 'Saving…' : 'Save AI Settings'}
                </Button>
              </form>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Staff Management */}
        <TabsContent value="team">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <div>
                <CardTitle>Team Members</CardTitle>
                <CardDescription>Manage your team access and roles</CardDescription>
              </div>
              <Button>
                <Plus className="h-4 w-4 mr-2" />
                Invite Member
              </Button>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Member</TableHead>
                    <TableHead>Role</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {teamMembers.map((member) => (
                    <TableRow key={member.id}>
                      <TableCell>
                        <div className="flex items-center gap-3">
                          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
                            {member.name.charAt(0)}
                          </div>
                          <div>
                            <div className="font-medium text-foreground">{member.name}</div>
                            <div className="text-sm text-muted-foreground">{member.email}</div>
                          </div>
                        </div>
                      </TableCell>
                      <TableCell>
                        <Select defaultValue={member.role.toLowerCase()}>
                          <SelectTrigger className="w-28">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="admin">Admin</SelectItem>
                            <SelectItem value="agent">Agent</SelectItem>
                            <SelectItem value="viewer">Viewer</SelectItem>
                          </SelectContent>
                        </Select>
                      </TableCell>
                      <TableCell>
                        <Badge variant={member.status === "active" ? "default" : "secondary"}>
                          {member.status}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Button variant="ghost" size="icon">
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Notifications */}
        <TabsContent value="notifications">
          <Card>
            <CardHeader>
              <CardTitle>Notification Preferences</CardTitle>
              <CardDescription>Configure how you receive notifications</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="space-y-4">
                <h3 className="font-medium text-foreground">Email Notifications</h3>
                
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">New orders</p>
                    <p className="text-sm text-muted-foreground">Get notified when a new order is placed</p>
                  </div>
                  <Switch defaultChecked />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">AI handoff requests</p>
                    <p className="text-sm text-muted-foreground">When AI needs human assistance</p>
                  </div>
                  <Switch defaultChecked />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Daily summary</p>
                    <p className="text-sm text-muted-foreground">Receive a daily activity summary</p>
                  </div>
                  <Switch defaultChecked />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Weekly analytics</p>
                    <p className="text-sm text-muted-foreground">Receive weekly performance reports</p>
                  </div>
                  <Switch defaultChecked />
                </div>
              </div>

              <div className="space-y-4 pt-4 border-t border-border">
                <h3 className="font-medium text-foreground">Push Notifications</h3>
                
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">New messages</p>
                    <p className="text-sm text-muted-foreground">Get push notifications for new messages</p>
                  </div>
                  <Switch defaultChecked />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Order updates</p>
                    <p className="text-sm text-muted-foreground">Status changes on orders</p>
                  </div>
                  <Switch defaultChecked />
                </div>
              </div>

              <Button>Save Preferences</Button>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Order Payments — enable M-Pesa and/or Stripe for customer orders */}
        <TabsContent value="order-payments">
          <Card>
            <CardHeader>
              <CardTitle>Collect payment for orders</CardTitle>
              <CardDescription>
                Choose whether to collect payment after orders. You can use M-Pesa, card (Stripe), and/or manual payment details (e.g. bank account). Turn off to skip payment and only confirm the order.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleOrderPaymentsSubmit} className="space-y-6">
                {orderPaymentsMessage && (
                  <p className={`text-sm ${orderPaymentsMessage.startsWith('Saved') ? 'text-primary' : 'text-muted-foreground'}`}>
                    {orderPaymentsMessage}
                  </p>
                )}
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Collect payment for orders</p>
                    <p className="text-sm text-muted-foreground">When on, the bot will ask the customer how to pay (M-Pesa, card, or manual). When off, the bot only confirms the order.</p>
                  </div>
                  <Switch checked={ordersCollectPaymentEnabled} onCheckedChange={setOrdersCollectPaymentEnabled} />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">M-Pesa (STK push)</p>
                    <p className="text-sm text-muted-foreground">Customer receives M-Pesa prompt on their phone to pay</p>
                  </div>
                  <Switch checked={ordersAcceptMpesa} onCheckedChange={setOrdersAcceptMpesa} disabled={!ordersCollectPaymentEnabled} />
                </div>
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Card (Stripe)</p>
                    <p className="text-sm text-muted-foreground">Customer gets a payment link to pay by card online</p>
                  </div>
                  <Switch checked={ordersAcceptStripe} onCheckedChange={setOrdersAcceptStripe} disabled={!ordersCollectPaymentEnabled} />
                </div>

                <FieldGroup>
                  <div>
                    <p className="font-medium text-foreground">Manual payment instructions (optional)</p>
                    <p className="text-sm text-muted-foreground">Bank account, PayBill to pay manually, etc. The bot will show this to the customer as option 3 or as the only payment option if you don&apos;t use M-Pesa/Stripe.</p>
                  </div>
                  <Textarea
                    placeholder="e.g. Pay via M-Pesa to Till 123456&#10;Or bank: KCB 1234567890, Account: MyShop. Use order number as reference."
                    value={orderPaymentManualInstructions}
                    onChange={(e) => setOrderPaymentManualInstructions(e.target.value)}
                    rows={4}
                    className="mt-2"
                    disabled={!ordersCollectPaymentEnabled}
                  />
                </FieldGroup>

                <div className="border-t border-border pt-6 space-y-6">
                  <h3 className="font-medium text-foreground">Use your own payment details (optional)</h3>
                  <p className="text-sm text-muted-foreground">
                    Add your M-Pesa till or Stripe account so payments go to you. Otherwise the platform default is used.
                  </p>

                  <FieldGroup>
                    <div className="flex items-center justify-between gap-4">
                      <div>
                        <p className="font-medium text-foreground">M-Pesa (Lipa Na M-Pesa Online)</p>
                        <p className="text-sm text-muted-foreground">PayBill or Till (Buy Goods); shortcode + passkey. Optional: Daraja consumer key/secret</p>
                      </div>
                      {settings?.orderPaymentMpesaConfigured && (
                        <div className="flex items-center gap-2">
                          <Badge variant="default" className="gap-1"><Check className="h-3 w-3" /> Configured</Badge>
                          <Button type="button" variant="outline" size="sm" onClick={handleClearMpesaConfig}>Clear</Button>
                        </div>
                      )}
                    </div>
                    <Field>
                      <FieldLabel>Type</FieldLabel>
                      <Select value={mpesaType} onValueChange={(v) => setMpesaType(v as 'paybill' | 'till')}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="paybill">PayBill</SelectItem>
                          <SelectItem value="till">Till (Buy Goods and Services)</SelectItem>
                        </SelectContent>
                      </Select>
                      <p className="text-xs text-muted-foreground mt-1">Use PayBill if you have a business PayBill number; use Till if you have a Lipa Na M-Pesa Till (Buy Goods) number.</p>
                    </Field>
                    <div className="grid gap-4 md:grid-cols-2">
                      <Field>
                        <FieldLabel>Shortcode</FieldLabel>
                        <Input placeholder={mpesaType === 'till' ? 'Till number' : 'e.g. 174379'} value={mpesaShortcode} onChange={(e) => setMpesaShortcode(e.target.value)} />
                      </Field>
                      <Field>
                        <FieldLabel>Passkey</FieldLabel>
                        {isMasked(mpesaPasskey) && !replacingMpesaSecret[mpesaSecretKey("passkey")] ? (
                          <div className="space-y-1.5">
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                              <Input type="text" readOnly className="font-mono text-sm" value={mpesaPasskey} />
                              <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="shrink-0"
                                onClick={() => {
                                  setReplacingMpesaSecret((p) => ({ ...p, [mpesaSecretKey("passkey")]: true }))
                                  setMpesaPasskey("")
                                }}
                              >
                                Replace
                              </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                              Stored passkey (masked). Use Replace to enter a new value.
                            </p>
                          </div>
                        ) : (
                          <div className="space-y-1">
                            <Input
                              type="password"
                              placeholder="Lipa Na M-Pesa passkey"
                              value={mpesaPasskey}
                              onChange={(e) => setMpesaPasskey(e.target.value)}
                            />
                            {replacingMpesaSecret[mpesaSecretKey("passkey")] && (
                              <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-8 text-xs"
                                onClick={() => {
                                  setReplacingMpesaSecret((p) => {
                                    const n = { ...p }
                                    delete n[mpesaSecretKey("passkey")]
                                    return n
                                  })
                                  mutate("company-settings")
                                }}
                              >
                                Cancel replace
                              </Button>
                            )}
                          </div>
                        )}
                      </Field>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                      <Field>
                        <FieldLabel>Consumer key (optional)</FieldLabel>
                        <Input placeholder="Daraja app consumer key" value={mpesaConsumerKey} onChange={(e) => setMpesaConsumerKey(e.target.value)} />
                      </Field>
                      <Field>
                        <FieldLabel>Consumer secret (optional)</FieldLabel>
                        {isMasked(mpesaConsumerSecret) && !replacingMpesaSecret[mpesaSecretKey("consumer_secret")] ? (
                          <div className="space-y-1.5">
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                              <Input type="text" readOnly className="font-mono text-sm" value={mpesaConsumerSecret} />
                              <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="shrink-0"
                                onClick={() => {
                                  setReplacingMpesaSecret((p) => ({ ...p, [mpesaSecretKey("consumer_secret")]: true }))
                                  setMpesaConsumerSecret("")
                                }}
                              >
                                Replace
                              </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                              Stored consumer secret (masked). Use Replace to enter a new value.
                            </p>
                          </div>
                        ) : (
                          <div className="space-y-1">
                            <Input
                              type="password"
                              placeholder="Daraja app consumer secret"
                              value={mpesaConsumerSecret}
                              onChange={(e) => setMpesaConsumerSecret(e.target.value)}
                            />
                            {replacingMpesaSecret[mpesaSecretKey("consumer_secret")] && (
                              <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-8 text-xs"
                                onClick={() => {
                                  setReplacingMpesaSecret((p) => {
                                    const n = { ...p }
                                    delete n[mpesaSecretKey("consumer_secret")]
                                    return n
                                  })
                                  mutate("company-settings")
                                }}
                              >
                                Cancel replace
                              </Button>
                            )}
                          </div>
                        )}
                      </Field>
                    </div>
                    <Field>
                      <FieldLabel>Environment</FieldLabel>
                      <Select value={mpesaEnv} onValueChange={(v) => setMpesaEnv(v as 'sandbox' | 'production')}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="sandbox">Sandbox</SelectItem>
                          <SelectItem value="production">Production</SelectItem>
                        </SelectContent>
                      </Select>
                    </Field>
                  </FieldGroup>

                  <FieldGroup>
                    <div className="flex items-center justify-between gap-4">
                      <div>
                        <p className="font-medium text-foreground">Stripe account</p>
                        <p className="text-sm text-muted-foreground">Secret key (sk_live_... or sk_test_...) so payments go to your Stripe</p>
                      </div>
                      {settings?.orderPaymentStripeConfigured && (
                        <div className="flex items-center gap-2">
                          <Badge variant="default" className="gap-1"><Check className="h-3 w-3" /> Configured</Badge>
                          <Button type="button" variant="outline" size="sm" onClick={handleClearStripeConfig}>Clear</Button>
                        </div>
                      )}
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                      <Field>
                        <FieldLabel>Secret key</FieldLabel>
                        {isMasked(stripeSecret) && !replacingStripeSecret ? (
                          <div className="space-y-1.5">
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                              <Input type="text" readOnly className="font-mono text-sm" value={stripeSecret} />
                              <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="shrink-0"
                                onClick={() => {
                                  setReplacingStripeSecret(true)
                                  setStripeSecret("")
                                }}
                              >
                                Replace
                              </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                              Stored Stripe secret (masked). Use Replace to enter a new key.
                            </p>
                          </div>
                        ) : (
                          <div className="space-y-1">
                            <Input
                              type="password"
                              placeholder="sk_live_... or sk_test_..."
                              value={stripeSecret}
                              onChange={(e) => setStripeSecret(e.target.value)}
                            />
                            {replacingStripeSecret && (
                              <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-8 text-xs"
                                onClick={() => {
                                  setReplacingStripeSecret(false)
                                  mutate("company-settings")
                                }}
                              >
                                Cancel replace
                              </Button>
                            )}
                          </div>
                        )}
                      </Field>
                      <Field>
                        <FieldLabel>Currency</FieldLabel>
                        <Input placeholder="usd, kes, etc." value={stripeCurrency} onChange={(e) => setStripeCurrency(e.target.value)} />
                      </Field>
                    </div>
                  </FieldGroup>
                </div>

                <Button type="submit" disabled={orderPaymentsSaving}>
                  {orderPaymentsSaving ? 'Saving…' : 'Save'}
                </Button>
              </form>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}
