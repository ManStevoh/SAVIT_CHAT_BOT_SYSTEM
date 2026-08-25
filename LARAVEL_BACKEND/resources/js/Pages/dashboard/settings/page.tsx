"use client"

import { useState, useEffect, useMemo } from "react"
import Link from "next/link"
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
import {
  Building2,
  MessageSquare,
  Bot,
  Users,
  Bell,
  Plus,
  Trash2,
  Check,
  CreditCard,
  Smartphone,
  Zap,
  Banknote,
  FileText,
  Package,
  Clock,
  AlertCircle,
  Settings2,
  Loader2,
  ChevronRight,
  ChevronLeft,
  ChevronDown,
  ChevronUp,
  ExternalLink,
  Copy,
  Palette,
} from "lucide-react"
import { OnboardingInterviewPanel } from "@/components/agent/OnboardingInterviewPanel"
import { BrandCustomizationCard } from "@/components/dashboard/BrandCustomizationCard"
import { useSearchParams } from "next/navigation"

function isMasked(val: unknown): boolean {
  return typeof val === "string" && val.startsWith("••••")
}

function mpesaSecretKey(field: "passkey" | "consumer_secret") {
  return `mpesa:${field}`
}
// API: GET /api/company/settings (useCompanySettings), PUT /api/company/settings (updateSettings)
import { useCompanySettings, useCompanyTeam, useWhatsAppNumbers, type BusinessDnaPreset, type BusinessDnaSettings } from "@/lib/api-hooks"
import { apiRequest } from "@/lib/api-client"
import { CATALOG_CURRENCY_OPTIONS, normalizeCurrencyCode, pairedDecimalForThousands, formatCurrencyAmount } from "@/lib/format-currency"
import { useSWRConfig } from "swr"
import {
  updateSettings,
  getCompanyAiProviders,
  updateCompanyAiProvider,
  getCompanyAiUsage,
  exportLearningSamples,
  getWhatsAppStatus,
  disconnectWhatsApp,
  resubscribeWhatsAppWebhooks,
  connectWhatsApp,
  getWhatsAppEmbeddedConfig,
  completeWhatsAppEmbeddedSignup,
  listWhatsAppTemplates,
  createWhatsAppTemplate,
  syncWhatsAppTemplates,
  deleteWhatsAppTemplate,
  getWhatsAppCampaignAudience,
  sendWhatsAppCampaign,
  type WhatsAppStatus,
  type WhatsAppTemplate,
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
  const searchParams = useSearchParams()
  const tabFromUrl = searchParams.get('tab')
  const allowedTabs = new Set(['profile', 'branding', 'appearance', 'whatsapp', 'ai', 'team', 'notifications', 'order-payments'])
  const normalizeTab = (t: string | null) => (t === 'appearance' ? 'branding' : t)
  const [activeTab, setActiveTab] = useState(() => {
    const raw = tabFromUrl && allowedTabs.has(tabFromUrl) ? tabFromUrl : 'profile'
    return normalizeTab(raw) || 'profile'
  })

  useEffect(() => {
    if (tabFromUrl && allowedTabs.has(tabFromUrl)) {
      setActiveTab(normalizeTab(tabFromUrl) || 'profile')
    }
  }, [tabFromUrl])

  const { data: settings } = useCompanySettings()
  const { data: teamMembers = [] } = useCompanyTeam({ enabled: activeTab === 'team' })
  const { data: whatsappNumbers = [] } = useWhatsAppNumbers({ enabled: activeTab === 'whatsapp' })
  const [profileSaving, setProfileSaving] = useState(false)
  const [profileError, setProfileError] = useState<string | null>(null)
  const [profileSuccess, setProfileSuccess] = useState(false)

  // Card-specific save states for AI Settings
  const [aiModelSaving, setAiModelSaving] = useState(false)
  const [aiModelError, setAiModelError] = useState<string | null>(null)
  const [aiModelSuccess, setAiModelSuccess] = useState(false)

  const [aiCommerceSaving, setAiCommerceSaving] = useState(false)
  const [aiCommerceError, setAiCommerceError] = useState<string | null>(null)
  const [aiCommerceSuccess, setAiCommerceSuccess] = useState(false)

  const [aiVoiceSaving, setAiVoiceSaving] = useState(false)
  const [aiVoiceError, setAiVoiceError] = useState<string | null>(null)
  const [aiVoiceSuccess, setAiVoiceSuccess] = useState(false)

  const [businessDnaSaving, setBusinessDnaSaving] = useState(false)
  const [businessDnaError, setBusinessDnaError] = useState<string | null>(null)
  const [businessDnaSuccess, setBusinessDnaSuccess] = useState(false)

  const [aiAdvancedSaving, setAiAdvancedSaving] = useState(false)
  const [aiAdvancedError, setAiAdvancedError] = useState<string | null>(null)
  const [aiAdvancedSuccess, setAiAdvancedSuccess] = useState(false)
  const [businessName, setBusinessName] = useState("QuickBite Restaurant")
  const [industry, setIndustry] = useState<'retail' | 'restaurant' | 'services' | 'other'>('other')
  const [businessMode, setBusinessMode] = useState<'retail' | 'services' | 'restaurant' | 'hybrid'>('hybrid')
  const [enableProductsCatalog, setEnableProductsCatalog] = useState(true)
  const [enableBookings, setEnableBookings] = useState(true)
  const [enableDineIn, setEnableDineIn] = useState(false)
  const [email, setEmail] = useState("contact@quickbite.com")
  const [phone, setPhone] = useState("+1 555-0100")
  const [address, setAddress] = useState("123 Main Street, New York, NY 10001")
  const [displayCurrency, setDisplayCurrency] = useState("USD")
  const [currencySymbol, setCurrencySymbol] = useState("")
  const [thousandsSeparator, setThousandsSeparator] = useState(",")
  const [decimalSeparator, setDecimalSeparator] = useState(".")
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
  const [waMessage, setWaMessage] = useState<string | null>(null)
  const [waMessageError, setWaMessageError] = useState(false)
  const [waEmbeddedLoading, setWaEmbeddedLoading] = useState(false)
  const [waManualLoading, setWaManualLoading] = useState(false)
  const [waManualPhoneNumberId, setWaManualPhoneNumberId] = useState("")
  const [waManualAccessToken, setWaManualAccessToken] = useState("")
  const [waManualWabaId, setWaManualWabaId] = useState("")
  const [waManualDisplayPhone, setWaManualDisplayPhone] = useState("")
  const [waManualRegistrationPin, setWaManualRegistrationPin] = useState("")
  const [waManualWebhookVerifyToken, setWaManualWebhookVerifyToken] = useState("")
  const [waManualMetaAppSecret, setWaManualMetaAppSecret] = useState("")
  const [waFixMetaAppSecret, setWaFixMetaAppSecret] = useState("")
  const [waFixWebhookVerifyToken, setWaFixWebhookVerifyToken] = useState("")
  const [waTemplates, setWaTemplates] = useState<WhatsAppTemplate[]>([])
  const [tplName, setTplName] = useState("")
  const [tplBody, setTplBody] = useState("")
  const [tplCategory, setTplCategory] = useState<"utility" | "marketing" | "authentication">("utility")
  const [campaignAudience, setCampaignAudience] = useState(0)
  const [campaignSegment, setCampaignSegment] = useState<"all" | "recent" | "inactive" | "ordered">("all")
  const [campaignTemplate, setCampaignTemplate] = useState("")
  const [campaignImageUrl, setCampaignImageUrl] = useState("")
  const [campaignCaption, setCampaignCaption] = useState("")
  const [campaignSending, setCampaignSending] = useState(false)
  const [tplLoading, setTplLoading] = useState(false)

  // Interactive WhatsApp Onboarding Flow State
  const [waModalOpen, setWaModalOpen] = useState(false)
  const [waStep, setWaStep] = useState<1 | 2 | 3 | 4>(1)
  const [waMethod, setWaMethod] = useState<"embedded" | "manual">("embedded")
  const [waChecklist, setWaChecklist] = useState({
    phoneReady: false,
    metaAccess: false,
    noConflict: false,
  })
  const [copiedWebhook, setCopiedWebhook] = useState(false)
  const [collapsedSections, setCollapsedSections] = useState<Record<string, boolean>>({})
  const toggleSection = (id: string) => setCollapsedSections((prev) => ({ ...prev, [id]: !prev[id] }))
  /** Only show Manual setup when Admin has enabled it (API: manualConnectEnabled). */
  const manualConnectEnabled = waStatus?.manualConnectEnabled === true

  useEffect(() => {
    if (!manualConnectEnabled && waMethod === "manual") {
      setWaMethod("embedded")
      if (waStep === 2) setWaStep(1)
    }
  }, [manualConnectEnabled, waMethod, waStep])

  const loadWhatsAppTemplates = async () => {
    try {
      const items = await listWhatsAppTemplates()
      setWaTemplates(items)
    } catch {
      setWaTemplates([])
    }
  }

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
    if (activeTab === "whatsapp") {
      loadWhatsAppStatus()
      loadWhatsAppTemplates()
      getWhatsAppCampaignAudience(campaignSegment).then((a) => setCampaignAudience(a.uniqueCustomers)).catch(() => { })
    }
  }, [activeTab, campaignSegment])

  const handleWhatsAppDisconnect = async () => {
    setWaMessage(null)
    setWaLoading(true)
    const result = await disconnectWhatsApp()
    setWaMessage(result.message ?? (result.success ? "Disconnected." : "Failed."))
    loadWhatsAppStatus()
  }

  const handleResubscribeWebhooks = async () => {
    setWaMessage(null)
    setWaMessageError(false)
    const needsSecret = waStatus?.connectedVia === "manual" && !waStatus?.hasMetaAppSecret
    const needsVerify = waStatus?.connectedVia === "manual" && !waStatus?.hasWebhookVerifyToken
    if (needsSecret && !waFixMetaAppSecret.trim()) {
      setWaMessage("Paste your Meta App Secret (from the same Meta app as your access token), then click Fix inbound messages.")
      setWaMessageError(true)
      return
    }
    if (needsVerify && !waFixWebhookVerifyToken.trim()) {
      setWaMessage("Paste your webhook verify token (from your Meta app webhook configuration), then click Fix inbound messages.")
      setWaMessageError(true)
      return
    }
    setWaLoading(true)
    try {
      const result = await resubscribeWhatsAppWebhooks({
        metaAppSecret: waFixMetaAppSecret.trim() || undefined,
        webhookVerifyToken: waFixWebhookVerifyToken.trim() || undefined,
      })
      setWaMessage(result.message ?? (result.success ? "Webhook subscribed." : "Failed to subscribe webhook."))
      setWaMessageError(!result.success)
      if (result.success) {
        setWaFixMetaAppSecret("")
        setWaFixWebhookVerifyToken("")
      }
      await loadWhatsAppStatus()
    } catch (err) {
      setWaMessage(err instanceof Error ? err.message : "Failed to subscribe webhook.")
      setWaMessageError(true)
    } finally {
      setWaLoading(false)
    }
  }

  const handleManualConnect = async (e: React.FormEvent) => {
    e.preventDefault()
    setWaMessage(null)
    setWaMessageError(false)
    const phoneNumberId = waManualPhoneNumberId.trim()
    const accessToken = waManualAccessToken.trim()
    const wabaId = waManualWabaId.trim()
    const metaAppSecret = waManualMetaAppSecret.trim()
    const webhookVerifyToken = waManualWebhookVerifyToken.trim()
    if (!phoneNumberId || !accessToken) {
      setWaMessage("Phone Number ID and permanent access token are required.")
      setWaMessageError(true)
      return
    }
    if (!wabaId) {
      setWaMessage("WhatsApp Business Account ID is required so inbound messages can be received.")
      setWaMessageError(true)
      return
    }
    if (!metaAppSecret) {
      setWaMessage("Meta App Secret is required. Use the App Secret from the same Meta Developer app that created this access token — not the platform/super-admin secret unless this token is from that same app.")
      setWaMessageError(true)
      return
    }
    if (!webhookVerifyToken) {
      setWaMessage("Webhook verify token is required. Set any string in Meta → Your App → WhatsApp → Configuration, and paste the same value here. Do not reuse the platform verify token unless this token is from that same app.")
      setWaMessageError(true)
      return
    }
    const pin = waManualRegistrationPin.trim()
    if (pin !== "" && pin.length !== 6) {
      setWaMessage("Two-step verification PIN must be exactly 6 digits, or leave it blank for a new number.")
      setWaMessageError(true)
      return
    }
    setWaManualLoading(true)
    try {
      const result = await connectWhatsApp({
        phoneNumberId,
        accessToken,
        whatsappBusinessAccountId: wabaId,
        displayPhoneNumber: waManualDisplayPhone.trim() || undefined,
        registrationPin: pin.length === 6 ? pin : undefined,
        webhookVerifyToken,
        metaAppSecret,
      })
      setWaMessage(result.message ?? (result.success ? "WhatsApp connected." : "Connection failed."))
      setWaMessageError(!result.success)
      if (result.success) {
        setWaManualAccessToken("")
        setWaManualRegistrationPin("")
        setWaManualWebhookVerifyToken("")
        setWaManualMetaAppSecret("")
        await loadWhatsAppStatus()
        await loadWhatsAppTemplates()
      }
    } catch (err) {
      setWaMessage(err instanceof Error ? err.message : "Manual connection failed.")
      setWaMessageError(true)
    } finally {
      setWaManualLoading(false)
    }
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
      if (cfg.platformBillingReady === false) {
        setWaMessage("Platform WhatsApp billing is enabled but not configured. Contact your administrator.")
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
      // Meta Embedded Signup requires sessionInfoVersion or the popup can fall back
      // to a normal Facebook login (news feed) instead of the WhatsApp wizard.
      const loginExtras: Record<string, unknown> = {
        setup: {},
        featureType: cfg.enableCoexist ? "coex" : "",
        sessionInfoVersion: "3",
      }

      const code = await new Promise<string | null>((resolve) => {
        window.FB?.login(
          (response) => {
            resolve(response?.authResponse?.code ?? null)
          },
          {
            config_id: cfg.configId,
            response_type: "code",
            override_default_response_type: true,
            extras: loginExtras,
          }
        )
      })
      const finishData = await finishPromise

      if (!code) {
        setWaMessage("Signup was cancelled or Meta did not return an authorization code.")
        return
      }

      const result = await completeWhatsAppEmbeddedSignup({
        code,
        phoneNumberId: finishData?.phoneNumberId,
        whatsappBusinessAccountId: finishData?.whatsappBusinessAccountId,
      })
      setWaMessage(result.message ?? (result.success ? "WhatsApp connected via embedded signup." : "Failed to connect."))
      setWaMessageError(!result.success)
      if (result.success) {
        setWaStep(4)
        await loadWhatsAppStatus()
        await loadWhatsAppTemplates()
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
  const [ordersAcceptPaystack, setOrdersAcceptPaystack] = useState(false)
  const [ordersAcceptPesapal, setOrdersAcceptPesapal] = useState(false)
  const [ordersAcceptFlutterwave, setOrdersAcceptFlutterwave] = useState(false)
  const [ordersAcceptPayPal, setOrdersAcceptPayPal] = useState(false)
  const [ordersAcceptCod, setOrdersAcceptCod] = useState(false)
  const [deliveryFeesEnabled, setDeliveryFeesEnabled] = useState(false)
  const [defaultDeliveryFee, setDefaultDeliveryFee] = useState<string>("")
  const [freeDeliveryAbove, setFreeDeliveryAbove] = useState<string>("")
  const [paymentRecoveryEnabled, setPaymentRecoveryEnabled] = useState(true)
  const [attributionRetentionDays, setAttributionRetentionDays] = useState<string>("")
  const [orderPaymentsSaving, setOrderPaymentsSaving] = useState(false)
  const [orderPaymentsMessage, setOrderPaymentsMessage] = useState<string | null>(null)
  const [mpesaType, setMpesaType] = useState<'paybill' | 'till'>('paybill')
  const [mpesaShortcode, setMpesaShortcode] = useState('')
  const [mpesaPasskey, setMpesaPasskey] = useState('')
  const [mpesaConsumerKey, setMpesaConsumerKey] = useState('')
  const [mpesaConsumerSecret, setMpesaConsumerSecret] = useState('')
  const [mpesaEnv, setMpesaEnv] = useState<'sandbox' | 'production'>('sandbox')
  const [stripeSecret, setStripeSecret] = useState('')
  const [stripeCurrency, setStripeCurrency] = useState('kes')
  const [stripeEnv, setStripeEnv] = useState<'sandbox' | 'production'>('sandbox')
  const [paystackSecretKey, setPaystackSecretKey] = useState('')
  const [paystackPublicKey, setPaystackPublicKey] = useState('')
  const [paystackCurrency, setPaystackCurrency] = useState('kes')
  const [paystackEnv, setPaystackEnv] = useState<'sandbox' | 'production'>('sandbox')
  const [pesapalConsumerKey, setPesapalConsumerKey] = useState('')
  const [pesapalConsumerSecret, setPesapalConsumerSecret] = useState('')
  const [pesapalCurrency, setPesapalCurrency] = useState('kes')
  const [pesapalEnv, setPesapalEnv] = useState<'sandbox' | 'production'>('sandbox')
  const [flutterwavePublicKey, setFlutterwavePublicKey] = useState('')
  const [flutterwaveSecretKey, setFlutterwaveSecretKey] = useState('')
  const [flutterwaveSecretHash, setFlutterwaveSecretHash] = useState('')
  const [flutterwaveCurrency, setFlutterwaveCurrency] = useState('kes')
  const [flutterwaveEnv, setFlutterwaveEnv] = useState<'sandbox' | 'production'>('sandbox')
  const [paypalClientId, setPaypalClientId] = useState('')
  const [paypalClientSecret, setPaypalClientSecret] = useState('')
  const [paypalCurrency, setPaypalCurrency] = useState('usd')
  const [paypalEnv, setPaypalEnv] = useState<'sandbox' | 'production'>('sandbox')
  const [replacingMpesaSecret, setReplacingMpesaSecret] = useState<Record<string, boolean>>({})
  const [replacingStripeSecret, setReplacingStripeSecret] = useState(false)
  const [replacingPaystackSecret, setReplacingPaystackSecret] = useState(false)
  const [replacingPesapalSecret, setReplacingPesapalSecret] = useState(false)
  const [replacingFlutterwaveSecret, setReplacingFlutterwaveSecret] = useState(false)
  const [replacingPayPalSecret, setReplacingPayPalSecret] = useState(false)

  // Per-option saving and saved states for Order Payments tab
  const [optionSaving, setOptionSaving] = useState<Record<string, boolean>>({})
  const [optionSaved, setOptionSaved] = useState<Record<string, boolean>>({})

  const setOptionSavingState = (key: string, isSaving: boolean) => {
    setOptionSaving((prev) => ({ ...prev, [key]: isSaving }))
  }

  const setOptionSavedState = (key: string) => {
    setOptionSaved((prev) => ({ ...prev, [key]: true }))
    setTimeout(() => {
      setOptionSaved((prev) => ({ ...prev, [key]: false }))
    }, 3000)
  }

  /** AI tab — persisted via PUT /api/company/settings (aiGreeting, aiTone, booleans). Model is platform-wide, not saved here. */
  const [aiGreeting, setAiGreeting] = useState('')
  const [aiTone, setAiTone] = useState('balanced')
  const [aiModelMode, setAiModelMode] = useState<'auto' | 'platform_default' | 'specific'>('auto')
  const [aiModelId, setAiModelId] = useState<string>('')
  const [aiReplyMode, setAiReplyMode] = useState<'ai_first' | 'balanced'>('ai_first')
  const [availableAiModels, setAvailableAiModels] = useState<Array<{
    id: string
    displayName: string
    provider: string
    inputCostPerMillion: number
    outputCostPerMillion: number
  }>>([])
  const [autoReplyEnabled, setAutoReplyEnabled] = useState(false)
  const [agentCommerceEnabled, setAgentCommerceEnabled] = useState(false)
  const [agentProactiveEnabled, setAgentProactiveEnabled] = useState(false)
  const [agentVoiceReplyEnabled, setAgentVoiceReplyEnabled] = useState(false)
  const [agentVoiceReplyMode, setAgentVoiceReplyMode] = useState<'voice_only' | 'dual_text_and_voice' | 'text_only'>('dual_text_and_voice')
  const [agentVoiceId, setAgentVoiceId] = useState<string>('nova')
  const [agentMorningBriefWhatsappEnabled, setAgentMorningBriefWhatsappEnabled] = useState(false)
  const [ownerWhatsappPhone, setOwnerWhatsappPhone] = useState('')
  const [webWidgetToken, setWebWidgetToken] = useState<string | null>(null)
  const [channelIngestSecret, setChannelIngestSecret] = useState<string | null>(null)
  const [channelWebhookUrls, setChannelWebhookUrls] = useState<{ email: string; instagramDm: string } | null>(null)
  const [widgetScriptUrl, setWidgetScriptUrl] = useState<string | null>(null)
  const [companyIdForEmbed, setCompanyIdForEmbed] = useState<number | null>(null)
  const [agentBusinessGoals, setAgentBusinessGoals] = useState<string[]>([])
  const [agentBusinessGoalCatalog, setAgentBusinessGoalCatalog] = useState<Record<string, string>>({})
  const [businessDnaPreset, setBusinessDnaPreset] = useState<'industry_default' | 'luxury_brand' | 'friendly_cafe' | 'custom'>('industry_default')
  const [businessDna, setBusinessDna] = useState<BusinessDnaSettings>({})
  const [businessDnaPresets, setBusinessDnaPresets] = useState<Record<string, BusinessDnaPreset>>({})
  const [digitalTwin, setDigitalTwin] = useState<Record<string, string>>({})
  const [agentCouncilEnabled, setAgentCouncilEnabled] = useState(false)
  const [learnFromConversations, setLearnFromConversations] = useState(true)
  const [learnFromConversationsEditable, setLearnFromConversationsEditable] = useState(true)
  const [devModeEnabled, setDevModeEnabled] = useState(false)
  const [notificationsEnabled, setNotificationsEnabled] = useState(false)
  const [aiSaving, setAiSaving] = useState(false)
  const [aiMessage, setAiMessage] = useState<string | null>(null)
  const [aiCredentialMode, setAiCredentialMode] = useState<'platform' | 'company' | 'company_preferred'>('platform')
  const [openaiApiKey, setOpenaiApiKey] = useState('')
  const [openaiKeyConfigured, setOpenaiKeyConfigured] = useState(false)
  const [aiUsageSummary, setAiUsageSummary] = useState<Record<string, unknown> | null>(null)
  const [aiUsageExtras, setAiUsageExtras] = useState<{
    byCredentialSource?: Array<{ source: string; requests: number; billedCostUsd: number }>
    learningEmbeddingCoveragePercent?: number
  } | null>(null)
  const [byokSaving, setByokSaving] = useState(false)
  const [replyInCustomerLanguage, setReplyInCustomerLanguage] = useState(true)
  const [defaultReplyLanguage, setDefaultReplyLanguage] = useState('')
  const [aiPlanCapabilities, setAiPlanCapabilities] = useState<{
    allowedModelModes: string[]
    allowByok: boolean
    allowedCredentialModes: string[]
    plan?: string
  } | null>(null)

  // Load initial values from GET /api/company/settings when available
  useEffect(() => {
    if (settings) {
      if (settings.companyName != null) setBusinessName(settings.companyName)
      if (settings.industry) setIndustry(settings.industry)
      if (settings.businessMode) setBusinessMode(settings.businessMode)
      if (settings.enableProductsCatalog != null) setEnableProductsCatalog(settings.enableProductsCatalog)
      if (settings.enableBookings != null) setEnableBookings(settings.enableBookings)
      if (settings.enableDineIn != null) setEnableDineIn(settings.enableDineIn)
      if (settings.email != null) setEmail(settings.email)
      if (settings.phone != null) setPhone(settings.phone)
      if (settings.address != null) setAddress(settings.address)
      if (settings.displayCurrency != null && settings.displayCurrency !== "") {
        setDisplayCurrency(normalizeCurrencyCode(settings.displayCurrency))
      }
      if (settings.currencySymbol != null) {
        setCurrencySymbol(String(settings.currencySymbol))
      }
      if (settings.thousandsSeparator != null && settings.thousandsSeparator !== "") {
        setThousandsSeparator(settings.thousandsSeparator)
      }
      if (settings.decimalSeparator != null && settings.decimalSeparator !== "") {
        setDecimalSeparator(settings.decimalSeparator)
      }
      if (settings.timezone != null && String(settings.timezone).trim() !== "") {
        setTimezone(String(settings.timezone).trim())
      }
      if (settings.ordersCollectPaymentEnabled != null) setOrdersCollectPaymentEnabled(settings.ordersCollectPaymentEnabled)
      if (settings.orderPaymentManualInstructions != null) setOrderPaymentManualInstructions(settings.orderPaymentManualInstructions)
      if (settings.ordersAcceptMpesa != null) setOrdersAcceptMpesa(settings.ordersAcceptMpesa)
      if (settings.ordersAcceptStripe != null) setOrdersAcceptStripe(settings.ordersAcceptStripe)
      if (settings.ordersAcceptPaystack != null) setOrdersAcceptPaystack(settings.ordersAcceptPaystack)
      if (settings.ordersAcceptPesapal != null) setOrdersAcceptPesapal(settings.ordersAcceptPesapal)
      if (settings.ordersAcceptCod != null) setOrdersAcceptCod(settings.ordersAcceptCod)
      if (settings.deliveryFeesEnabled != null) setDeliveryFeesEnabled(settings.deliveryFeesEnabled)
      if (settings.defaultDeliveryFee != null) setDefaultDeliveryFee(String(settings.defaultDeliveryFee))
      if (settings.freeDeliveryAbove != null) setFreeDeliveryAbove(String(settings.freeDeliveryAbove))
      if (settings.paymentRecoveryEnabled != null) setPaymentRecoveryEnabled(settings.paymentRecoveryEnabled)
      if (settings.attributionRetentionDays != null) {
        setAttributionRetentionDays(String(settings.attributionRetentionDays))
      } else {
        setAttributionRetentionDays("")
      }
      if (settings.aiGreeting != null && settings.aiGreeting.trim() !== '') setAiGreeting(settings.aiGreeting)
      if (settings.aiTone != null && settings.aiTone.trim() !== '') {
        const t = settings.aiTone.trim().toLowerCase()
        if (t === 'formal' || t === 'balanced' || t === 'casual') setAiTone(t)
      }
      if (settings.aiModelMode) setAiModelMode(settings.aiModelMode)
      if (settings.aiModelId) setAiModelId(settings.aiModelId)
      if (settings.aiReplyMode === 'ai_first' || settings.aiReplyMode === 'balanced') {
        setAiReplyMode(settings.aiReplyMode)
      }
      if (settings.aiCredentialMode === 'platform' || settings.aiCredentialMode === 'company' || settings.aiCredentialMode === 'company_preferred') {
        setAiCredentialMode(settings.aiCredentialMode)
      }
      if (settings.replyInCustomerLanguage != null) {
        setReplyInCustomerLanguage(settings.replyInCustomerLanguage)
      }
      if (settings.defaultReplyLanguage != null) {
        setDefaultReplyLanguage(settings.defaultReplyLanguage)
      }
      if (settings.aiPlanCapabilities) {
        setAiPlanCapabilities(settings.aiPlanCapabilities)
      }
      if (settings.effectiveAiModelMode && settings.aiModelMode !== settings.effectiveAiModelMode) {
        setAiModelMode(settings.effectiveAiModelMode as 'auto' | 'platform_default' | 'specific')
        if (settings.effectiveAiModelMode !== 'specific') {
          setAiModelId('')
        }
      }
      if (settings.autoReplyEnabled != null) setAutoReplyEnabled(settings.autoReplyEnabled)
      if (settings.agentCommerceEnabled != null) setAgentCommerceEnabled(settings.agentCommerceEnabled)
      if (settings.agentProactiveEnabled != null) setAgentProactiveEnabled(settings.agentProactiveEnabled)
      if (settings.agentVoiceReplyEnabled != null) setAgentVoiceReplyEnabled(settings.agentVoiceReplyEnabled)
      if (settings.agentVoiceReplyMode != null) setAgentVoiceReplyMode(settings.agentVoiceReplyMode)
      if (settings.agentVoiceId != null) setAgentVoiceId(settings.agentVoiceId)
      if (settings.agentMorningBriefWhatsappEnabled != null) {
        setAgentMorningBriefWhatsappEnabled(settings.agentMorningBriefWhatsappEnabled)
      }
      if (settings.ownerWhatsappPhone != null) setOwnerWhatsappPhone(settings.ownerWhatsappPhone)
      if (settings.webWidgetToken != null) setWebWidgetToken(settings.webWidgetToken)
      if (settings.channelIngestSecret != null) setChannelIngestSecret(settings.channelIngestSecret)
      if (settings.channelWebhookUrls) setChannelWebhookUrls(settings.channelWebhookUrls)
      if (settings.widgetScriptUrl) setWidgetScriptUrl(settings.widgetScriptUrl)
      if (settings.companyId != null) setCompanyIdForEmbed(settings.companyId)
      if (settings.agentBusinessGoals) setAgentBusinessGoals(settings.agentBusinessGoals)
      if (settings.agentBusinessGoalCatalog) setAgentBusinessGoalCatalog(settings.agentBusinessGoalCatalog)
      if (settings.businessDnaPresets) setBusinessDnaPresets(settings.businessDnaPresets)
      if (settings.businessDna) setBusinessDna(settings.businessDna)
      if (settings.businessDnaCustom != null) {
        setBusinessDnaPreset(settings.businessDnaCustom ? 'custom' : 'industry_default')
      }
      if (settings.digitalTwin) setDigitalTwin(settings.digitalTwin)
      if (settings.agentCouncilEnabled != null) setAgentCouncilEnabled(settings.agentCouncilEnabled)
      if (settings.learnFromConversations != null) setLearnFromConversations(settings.learnFromConversations)
      if (settings.devModeEnabled != null) setDevModeEnabled(settings.devModeEnabled)
      if (settings.learnFromConversationsEditable != null) {
        setLearnFromConversationsEditable(settings.learnFromConversationsEditable)
      }
      if (settings.notificationsEnabled != null) setNotificationsEnabled(settings.notificationsEnabled)

      if (settings.ordersAcceptMpesa != null) setOrdersAcceptMpesa(settings.ordersAcceptMpesa)
      if (settings.ordersAcceptStripe != null) setOrdersAcceptStripe(settings.ordersAcceptStripe)
      if (settings.ordersAcceptPaystack != null) setOrdersAcceptPaystack(settings.ordersAcceptPaystack)
      if (settings.ordersAcceptPesapal != null) setOrdersAcceptPesapal(settings.ordersAcceptPesapal)
      if (settings.ordersAcceptFlutterwave != null) setOrdersAcceptFlutterwave(settings.ordersAcceptFlutterwave)
      if (settings.ordersAcceptPayPal != null) setOrdersAcceptPayPal(settings.ordersAcceptPayPal)
      if (settings.ordersAcceptCod != null) setOrdersAcceptCod(settings.ordersAcceptCod)

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
        if (st.env === "production" || st.env === "sandbox") setStripeEnv(st.env)
      } else if (settings.orderPaymentStripeConfigured === false) {
        setStripeSecret("")
        setStripeCurrency("kes")
        setStripeEnv("sandbox")
      }
      const ps = settings.orderPaymentPaystackConfig
      if (ps) {
        if (ps.secret_key != null && ps.secret_key !== "") setPaystackSecretKey(ps.secret_key)
        if (ps.public_key != null && ps.public_key !== "") setPaystackPublicKey(ps.public_key)
        if (ps.currency != null && ps.currency !== "") setPaystackCurrency(ps.currency)
        if (ps.env === "production" || ps.env === "sandbox") setPaystackEnv(ps.env)
      } else if (settings.orderPaymentPaystackConfigured === false) {
        setPaystackSecretKey("")
        setPaystackPublicKey("")
        setPaystackCurrency("kes")
        setPaystackEnv("sandbox")
      }
      const pes = settings.orderPaymentPesapalConfig
      if (pes) {
        if (pes.consumer_key != null && pes.consumer_key !== "") setPesapalConsumerKey(pes.consumer_key)
        if (pes.consumer_secret != null && pes.consumer_secret !== "") setPesapalConsumerSecret(pes.consumer_secret)
        if (pes.currency != null && pes.currency !== "") setPesapalCurrency(pes.currency)
        if (pes.env === "production" || pes.env === "sandbox") setPesapalEnv(pes.env)
      } else if (settings.orderPaymentPesapalConfigured === false) {
        setPesapalConsumerKey("")
        setPesapalConsumerSecret("")
        setPesapalCurrency("kes")
        setPesapalEnv("sandbox")
      }
      const flw = settings.orderPaymentFlutterwaveConfig
      if (flw) {
        if (flw.public_key != null && flw.public_key !== "") setFlutterwavePublicKey(flw.public_key)
        if (flw.secret_key != null && flw.secret_key !== "") setFlutterwaveSecretKey(flw.secret_key)
        if (flw.secret_hash != null && flw.secret_hash !== "") setFlutterwaveSecretHash(flw.secret_hash)
        if (flw.currency != null && flw.currency !== "") setFlutterwaveCurrency(flw.currency)
        if (flw.env === "production" || flw.env === "sandbox") setFlutterwaveEnv(flw.env)
      } else if (settings.orderPaymentFlutterwaveConfigured === false) {
        setFlutterwavePublicKey("")
        setFlutterwaveSecretKey("")
        setFlutterwaveSecretHash("")
        setFlutterwaveCurrency("kes")
        setFlutterwaveEnv("sandbox")
      }
      const ppl = settings.orderPaymentPayPalConfig
      if (ppl) {
        if (ppl.client_id != null && ppl.client_id !== "") setPaypalClientId(ppl.client_id)
        if (ppl.client_secret != null && ppl.client_secret !== "") setPaypalClientSecret(ppl.client_secret)
        if (ppl.currency != null && ppl.currency !== "") setPaypalCurrency(ppl.currency)
        if (ppl.env === "production" || ppl.env === "sandbox") setPaypalEnv(ppl.env)
      } else if (settings.orderPaymentPayPalConfigured === false) {
        setPaypalClientId("")
        setPaypalClientSecret("")
        setPaypalCurrency("usd")
        setPaypalEnv("sandbox")
      }
    }
  }, [settings])

  useEffect(() => {
    if (activeTab !== 'ai') return
    apiRequest<{ models: Array<{ id: string; displayName: string; provider: string; inputCostPerMillion: number; outputCostPerMillion: number }> }>(
      '/api/company/ai-models'
    )
      .then((data) => setAvailableAiModels(data.models ?? []))
      .catch(() => setAvailableAiModels([]))
  }, [activeTab])

  useEffect(() => {
    if (activeTab !== 'ai') return
    getCompanyAiProviders()
      .then((data) => {
        if (data.credentialMode) {
          setAiCredentialMode(data.credentialMode as 'platform' | 'company' | 'company_preferred')
        }
        if (data.aiPlanCapabilities) {
          setAiPlanCapabilities(data.aiPlanCapabilities)
        }
        const openai = data.providers?.find((p) => p.slug === 'openai')
        setOpenaiKeyConfigured(!!openai?.apiKeyConfigured)
      })
      .catch(() => { })
    getCompanyAiUsage('30d')
      .then((data) => {
        setAiUsageSummary(data.summary)
        setAiUsageExtras({
          byCredentialSource: data.byCredentialSource,
          learningEmbeddingCoveragePercent: data.learningEmbeddingCoveragePercent,
        })
      })
      .catch(() => {
        setAiUsageSummary(null)
        setAiUsageExtras(null)
      })
  }, [activeTab])

  const handleByokSave = async () => {
    setByokSaving(true)
    const payload: { credentialMode: string; apiKey?: string } = { credentialMode: aiCredentialMode }
    if (openaiApiKey.trim()) payload.apiKey = openaiApiKey.trim()
    const result = await updateCompanyAiProvider('openai', payload)
    setByokSaving(false)
    if (result.success) {
      setOpenaiApiKey('')
      setOpenaiKeyConfigured(true)
      getCompanyAiUsage('30d').then((data) => setAiUsageSummary(data.summary)).catch(() => { })
    }
    setAiMessage(result.success ? 'API key settings saved.' : (result.message ?? 'Failed to save API key.'))
  }

  const applyBusinessDnaPreset = (key: 'industry_default' | 'luxury_brand' | 'friendly_cafe' | 'custom') => {
    setBusinessDnaPreset(key)
    if (key === 'industry_default') {
      setBusinessDna({})
      return
    }
    if (key === 'custom') {
      return
    }
    const preset = businessDnaPresets[key]
    if (preset) {
      const { label: _l, description: _d, ...dna } = preset
      setBusinessDna(dna)
    }
  }

  const businessDnaPayload = (): BusinessDnaSettings | null => {
    if (businessDnaPreset === 'industry_default') {
      return null
    }
    return {
      tone: businessDna.tone?.trim() || undefined,
      values: businessDna.values?.filter((v) => v.trim() !== '') ?? undefined,
      risk_tolerance: businessDna.risk_tolerance,
      service_philosophy: businessDna.service_philosophy?.trim() || undefined,
      escalation_culture: businessDna.escalation_culture?.trim() || undefined,
      communication_style: businessDna.communication_style?.trim() || undefined,
    }
  }

  const handleAiSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setAiMessage(null)
    setAiSaving(true)
    const result = await updateSettings({
      aiGreeting: aiGreeting.trim(),
      aiTone: aiTone.trim(),
      aiModelMode,
      aiModelId: aiModelMode === 'specific' && aiModelId ? aiModelId : null,
      aiReplyMode,
      replyInCustomerLanguage,
      defaultReplyLanguage: defaultReplyLanguage.trim() || null,
      autoReplyEnabled,
      agentCommerceEnabled,
      agentProactiveEnabled,
      agentVoiceReplyEnabled,
      agentVoiceReplyMode,
      agentVoiceId,
      agentMorningBriefWhatsappEnabled,
      ownerWhatsappPhone: ownerWhatsappPhone.trim() || null,
      agentBusinessGoals,
      businessDna: businessDnaPayload(),
      digitalTwin: Object.keys(digitalTwin).length > 0 ? digitalTwin : null,
      agentCouncilEnabled,
      learnFromConversations,
      devModeEnabled,
      notificationsEnabled,
    })
    setAiSaving(false)
    setAiMessage(result.success ? 'AI settings saved.' : (result.message ?? 'Failed to save.'))
    if (result.success) mutate('company-settings')
  }

  const handleSaveAiModelAndTone = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setAiModelError(null)
    setAiModelSuccess(false)
    setAiModelSaving(true)
    const result = await updateSettings({
      aiGreeting: aiGreeting.trim(),
      aiTone: aiTone.trim(),
      aiModelMode,
      aiModelId: aiModelMode === 'specific' && aiModelId ? aiModelId : null,
      aiReplyMode,
      autoReplyEnabled,
      replyInCustomerLanguage,
      defaultReplyLanguage: defaultReplyLanguage.trim() || null,
    })
    setAiModelSaving(false)
    if (!result.success) {
      setAiModelError(result.message ?? "Failed to save AI model & tone settings")
      return
    }
    setAiModelSuccess(true)
    setTimeout(() => setAiModelSuccess(false), 3000)
    mutate("company-settings")
  }

  const handleSaveAiCommerce = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setAiCommerceError(null)
    setAiCommerceSuccess(false)
    setAiCommerceSaving(true)
    const result = await updateSettings({
      agentCommerceEnabled,
      agentProactiveEnabled,
      agentMorningBriefWhatsappEnabled,
      ownerWhatsappPhone: ownerWhatsappPhone.trim() || null,
    })
    setAiCommerceSaving(false)
    if (!result.success) {
      setAiCommerceError(result.message ?? "Failed to save commerce agent settings")
      return
    }
    setAiCommerceSuccess(true)
    setTimeout(() => setAiCommerceSuccess(false), 3000)
    mutate("company-settings")
  }

  const handleSaveAiVoice = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setAiVoiceError(null)
    setAiVoiceSuccess(false)
    setAiVoiceSaving(true)
    const result = await updateSettings({
      agentVoiceReplyEnabled,
      agentVoiceReplyMode,
      agentVoiceId,
    })
    setAiVoiceSaving(false)
    if (!result.success) {
      setAiVoiceError(result.message ?? "Failed to save voice note settings")
      return
    }
    setAiVoiceSuccess(true)
    setTimeout(() => setAiVoiceSuccess(false), 3000)
    mutate("company-settings")
  }

  const handleSaveBusinessDna = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setBusinessDnaError(null)
    setBusinessDnaSuccess(false)
    setBusinessDnaSaving(true)
    const result = await updateSettings({
      agentBusinessGoals,
      businessDna: businessDnaPayload(),
      digitalTwin: Object.keys(digitalTwin).length > 0 ? digitalTwin : null,
    })
    setBusinessDnaSaving(false)
    if (!result.success) {
      setBusinessDnaError(result.message ?? "Failed to save Business DNA & Digital Twin")
      return
    }
    setBusinessDnaSuccess(true)
    setTimeout(() => setBusinessDnaSuccess(false), 3000)
    mutate("company-settings")
  }

  const handleSaveAiAdvanced = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setAiAdvancedError(null)
    setAiAdvancedSuccess(false)
    setAiAdvancedSaving(true)
    const result = await updateSettings({
      agentCouncilEnabled,
      learnFromConversations,
      devModeEnabled,
      notificationsEnabled,
    })
    setAiAdvancedSaving(false)
    if (!result.success) {
      setAiAdvancedError(result.message ?? "Failed to save advanced AI controls")
      return
    }
    setAiAdvancedSuccess(true)
    setTimeout(() => setAiAdvancedSuccess(false), 3000)
    mutate("company-settings")
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
      currencySymbol: currencySymbol.trim() || null,
      thousandsSeparator,
      decimalSeparator,
      timezone,
      industry,
      businessMode,
      enableProductsCatalog,
      enableBookings,
      enableDineIn,
      attributionRetentionDays: attributionRetentionDays.trim()
        ? Math.min(730, Math.max(30, parseInt(attributionRetentionDays, 10) || 365))
        : null,
    })
    setProfileSaving(false)
    if (!result.success) {
      setProfileError(result.message ?? "Failed to save")
      return
    }
    setProfileSuccess(true)
    mutate("company-settings")
  }

  const handleToggleOption = async (
    key: string,
    stateSetter: (val: boolean) => void,
    newVal: boolean,
    payloadKey: keyof Parameters<typeof updateSettings>[0]
  ) => {
    stateSetter(newVal)
    setOptionSavingState(key, true)
    const res = await updateSettings({ [payloadKey]: newVal })
    setOptionSavingState(key, false)
    if (res.success) {
      setOptionSavedState(key)
      mutate("company-settings")
    }
  }

  const handleSaveMpesaConfig = async () => {
    setOptionSavingState('mpesaConfig', true)
    const res = await updateSettings({
      orderPaymentMpesaConfig: {
        type: mpesaType,
        shortcode: mpesaShortcode.trim(),
        passkey: mpesaPasskey.trim(),
        consumer_key: mpesaConsumerKey.trim() || undefined,
        consumer_secret: mpesaConsumerSecret.trim() || undefined,
        env: mpesaEnv,
      }
    })
    setOptionSavingState('mpesaConfig', false)
    if (res.success) {
      setReplacingMpesaSecret({})
      setOptionSavedState('mpesaConfig')
      mutate("company-settings")
    }
  }

  const handleSaveStripeConfig = async () => {
    setOptionSavingState('stripeConfig', true)
    const res = await updateSettings({
      orderPaymentStripeConfig: {
        secret: stripeSecret.trim(),
        currency: stripeCurrency.trim() || "kes",
        env: stripeEnv,
      }
    })
    setOptionSavingState('stripeConfig', false)
    if (res.success) {
      setReplacingStripeSecret(false)
      setOptionSavedState('stripeConfig')
      mutate("company-settings")
    }
  }

  const handleSavePaystackConfig = async () => {
    setOptionSavingState('paystackConfig', true)
    const res = await updateSettings({
      ordersAcceptPaystack: true,
      orderPaymentPaystackConfig: {
        secret_key: paystackSecretKey,
        public_key: paystackPublicKey,
        currency: paystackCurrency.trim() || "kes",
        env: paystackEnv,
      },
    })
    setOptionSavingState('paystackConfig', false)
    if (res.success) {
      setOptionSavedState('paystackConfig')
      setReplacingPaystackSecret(false)
      mutate("company-settings")
    }
  }

  const handleClearPaystackConfig = async () => {
    setOptionSavingState('paystackConfig', true)
    const res = await updateSettings({
      orderPaymentPaystackConfig: null,
    })
    setOptionSavingState('paystackConfig', false)
    if (res.success) {
      setOptionSavedState('paystackConfig')
      setPaystackSecretKey("")
      setPaystackPublicKey("")
      setReplacingPaystackSecret(false)
      mutate("company-settings")
    }
  }

  const handleSavePesapalConfig = async () => {
    setOptionSavingState('pesapalConfig', true)
    const res = await updateSettings({
      ordersAcceptPesapal: true,
      orderPaymentPesapalConfig: {
        consumer_key: pesapalConsumerKey.trim(),
        consumer_secret: pesapalConsumerSecret.trim(),
        currency: pesapalCurrency.trim() || "kes",
        env: pesapalEnv,
      },
    })
    setOptionSavingState('pesapalConfig', false)
    if (res.success) {
      setOptionSavedState('pesapalConfig')
      setReplacingPesapalSecret(false)
      mutate("company-settings")
    }
  }

  const handleClearPesapalConfig = async () => {
    setOptionSavingState('pesapalConfig', true)
    const res = await updateSettings({
      orderPaymentPesapalConfig: null,
    })
    setOptionSavingState('pesapalConfig', false)
    if (res.success) {
      setOptionSavedState('pesapalConfig')
      setPesapalConsumerKey("")
      setPesapalConsumerSecret("")
      setReplacingPesapalSecret(false)
      mutate("company-settings")
    }
  }

  const handleSaveFlutterwaveConfig = async () => {
    setOptionSavingState('flutterwaveConfig', true)
    const res = await updateSettings({
      ordersAcceptFlutterwave: true,
      orderPaymentFlutterwaveConfig: {
        public_key: flutterwavePublicKey.trim(),
        secret_key: flutterwaveSecretKey.trim(),
        secret_hash: flutterwaveSecretHash.trim(),
        currency: flutterwaveCurrency.trim() || "kes",
        env: flutterwaveEnv,
      },
    })
    setOptionSavingState('flutterwaveConfig', false)
    if (res.success) {
      setOptionSavedState('flutterwaveConfig')
      setReplacingFlutterwaveSecret(false)
      mutate("company-settings")
    }
  }

  const handleClearFlutterwaveConfig = async () => {
    setOptionSavingState('flutterwaveConfig', true)
    const res = await updateSettings({
      orderPaymentFlutterwaveConfig: null,
    })
    setOptionSavingState('flutterwaveConfig', false)
    if (res.success) {
      setOptionSavedState('flutterwaveConfig')
      setFlutterwavePublicKey("")
      setFlutterwaveSecretKey("")
      setFlutterwaveSecretHash("")
      setReplacingFlutterwaveSecret(false)
      mutate("company-settings")
    }
  }

  const handleSavePayPalConfig = async () => {
    setOptionSavingState('paypalConfig', true)
    const res = await updateSettings({
      ordersAcceptPayPal: true,
      orderPaymentPayPalConfig: {
        client_id: paypalClientId.trim(),
        client_secret: paypalClientSecret.trim(),
        currency: paypalCurrency.trim() || "usd",
        env: paypalEnv,
      },
    })
    setOptionSavingState('paypalConfig', false)
    if (res.success) {
      setOptionSavedState('paypalConfig')
      setReplacingPayPalSecret(false)
      mutate("company-settings")
    }
  }

  const handleClearPayPalConfig = async () => {
    setOptionSavingState('paypalConfig', true)
    const res = await updateSettings({
      orderPaymentPayPalConfig: null,
    })
    setOptionSavingState('paypalConfig', false)
    if (res.success) {
      setOptionSavedState('paypalConfig')
      setPaypalClientId("")
      setPaypalClientSecret("")
      setReplacingPayPalSecret(false)
      mutate("company-settings")
    }
  }

  const handleSaveManualInstructions = async () => {
    setOptionSavingState('manualInstructions', true)
    const res = await updateSettings({
      orderPaymentManualInstructions: orderPaymentManualInstructions.trim() || null,
    })
    setOptionSavingState('manualInstructions', false)
    if (res.success) {
      setOptionSavedState('manualInstructions')
      mutate("company-settings")
    }
  }

  const handleSaveDeliveryFeesConfig = async () => {
    setOptionSavingState('deliveryFeesConfig', true)
    const res = await updateSettings({
      defaultDeliveryFee: defaultDeliveryFee.trim() ? parseFloat(defaultDeliveryFee) : undefined,
      freeDeliveryAbove: freeDeliveryAbove.trim() ? parseFloat(freeDeliveryAbove) : null,
    })
    setOptionSavingState('deliveryFeesConfig', false)
    if (res.success) {
      setOptionSavedState('deliveryFeesConfig')
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
          <TabsTrigger value="branding" className="gap-2">
            <Palette className="h-4 w-4" />
            Brand &amp; Appearance
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

        {/* Brand & Appearance Studio */}
        <TabsContent value="branding">
          <BrandCustomizationCard
            initialLogo={settings?.logo}
            initialTheme={settings?.storefrontTheme}
            initialAnnouncementBar={settings?.storefrontAnnouncementBar}
            initialFooterText={settings?.storefrontTheme?.footer_text}
            businessName={businessName || settings?.companyName || 'My Brand'}
            storeSlug={settings?.storeSlug || ''}
          />
        </TabsContent>

        {/* Business Profile — API: PUT /api/company/settings (companyName, email, phone) */}
        <TabsContent value="profile">
          <form className="space-y-6" onSubmit={handleProfileSubmit}>
            {profileError && (
              <div className="rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                {profileError}
              </div>
            )}
            {profileSuccess && (
              <div className="rounded-lg border border-primary/50 bg-primary/10 px-4 py-3 text-sm text-primary">
                Settings saved successfully.
              </div>
            )}

            {/* Business Operating Mode & Capabilities */}
            <Card>
              <CardHeader>
                <div className="flex items-center gap-2">
                  <Zap className="h-5 w-5 text-primary" />
                  <div>
                    <CardTitle>Business Operating Mode & Capabilities</CardTitle>
                    <CardDescription>Choose how RelayIQ tailors your AI assistant, checkout flows, and dashboard modules</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="space-y-6">
                <div>
                  <FieldLabel className="mb-2 block">Quick Operating Preset</FieldLabel>
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <button
                      type="button"
                      onClick={() => {
                        setBusinessMode('retail')
                        setEnableProductsCatalog(true)
                        setEnableBookings(false)
                        setEnableDineIn(false)
                      }}
                      className={`p-3.5 rounded-lg border text-left transition-all ${
                        businessMode === 'retail'
                          ? 'border-primary bg-primary/5 ring-1 ring-primary'
                          : 'border-border hover:border-muted-foreground/50'
                      }`}
                    >
                      <div className="font-semibold text-sm flex items-center gap-1.5">
                        🛍️ Retail & E-Commerce
                      </div>
                      <p className="text-xs text-muted-foreground mt-1">
                        Physical & digital goods, shopping cart, delivery & shipping.
                      </p>
                    </button>

                    <button
                      type="button"
                      onClick={() => {
                        setBusinessMode('services')
                        setEnableProductsCatalog(true)
                        setEnableBookings(true)
                        setEnableDineIn(false)
                      }}
                      className={`p-3.5 rounded-lg border text-left transition-all ${
                        businessMode === 'services'
                          ? 'border-primary bg-primary/5 ring-1 ring-primary'
                          : 'border-border hover:border-muted-foreground/50'
                      }`}
                    >
                      <div className="font-semibold text-sm flex items-center gap-1.5">
                        📅 Services & Bookings
                      </div>
                      <p className="text-xs text-muted-foreground mt-1">
                        Appointments, service schedules, consultations, no shipping fees.
                      </p>
                    </button>

                    <button
                      type="button"
                      onClick={() => {
                        setBusinessMode('restaurant')
                        setEnableProductsCatalog(true)
                        setEnableBookings(false)
                        setEnableDineIn(true)
                      }}
                      className={`p-3.5 rounded-lg border text-left transition-all ${
                        businessMode === 'restaurant'
                          ? 'border-primary bg-primary/5 ring-1 ring-primary'
                          : 'border-border hover:border-muted-foreground/50'
                      }`}
                    >
                      <div className="font-semibold text-sm flex items-center gap-1.5">
                        🍽️ Restaurant & Dine-In
                      </div>
                      <p className="text-xs text-muted-foreground mt-1">
                        Food menu, table QR ordering, takeout, no address needed at table.
                      </p>
                    </button>

                    <button
                      type="button"
                      onClick={() => {
                        setBusinessMode('hybrid')
                        setEnableProductsCatalog(true)
                        setEnableBookings(true)
                        setEnableDineIn(true)
                      }}
                      className={`p-3.5 rounded-lg border text-left transition-all ${
                        businessMode === 'hybrid'
                          ? 'border-primary bg-primary/5 ring-1 ring-primary'
                          : 'border-border hover:border-muted-foreground/50'
                      }`}
                    >
                      <div className="font-semibold text-sm flex items-center gap-1.5">
                        ⚡ Hybrid / All Modules
                      </div>
                      <p className="text-xs text-muted-foreground mt-1">
                        Full platform power: products, appointments, and dine-in tables.
                      </p>
                    </button>
                  </div>
                </div>

                <div className="border-t pt-4 space-y-3">
                  <div className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                    Granular Capability Toggles
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <label className="flex items-center justify-between p-3 rounded-md border bg-muted/30">
                      <div>
                        <div className="text-sm font-medium">Products & Catalog</div>
                        <div className="text-xs text-muted-foreground">Sell physical/digital goods</div>
                      </div>
                      <Switch
                        checked={enableProductsCatalog}
                        onCheckedChange={(checked) => setEnableProductsCatalog(checked)}
                      />
                    </label>

                    <label className="flex items-center justify-between p-3 rounded-md border bg-muted/30">
                      <div>
                        <div className="text-sm font-medium">Service Bookings</div>
                        <div className="text-xs text-muted-foreground">Appointment scheduling</div>
                      </div>
                      <Switch
                        checked={enableBookings}
                        onCheckedChange={(checked) => setEnableBookings(checked)}
                      />
                    </label>

                    <label className="flex items-center justify-between p-3 rounded-md border bg-muted/30">
                      <div>
                        <div className="text-sm font-medium">Dine-In Table QR</div>
                        <div className="text-xs text-muted-foreground">Table ordering & tabs</div>
                      </div>
                      <Switch
                        checked={enableDineIn}
                        onCheckedChange={(checked) => setEnableDineIn(checked)}
                      />
                    </label>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Basic Information */}
            <Card>
              <CardHeader>
                <div className="flex items-center gap-2">
                  <Building2 className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <CardTitle>Basic Information</CardTitle>
                    <CardDescription>Manage your business identity and primary contact details</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid gap-4 md:grid-cols-2">
                  <Field>
                    <FieldLabel htmlFor="businessName">Business Name</FieldLabel>
                    <Input id="businessName" value={businessName} onChange={(e) => setBusinessName(e.target.value)} />
                  </Field>

                  <Field>
                    <FieldLabel htmlFor="industry">Industry</FieldLabel>
                    <Select value={industry} onValueChange={(v) => setIndustry(v as typeof industry)}>
                      <SelectTrigger id="industry">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="retail">Retail</SelectItem>
                        <SelectItem value="restaurant">Restaurant / Food</SelectItem>
                        <SelectItem value="services">Services</SelectItem>
                        <SelectItem value="other">Other</SelectItem>
                      </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground mt-1">
                      Used for CRM follow-up templates and portfolio insights matching.
                    </p>
                  </Field>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                  <Field>
                    <FieldLabel htmlFor="email">Email Address</FieldLabel>
                    <Input id="email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
                  </Field>
                  <Field>
                    <FieldLabel htmlFor="phone">Phone Number</FieldLabel>
                    <Input id="phone" value={phone} onChange={(e) => setPhone(e.target.value)} />
                  </Field>
                </div>

                <Field>
                  <FieldLabel htmlFor="address">Physical Address</FieldLabel>
                  <Textarea id="address" value={address} onChange={(e) => setAddress(e.target.value)} rows={2} />
                </Field>
              </CardContent>
            </Card>

            {/* Regional & Timezone */}
            <Card>
              <CardHeader>
                <div className="flex items-center gap-2">
                  <Clock className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <CardTitle>Regional & Timezone</CardTitle>
                    <CardDescription>Configure local time preferences for business hours and automated responses</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <Field className="max-w-md">
                  <FieldLabel htmlFor="timezone">Timezone</FieldLabel>
                  <Select value={timezone} onValueChange={setTimezone}>
                    <SelectTrigger id="timezone" className="w-full">
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
                  <p className="text-xs text-muted-foreground mt-1">
                    Used for business hours, away messages, and timestamps. Pick your region (e.g. Nairobi for East Africa Time, EAT).
                  </p>
                </Field>
              </CardContent>
            </Card>

            {/* Currency & Financial Formatting */}
            <Card>
              <CardHeader>
                <div className="flex items-center gap-2">
                  <Banknote className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <CardTitle>Currency & Formatting</CardTitle>
                    <CardDescription>Define how currency and price totals appear across catalogs and chat replies</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid gap-4 md:grid-cols-2">
                  <Field>
                    <FieldLabel htmlFor="displayCurrency">Catalog Currency</FieldLabel>
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
                    <p className="text-xs text-muted-foreground mt-1">
                      ISO 4217 code used in dashboard, WhatsApp catalog, and AI replies. Manage tax rates under{" "}
                      <a href="/dashboard/taxes" className="underline underline-offset-2">
                        Taxes
                      </a>
                      .
                    </p>
                  </Field>

                  <Field>
                    <FieldLabel htmlFor="currencySymbol">Currency Symbol (Optional)</FieldLabel>
                    <Input
                      id="currencySymbol"
                      value={currencySymbol}
                      onChange={(e) => setCurrencySymbol(e.target.value)}
                      placeholder="e.g. KSh"
                      maxLength={16}
                    />
                    <p className="text-xs text-muted-foreground mt-1">
                      Label shown before amounts. Leave empty to default to currency code.
                    </p>
                  </Field>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                  <Field>
                    <FieldLabel htmlFor="thousandsSeparator">Thousands Separator</FieldLabel>
                    <Select
                      value={thousandsSeparator}
                      onValueChange={(value) => {
                        setThousandsSeparator(value)
                        setDecimalSeparator(pairedDecimalForThousands(value))
                      }}
                    >
                      <SelectTrigger id="thousandsSeparator">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value=",">Comma (1,000.00)</SelectItem>
                        <SelectItem value=".">Dot (1.000,00)</SelectItem>
                        <SelectItem value=" ">Space (1 000,00)</SelectItem>
                        <SelectItem value="'">Apostrophe (1&apos;000.00)</SelectItem>
                      </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground mt-1">
                      Grouping mark for large amounts.
                    </p>
                  </Field>

                  <Field>
                    <FieldLabel htmlFor="decimalSeparator">Decimal Separator</FieldLabel>
                    <Select value={decimalSeparator} onValueChange={setDecimalSeparator}>
                      <SelectTrigger id="decimalSeparator">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value=".">Dot (…00.50)</SelectItem>
                        <SelectItem value=",">Comma (…00,50)</SelectItem>
                      </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground mt-1">
                      Mark before cents/fraction. Auto-updates when thousands style changes.
                    </p>
                  </Field>
                </div>

                <div className="rounded-lg border bg-muted/30 p-3.5 flex items-center justify-between text-sm">
                  <span className="text-muted-foreground font-medium">Format Preview:</span>
                  <span className="font-mono font-semibold text-foreground">
                    {formatCurrencyAmount(1234567.89, displayCurrency, {
                      symbol: currencySymbol || null,
                      thousandsSeparator,
                      decimalSeparator,
                    })}
                  </span>
                </div>
              </CardContent>
            </Card>

            {/* Data Retention */}
            <Card>
              <CardHeader>
                <div className="flex items-center gap-2">
                  <FileText className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <CardTitle>Data Retention</CardTitle>
                    <CardDescription>Configure storage retention duration for social attribution data</CardDescription>
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <Field className="max-w-md">
                  <FieldLabel htmlFor="attributionRetentionDays">Attribution Data Retention (Days)</FieldLabel>
                  <Input
                    id="attributionRetentionDays"
                    type="number"
                    min={30}
                    max={730}
                    placeholder="e.g. 365"
                    value={attributionRetentionDays}
                    onChange={(e) => setAttributionRetentionDays(e.target.value)}
                  />
                  <p className="text-xs text-muted-foreground mt-1">
                    How long to keep social attribution data (30–730 days). Leave empty for platform default.
                  </p>
                </Field>
              </CardContent>
            </Card>

            <div className="flex justify-end pt-2">
              <Button type="submit" disabled={profileSaving} size="lg">
                {profileSaving ? "Saving..." : "Save Changes"}
              </Button>
            </div>
          </form>
        </TabsContent>

        <TabsContent value="whatsapp">
          <Card>
            <CardHeader>
              <CardTitle>WhatsApp Business</CardTitle>
              <CardDescription>
                {manualConnectEnabled
                  ? "Connect via Facebook (recommended) or paste Meta API credentials manually if your administrator enabled that option."
                  : "Connect WhatsApp with Facebook Embedded Signup — no Meta Developer account needed."}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              {waLoading && !waStatus ? (
                <p className="text-sm text-muted-foreground">Loading status…</p>
              ) : waStatus?.connected ? (
                <div className="space-y-4">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="default" className="gap-1">
                      <Check className="h-3 w-3" />
                      Connected
                    </Badge>
                    {waStatus.displayPhoneNumber && (
                      <span className="text-sm text-muted-foreground">{waStatus.displayPhoneNumber}</span>
                    )}
                    {waStatus.qualityRating && (
                      <Badge variant="outline">Quality: {waStatus.qualityRating}</Badge>
                    )}
                    {waStatus.displayNameStatus && (
                      <Badge variant="outline">Display name: {waStatus.displayNameStatus}</Badge>
                    )}
                  </div>
                  <div className="grid gap-2 text-sm text-muted-foreground sm:grid-cols-2">
                    <p>Webhook subscribed: {waStatus.webhookSubscribed ? "Yes" : "No"}</p>
                    <p>Phone registered: {waStatus.phoneRegistered ? "Yes" : "No"}</p>
                    {waStatus.connectedVia === "manual" && (
                      <>
                        <p>Company Meta App Secret: {waStatus.hasMetaAppSecret ? "Saved" : "Missing"}</p>
                        <p>Company verify token: {waStatus.hasWebhookVerifyToken ? "Saved" : "Missing"}</p>
                      </>
                    )}
                    {waStatus.metaBillingModel === "solution_partner" && waStatus.connectedVia !== "manual" && (
                      <p>Platform credit line: {waStatus.creditLineShared ? "Attached" : "Pending"}</p>
                    )}
                  </div>
                  {((!waStatus.webhookSubscribed) || (waStatus.connectedVia === "manual" && (!waStatus.hasMetaAppSecret || !waStatus.hasWebhookVerifyToken))) && (
                    <div className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 space-y-3">
                      <p className="text-sm text-foreground">
                        {waStatus.connectedVia === "manual" && (!waStatus.hasMetaAppSecret || !waStatus.hasWebhookVerifyToken)
                          ? "Inbound messages will not arrive until your Meta App Secret and webhook verify token are saved. Use credentials from your own Meta Developer app — not the platform/super-admin values."
                          : "Inbound messages will not arrive until the webhook is subscribed. This usually means the WhatsApp Business Account ID was missing during connect."}
                      </p>
                      {waStatus.onboardingError && (
                        <p className="text-xs text-muted-foreground">{waStatus.onboardingError}</p>
                      )}
                      {waStatus.connectedVia === "manual" && !waStatus.hasMetaAppSecret && (
                        <Field>
                          <FieldLabel htmlFor="waFixMetaAppSecret">Meta App Secret</FieldLabel>
                          <Input
                            id="waFixMetaAppSecret"
                            type="password"
                            value={waFixMetaAppSecret}
                            onChange={(e) => setWaFixMetaAppSecret(e.target.value)}
                            placeholder="From Meta → Your App → Settings → Basic"
                            required
                          />
                        </Field>
                      )}
                      {waStatus.connectedVia === "manual" && !waStatus.hasWebhookVerifyToken && (
                        <Field>
                          <FieldLabel htmlFor="waFixWebhookVerifyToken">Webhook verify token</FieldLabel>
                          <Input
                            id="waFixWebhookVerifyToken"
                            type="password"
                            value={waFixWebhookVerifyToken}
                            onChange={(e) => setWaFixWebhookVerifyToken(e.target.value)}
                            placeholder="Same token set in Meta → Webhooks"
                            required
                          />
                        </Field>
                      )}
                      <Button type="button" onClick={handleResubscribeWebhooks} disabled={waLoading}>
                        {waLoading ? "Subscribing…" : "Fix inbound messages"}
                      </Button>
                    </div>
                  )}
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
                /* DISCONNECTED: STEP-BY-STEP ONBOARDING WIZARD */
                <div className="space-y-6">
                  {/* Stepper adapts: Embedded skips Meta Developer prerequisites */}
                  <div className="rounded-lg border bg-muted/20 p-4">
                    <div className="flex items-center justify-between">
                      {(waMethod === "embedded"
                        ? [
                            { step: 1 as const, title: "Choose method", desc: "Recommended path" },
                            { step: 3 as const, title: "Connect Facebook", desc: "Authorize WhatsApp" },
                            { step: 4 as const, title: "Done", desc: "You're live" },
                          ]
                        : [
                            { step: 1 as const, title: "Select Method", desc: "OAuth vs Manual" },
                            { step: 2 as const, title: "Prerequisites", desc: "Readiness Check" },
                            { step: 3 as const, title: "Configuration", desc: "Credentials" },
                            { step: 4 as const, title: "Finish & Verify", desc: "Finalize Setup" },
                          ]
                      ).map((s, idx, arr) => {
                        const stepOrder = arr.map((x) => x.step)
                        const currentIdx = stepOrder.indexOf(waStep)
                        const thisIdx = idx
                        const isDone = currentIdx > thisIdx
                        const isActive = waStep === s.step
                        return (
                        <div key={s.step} className="flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => isDone && setWaStep(s.step)}
                            disabled={!isDone && !isActive}
                            className={`flex h-8 w-8 items-center justify-center rounded-full border text-xs font-semibold transition-colors ${
                              isActive
                                ? "border-foreground bg-foreground text-background"
                                : isDone
                                ? "border-muted-foreground bg-muted/40 text-foreground cursor-pointer"
                                : "border-border text-muted-foreground cursor-not-allowed opacity-60"
                            }`}
                          >
                            {isDone ? <Check className="h-4 w-4" /> : thisIdx + 1}
                          </button>
                          <div className="hidden sm:block text-left">
                            <p className={`text-xs font-medium ${isActive ? "text-foreground font-semibold" : "text-muted-foreground"}`}>{s.title}</p>
                            <p className="text-[10px] text-muted-foreground">{s.desc}</p>
                          </div>
                          {idx < arr.length - 1 && <ChevronRight className="h-4 w-4 text-muted-foreground/60 hidden md:block mx-1" />}
                        </div>
                        )
                      })}
                    </div>
                  </div>

                  {/* STEP 1: CHOOSE CONNECTION METHOD */}
                  {waStep === 1 && (
                    <div className="space-y-5">
                      <div>
                        <h3 className="text-base font-semibold text-foreground">Choose how to connect WhatsApp</h3>
                        <p className="text-xs text-muted-foreground mt-1">
                          Most businesses should use Facebook Embedded Signup — no Meta Developer account needed.
                        </p>
                      </div>

                      <div className={`grid gap-4 ${manualConnectEnabled ? "md:grid-cols-2" : "md:grid-cols-1"}`}>
                        {/* Method Option A: Embedded Signup */}
                        <div
                          onClick={() => setWaMethod("embedded")}
                          className={`rounded-lg border p-5 cursor-pointer transition-all space-y-3 ${
                            waMethod === "embedded"
                              ? "border-foreground bg-muted/30 shadow-sm"
                              : "border-border hover:border-muted-foreground/50"
                          }`}
                        >
                          <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                              <Smartphone className="h-5 w-5 text-foreground" />
                              <p className="text-sm font-semibold text-foreground">Facebook Embedded Signup</p>
                            </div>
                            <Badge variant="outline" className="text-[10px]">Recommended</Badge>
                          </div>
                          <p className="text-xs text-muted-foreground">
                            Fastest 2-minute setup. Log in with Facebook to select your Meta Business portfolio and verify your phone number via SMS.
                          </p>
                          <ul className="text-xs text-muted-foreground space-y-1 list-disc pl-4">
                            <li>No Meta Developer app required</li>
                            <li>Automatic webhook & API token setup</li>
                            <li>Direct OTP phone verification</li>
                          </ul>
                        </div>

                        {/* Method Option B: Manual Credentials — admin must enable */}
                        {manualConnectEnabled ? (
                        <div
                          onClick={() => setWaMethod("manual")}
                          className={`rounded-lg border p-5 cursor-pointer transition-all space-y-3 ${
                            waMethod === "manual"
                              ? "border-foreground bg-muted/30 shadow-sm"
                              : "border-border hover:border-muted-foreground/50"
                          }`}
                        >
                          <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                              <Settings2 className="h-5 w-5 text-foreground" />
                              <p className="text-sm font-semibold text-foreground">Manual Meta Developer Setup</p>
                            </div>
                            <Badge variant="outline" className="text-[10px]">Advanced</Badge>
                          </div>
                          <p className="text-xs text-muted-foreground">
                            For existing Meta Developer apps. Connect using your own System User Permanent Access Token, Phone Number ID, App Secret, and Webhook Verify Token.
                          </p>
                          <ul className="text-xs text-muted-foreground space-y-1 list-disc pl-4">
                            <li>Full control over custom Meta App</li>
                            <li>Custom Webhook Callback URL configuration</li>
                            <li>Includes simple PDF setup guide for non-developers</li>
                          </ul>
                        </div>
                        ) : null}
                      </div>

                      {waMethod === "embedded" ? (
                        <div className="rounded-lg border border-dashed bg-muted/10 p-4 text-xs text-muted-foreground space-y-2">
                          <p className="font-medium text-foreground">Before you continue</p>
                          <ul className="list-disc pl-4 space-y-1">
                            <li>Have a phone that can receive SMS / voice OTP</li>
                            <li>Use a Facebook account with admin access to your Business Portfolio</li>
                            <li>The number should not be logged into the regular WhatsApp mobile app</li>
                          </ul>
                        </div>
                      ) : null}

                      <div className="flex justify-end pt-3 border-t">
                        <Button
                          type="button"
                          onClick={() => setWaStep(waMethod === "embedded" ? 3 : 2)}
                          className="gap-1"
                        >
                          <span>
                            {waMethod === "embedded" ? "Continue with Facebook" : "Next: Prerequisites"}
                          </span>
                          <ChevronRight className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  )}

                  {/* STEP 2: PREREQUISITES — Manual path only (hidden when admin disabled manual) */}
                  {waStep === 2 && manualConnectEnabled && (
                    <div className="space-y-5">
                      <div>
                        <h3 className="text-base font-semibold text-foreground">Prerequisites & Readiness Checklist</h3>
                        <p className="text-xs text-muted-foreground mt-1">
                          Manual setup needs a Meta Developer account and API credentials. Embedded Signup customers can skip this.
                        </p>
                      </div>

                      <div className="rounded-lg border border-primary/20 bg-primary/5 p-3 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <p className="text-foreground">
                          Prefer the easy path? Switch to <strong>Facebook Embedded Signup</strong> — no developer account required.
                        </p>
                        <Button
                          type="button"
                          size="sm"
                          variant="outline"
                          onClick={() => {
                            setWaMethod("embedded")
                            setWaStep(3)
                          }}
                        >
                          Use Embedded Signup
                        </Button>
                      </div>

                      {/* Explicit Guide on How to Register a Free Meta Developer Account */}
                      <div className="rounded-lg border p-4 space-y-3 bg-muted/20">
                        <div className="flex items-center justify-between">
                          <p className="text-xs font-semibold text-foreground uppercase tracking-wider flex items-center gap-1.5">
                            <ExternalLink className="h-3.5 w-3.5" />
                            <span>How to Create a Free Meta Developer Account (60 Seconds)</span>
                          </p>
                          <a
                            href="/docs/Meta_Developer_WhatsApp_Setup_Guide.pdf"
                            target="_blank"
                            rel="noreferrer"
                            download="Meta_Developer_WhatsApp_Setup_Guide.pdf"
                            className="inline-flex items-center gap-1 text-[11px] font-medium text-foreground underline underline-offset-2 hover:text-primary shrink-0"
                          >
                            <FileText className="h-3 w-3" />
                            <span>Download Full PDF Guide</span>
                          </a>
                        </div>
                        
                        <p className="text-xs text-muted-foreground">
                          Creating a Meta Developer account does <strong>NOT</strong> require any programming skills! It is simply a free feature by Meta that lets business owners connect automated tools to their official WhatsApp account using their regular Facebook login.
                        </p>

                        <div className="rounded border bg-background p-3 text-xs space-y-2 text-muted-foreground">
                          <ol className="list-decimal pl-4 space-y-1 text-foreground">
                            <li>Go to <a href="https://developers.facebook.com" target="_blank" rel="noreferrer" className="underline font-medium">developers.facebook.com</a> in your browser.</li>
                            <li>Click <strong>Log In</strong> in the top right using your normal personal Facebook account.</li>
                            <li>Click <strong>Get Started</strong> (or Register), accept the terms, and confirm your phone/email.</li>
                            <li>Select your role as <strong>Business Owner</strong> or <strong>Administrator</strong> and finish.</li>
                          </ol>
                        </div>
                      </div>

                      <div className="rounded-lg border p-4 space-y-4 bg-muted/10">
                        <p className="text-xs font-semibold text-foreground uppercase tracking-wider">Required Readiness Checks</p>
                        
                        <div className="space-y-3 text-sm">
                          <label className="flex items-start gap-3 cursor-pointer">
                            <input
                              type="checkbox"
                              checked={waChecklist.phoneReady}
                              onChange={(e) => setWaChecklist((prev) => ({ ...prev, phoneReady: e.target.checked }))}
                              className="mt-1 h-4 w-4 rounded border-border"
                            />
                            <div>
                              <p className="font-medium text-foreground">Phone Number SMS / Voice OTP Ready</p>
                              <p className="text-xs text-muted-foreground">
                                I have access to a phone number that can receive SMS or voice OTP calls during setup.
                              </p>
                            </div>
                          </label>

                          <label className="flex items-start gap-3 cursor-pointer">
                            <input
                              type="checkbox"
                              checked={waChecklist.noConflict}
                              onChange={(e) => setWaChecklist((prev) => ({ ...prev, noConflict: e.target.checked }))}
                              className="mt-1 h-4 w-4 rounded border-border"
                            />
                            <div>
                              <p className="font-medium text-foreground">Mobile WhatsApp App Disconnected</p>
                              <p className="text-xs text-muted-foreground">
                                This number is NOT currently logged into standard mobile WhatsApp app (or has been deleted/migrated to Cloud API).
                              </p>
                            </div>
                          </label>

                          <label className="flex items-start gap-3 cursor-pointer">
                            <input
                              type="checkbox"
                              checked={waChecklist.metaAccess}
                              onChange={(e) => setWaChecklist((prev) => ({ ...prev, metaAccess: e.target.checked }))}
                              className="mt-1 h-4 w-4 rounded border-border"
                            />
                            <div>
                              <p className="font-medium text-foreground">Meta Account Admin Access</p>
                              <p className="text-xs text-muted-foreground">
                                I have registered a free Meta Developer account using my Facebook login.
                              </p>
                            </div>
                          </label>
                        </div>
                      </div>

                      <div className="rounded-lg border p-4 space-y-2 bg-muted/20 text-xs text-muted-foreground">
                        <p className="font-medium text-foreground">Important Note on Phone Numbers:</p>
                        <p>
                          WhatsApp Cloud API cannot share a number simultaneously with the iOS/Android WhatsApp app. If your number is on standard WhatsApp, delete your mobile WhatsApp account first under Settings → Account → Delete My Account before starting verification.
                        </p>
                      </div>

                      <div className="flex items-center justify-between pt-3 border-t">
                        <Button type="button" variant="outline" onClick={() => setWaStep(1)} className="gap-1">
                          <ChevronLeft className="h-4 w-4" />
                          <span>Back</span>
                        </Button>
                        <Button
                          type="button"
                          onClick={() => setWaStep(3)}
                          disabled={!waChecklist.phoneReady || !waChecklist.metaAccess || !waChecklist.noConflict}
                          className="gap-1"
                        >
                          <span>Proceed to Configuration</span>
                          <ChevronRight className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  )}

                  {/* STEP 3: CREDENTIALS & ACCOUNT SETUP */}
                  {waStep === 3 && (
                    <div className="space-y-5">
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b">
                        <div>
                          <h3 className="text-base font-semibold text-foreground">
                            {waMethod === "embedded" ? "Connect with Facebook" : "Enter Meta API Credentials"}
                          </h3>
                          <p className="text-xs text-muted-foreground mt-0.5">
                            {waMethod === "embedded"
                              ? "One click opens Meta’s official window — sign in, pick your WhatsApp number, and verify with SMS."
                              : "Input your custom Meta Developer app credentials below."}
                          </p>
                        </div>
                        {waMethod === "manual" ? (
                        <div className="inline-flex rounded-md border bg-muted/30 p-1 shrink-0 self-start sm:self-auto">
                          <button
                            type="button"
                            onClick={() => setWaMethod("embedded")}
                            className={`px-3 py-1 text-xs font-medium rounded transition-colors ${
                              (waMethod as string) === "embedded"
                                ? "bg-background text-foreground shadow-sm"
                                : "text-muted-foreground hover:text-foreground"
                            }`}
                          >
                            Facebook OAuth
                          </button>
                          <button
                            type="button"
                            onClick={() => setWaMethod("manual")}
                            className={`px-3 py-1 text-xs font-medium rounded transition-colors ${
                              (waMethod as string) === "manual"
                                ? "bg-background text-foreground shadow-sm"
                                : "text-muted-foreground hover:text-foreground"
                            }`}
                          >
                            Manual Setup
                          </button>
                        </div>
                        ) : null}
                      </div>

                      {waMethod === "embedded" ? (
                        <div className="rounded-lg border p-5 space-y-4 bg-muted/10">
                          <div className="space-y-2">
                            <p className="text-sm font-medium text-foreground">What happens next</p>
                            <ol className="text-xs text-muted-foreground list-decimal pl-5 space-y-1">
                              <li>Log in with your Facebook account.</li>
                              <li>Select your Business Portfolio and WhatsApp Business Profile.</li>
                              <li>Enter your phone number and verify via 6-digit SMS OTP.</li>
                            </ol>
                            <p className="text-xs text-muted-foreground pt-1">
                              Tip: allow pop-ups for this site. After Meta finishes, we connect automatically — no tokens to copy.
                            </p>
                          </div>

                          <div className="pt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t">
                            <div className="flex items-center gap-2">
                              <Button type="button" variant="outline" onClick={() => setWaStep(1)} className="gap-1">
                                <ChevronLeft className="h-4 w-4" />
                                <span>Back</span>
                              </Button>
                              <Button
                                type="button"
                                onClick={handleEmbeddedSignup}
                                disabled={waEmbeddedLoading || waStatus?.platformBillingReady === false}
                                className="gap-2"
                              >
                                <Smartphone className="h-4 w-4" />
                                {waEmbeddedLoading ? "Opening Meta Window…" : "Continue with Facebook"}
                              </Button>
                            </div>
                            {manualConnectEnabled ? (
                            <button
                              type="button"
                              onClick={() => {
                                setWaMethod("manual")
                                setWaStep(2)
                              }}
                              className="text-xs text-muted-foreground underline hover:text-foreground text-left sm:text-right"
                            >
                              Need a custom Meta Developer app? Use Manual Setup →
                            </button>
                            ) : null}
                          </div>
                        </div>
                      ) : manualConnectEnabled ? (
                        <form onSubmit={handleManualConnect} className="space-y-5 border rounded-lg p-5 bg-background">
                          {/* PDF Guide Callout */}
                          <div className="rounded-md border bg-muted/20 p-3 flex items-center justify-between gap-3 text-xs">
                            <div className="space-y-0.5">
                              <p className="font-semibold text-foreground flex items-center gap-1.5">
                                <FileText className="h-3.5 w-3.5 text-muted-foreground" />
                                <span>Non-Developer Setup Guide (PDF)</span>
                              </p>
                              <p className="text-muted-foreground">Step-by-step PDF guide explaining where to find Meta API credentials.</p>
                            </div>
                            <a
                              href="/docs/Meta_Developer_WhatsApp_Setup_Guide.pdf"
                              target="_blank"
                              rel="noreferrer"
                              download="Meta_Developer_WhatsApp_Setup_Guide.pdf"
                              className="inline-flex items-center gap-1 rounded border bg-background px-2.5 py-1 font-medium text-foreground hover:bg-muted shrink-0"
                            >
                              <FileText className="h-3 w-3" />
                              <span>Download PDF</span>
                            </a>
                          </div>

                          {/* Section A: App Identifiers */}
                          <div className="space-y-4 rounded-lg border bg-muted/10 p-4">
                            <div className="flex items-center gap-2.5 pb-2 border-b border-border/60">
                              <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-foreground text-background text-xs font-bold font-mono">
                                A
                              </span>
                              <h4 className="text-sm font-bold text-foreground tracking-tight">Meta App & Phone Identifiers</h4>
                            </div>
                            <div className="grid gap-3 md:grid-cols-2">
                              <Field>
                                <FieldLabel htmlFor="waManualPhoneNumberId">Phone Number ID</FieldLabel>
                                <Input
                                  id="waManualPhoneNumberId"
                                  value={waManualPhoneNumberId}
                                  onChange={(e) => setWaManualPhoneNumberId(e.target.value)}
                                  placeholder="e.g. 10482019283719"
                                  required
                                />
                              </Field>

                              <Field>
                                <FieldLabel htmlFor="waManualWabaId">WhatsApp Business Account ID</FieldLabel>
                                <Input
                                  id="waManualWabaId"
                                  value={waManualWabaId}
                                  onChange={(e) => setWaManualWabaId(e.target.value)}
                                  placeholder="e.g. 10928374910283"
                                  required
                                />
                              </Field>
                            </div>
                            <div className="text-[11px] text-muted-foreground bg-background/80 p-2.5 rounded border">
                              <span className="font-semibold text-foreground">For Non-Developers:</span> <strong>Phone Number ID</strong> is Meta&apos;s code for your phone. <strong>WABA ID</strong> is your WhatsApp Business Account ID.
                            </div>
                          </div>

                          {/* Section B: Tokens & Secrets */}
                          <div className="space-y-4 rounded-lg border bg-muted/10 p-4">
                            <div className="flex items-center gap-2.5 pb-2 border-b border-border/60">
                              <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-foreground text-background text-xs font-bold font-mono">
                                B
                              </span>
                              <h4 className="text-sm font-bold text-foreground tracking-tight">System User Access Token & Meta App Secret</h4>
                            </div>
                            <div className="grid gap-3 md:grid-cols-2">
                              <Field>
                                <FieldLabel htmlFor="waManualAccessToken">Permanent System User Access Token</FieldLabel>
                                <Input
                                  id="waManualAccessToken"
                                  type="password"
                                  value={waManualAccessToken}
                                  onChange={(e) => setWaManualAccessToken(e.target.value)}
                                  placeholder="EAAG..."
                                  required
                                />
                              </Field>

                              <Field>
                                <FieldLabel htmlFor="waManualMetaAppSecret">Meta App Secret</FieldLabel>
                                <Input
                                  id="waManualMetaAppSecret"
                                  type="password"
                                  value={waManualMetaAppSecret}
                                  onChange={(e) => setWaManualMetaAppSecret(e.target.value)}
                                  placeholder="Meta App → Settings → Basic"
                                  required
                                />
                              </Field>
                            </div>
                            <div className="text-[11px] text-muted-foreground bg-background/80 p-2.5 rounded border">
                              <span className="font-semibold text-foreground">For Non-Developers:</span> <strong>Permanent System User Access Token</strong> is generated under Meta Business Settings → System Users. <strong>Meta App Secret</strong> is under App Settings → Basic.
                            </div>
                          </div>

                          {/* Section C: Webhook Configuration */}
                          <div className="space-y-4 rounded-lg border bg-muted/10 p-4">
                            <div className="flex items-center gap-2.5 pb-2 border-b border-border/60">
                              <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-foreground text-background text-xs font-bold font-mono">
                                C
                              </span>
                              <h4 className="text-sm font-bold text-foreground tracking-tight">Webhook Configuration</h4>
                            </div>

                            <Field>
                              <FieldLabel htmlFor="waManualWebhookVerifyToken">Webhook Verify Token</FieldLabel>
                              <Input
                                id="waManualWebhookVerifyToken"
                                type="password"
                                value={waManualWebhookVerifyToken}
                                onChange={(e) => setWaManualWebhookVerifyToken(e.target.value)}
                                placeholder="e.g. my_secret_key_123"
                                required
                              />
                            </Field>

                            <div className="space-y-1.5">
                              <FieldLabel>Webhook Callback URL (Paste into Meta Webhooks Console)</FieldLabel>
                              <div className="flex items-center justify-between rounded-md border bg-background p-2 text-xs font-mono">
                                <span className="truncate mr-2 select-all">{waStatus?.webhookUrl ?? "http://localhost:8080/api/whatsapp/webhook"}</span>
                                <Button
                                  type="button"
                                  variant="ghost"
                                  size="sm"
                                  className="h-7 px-2 gap-1 shrink-0"
                                  onClick={() => {
                                    const url = waStatus?.webhookUrl ?? "http://localhost:8080/api/whatsapp/webhook"
                                    navigator.clipboard.writeText(url)
                                    setCopiedWebhook(true)
                                    setTimeout(() => setCopiedWebhook(false), 2000)
                                  }}
                                >
                                  {copiedWebhook ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                                  <span>{copiedWebhook ? "Copied" : "Copy URL"}</span>
                                </Button>
                              </div>
                            </div>

                            <div className="text-[11px] text-muted-foreground bg-background/80 p-2.5 rounded border">
                              <span className="font-semibold text-foreground">For Non-Developers:</span> In Meta Developer Portal → Webhooks, paste the <strong>Callback URL</strong> above and enter the exact same <strong>Verify Token</strong> secret passphrase you typed here.
                            </div>
                          </div>

                          {/* Section D: Optional Details */}
                          <div className="space-y-4 rounded-lg border bg-muted/10 p-4">
                            <div className="flex items-center gap-2.5 pb-2 border-b border-border/60">
                              <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-muted-foreground/20 text-foreground text-xs font-bold font-mono">
                                D
                              </span>
                              <h4 className="text-sm font-bold text-foreground tracking-tight">Optional Details (Display Phone & 2FA PIN)</h4>
                            </div>
                            <div className="grid gap-3 md:grid-cols-2">
                              <Field>
                                <FieldLabel htmlFor="waManualDisplayPhone">Display Phone Number (Optional)</FieldLabel>
                                <Input
                                  id="waManualDisplayPhone"
                                  value={waManualDisplayPhone}
                                  onChange={(e) => setWaManualDisplayPhone(e.target.value)}
                                  placeholder="+254712345678"
                                />
                              </Field>

                              <Field>
                                <FieldLabel htmlFor="waManualRegistrationPin">2FA Verification PIN (Optional)</FieldLabel>
                                <Input
                                  id="waManualRegistrationPin"
                                  type="password"
                                  inputMode="numeric"
                                  autoComplete="one-time-code"
                                  maxLength={6}
                                  value={waManualRegistrationPin}
                                  onChange={(e) => setWaManualRegistrationPin(e.target.value.replace(/\D/g, "").slice(0, 6))}
                                  placeholder="6-digit PIN"
                                />
                              </Field>
                            </div>
                          </div>

                          <div className="flex items-center justify-between pt-2 border-t">
                            <Button type="button" variant="outline" onClick={() => setWaStep(2)} className="gap-1">
                              <ChevronLeft className="h-4 w-4" />
                              <span>Back</span>
                            </Button>
                            <Button type="submit" disabled={waManualLoading} className="gap-1">
                              <span>{waManualLoading ? "Connecting…" : "Connect Manually & Save"}</span>
                              <ChevronRight className="h-4 w-4" />
                            </Button>
                          </div>
                        </form>
                      ) : null}
                    </div>
                  )}

                  {/* STEP 4: FINISH & VERIFY */}
                  {waStep === 4 && (
                    <div className="space-y-4 pt-1">
                      <div>
                        <h4 className="text-sm font-semibold text-foreground">Step 4: Connection Completed</h4>
                        <p className="text-xs text-muted-foreground mt-0.5">
                          Your WhatsApp Business account is successfully connected.
                        </p>
                      </div>

                      <div className="rounded-lg border p-4 space-y-3 bg-muted/20">
                        <div className="flex items-center gap-2 text-foreground font-medium text-sm">
                          <Check className="h-4 w-4 text-emerald-500" />
                          <span>Status: Active & Connected</span>
                        </div>
                        <p className="text-xs text-muted-foreground">
                          You can now create message templates, run broadcast campaigns, and configure AI chatbot responses.
                        </p>
                      </div>
                    </div>
                  )}
                </div>
              )}
              {waMessage && (
                <p className={`text-sm ${waMessageError ? "text-destructive font-medium" : "text-muted-foreground"}`}>
                  {waMessage}
                </p>
              )}
              {waStatus?.onboardingError && (
                <p className="text-sm text-destructive">{waStatus.onboardingError}</p>
              )}
            </CardContent>
          </Card>

          {waStatus?.connected && (
            <Card className="mt-6">
              <CardHeader>
                <CardTitle>Message templates</CardTitle>
                <CardDescription>
                  Create and sync WhatsApp message templates for outbound marketing and notifications (Meta approval required).
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex flex-wrap gap-2">
                  <Button type="button" variant="outline" disabled={tplLoading} onClick={async () => {
                    setTplLoading(true)
                    const res = await syncWhatsAppTemplates()
                    setTplLoading(false)
                    setWaMessage(res.message ?? null)
                    if (res.success) await loadWhatsAppTemplates()
                  }}>
                    Sync from Meta
                  </Button>
                </div>
                <form className="space-y-3 border rounded-lg p-4" onSubmit={async (e) => {
                  e.preventDefault()
                  setTplLoading(true)
                  const res = await createWhatsAppTemplate({ name: tplName, body: tplBody, category: tplCategory })
                  setTplLoading(false)
                  setWaMessage(res.message ?? null)
                  if (res.success) {
                    setTplName("")
                    setTplBody("")
                    await loadWhatsAppTemplates()
                  }
                }}>
                  <Field>
                    <FieldLabel>Template name</FieldLabel>
                    <Input value={tplName} onChange={(e) => setTplName(e.target.value)} placeholder="order_update" required />
                  </Field>
                  <Field>
                    <FieldLabel>Category</FieldLabel>
                    <select className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={tplCategory} onChange={(e) => setTplCategory(e.target.value as typeof tplCategory)}>
                      <option value="utility">Utility</option>
                      <option value="marketing">Marketing</option>
                      <option value="authentication">Authentication</option>
                    </select>
                  </Field>
                  <Field>
                    <FieldLabel>Body text</FieldLabel>
                    <Textarea value={tplBody} onChange={(e) => setTplBody(e.target.value)} placeholder="Hello {{1}}, your order is ready." required rows={3} />
                  </Field>
                  <Button type="submit" disabled={tplLoading}>{tplLoading ? "Submitting…" : "Submit to Meta"}</Button>
                </form>
                {waTemplates.length > 0 ? (
                  <div className="rounded-lg border overflow-hidden">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Name</TableHead>
                          <TableHead>Status</TableHead>
                          <TableHead>Category</TableHead>
                          <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {waTemplates.map((t) => (
                          <TableRow key={t.id}>
                            <TableCell>
                              <div className="font-medium">{t.name}</div>
                              <div className="text-xs text-muted-foreground truncate max-w-xs">{t.bodyPreview}</div>
                            </TableCell>
                            <TableCell><Badge variant={t.status === "approved" ? "default" : "secondary"}>{t.status}</Badge></TableCell>
                            <TableCell>{t.category}</TableCell>
                            <TableCell className="text-right">
                              <Button type="button" variant="ghost" size="sm" onClick={async () => {
                                const res = await deleteWhatsAppTemplate(t.id)
                                setWaMessage(res.message ?? null)
                                if (res.success) await loadWhatsAppTemplates()
                              }}>Delete</Button>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                ) : (
                  <p className="text-sm text-muted-foreground">No templates yet. Create one or sync from Meta.</p>
                )}
              </CardContent>
            </Card>
          )}

          {waStatus?.connected && (
            <Card className="mt-6">
              <CardHeader>
                <CardTitle>WhatsApp campaigns</CardTitle>
                <CardDescription>
                  Send an approved template or poster image to customers who chatted with you. Marketing outside the 24h window requires approved templates.{" "}
                  <Link href="/dashboard/whatsapp/campaigns" className="underline font-medium text-primary">Open campaign wizard</Link>
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <Field>
                  <FieldLabel>Audience segment</FieldLabel>
                  <Select value={campaignSegment} onValueChange={(v) => setCampaignSegment(v as typeof campaignSegment)}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All customers</SelectItem>
                      <SelectItem value="recent">Active (last 30 days)</SelectItem>
                      <SelectItem value="inactive">Inactive (30+ days)</SelectItem>
                      <SelectItem value="ordered">Customers with orders</SelectItem>
                    </SelectContent>
                  </Select>
                </Field>
                <p className="text-sm text-muted-foreground">
                  Audience: {campaignAudience} unique customer phone numbers
                </p>
                <Field>
                  <FieldLabel>Approved template</FieldLabel>
                  <Select value={campaignTemplate} onValueChange={setCampaignTemplate}>
                    <SelectTrigger><SelectValue placeholder="Select template" /></SelectTrigger>
                    <SelectContent>
                      {waTemplates.filter((t) => t.status === "approved").map((t) => (
                        <SelectItem key={t.id} value={t.name}>{t.name}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </Field>
                <Button
                  type="button"
                  disabled={campaignSending || !campaignTemplate}
                  onClick={async () => {
                    setCampaignSending(true)
                    const res = await sendWhatsAppCampaign({ mode: "template", templateName: campaignTemplate, segment: campaignSegment })
                    setCampaignSending(false)
                    setWaMessage(res.message ?? null)
                  }}
                >
                  {campaignSending ? "Sending…" : `Send template to segment (${campaignAudience})`}
                </Button>
                <div className="border-t pt-4 space-y-3">
                  <Field>
                    <FieldLabel>Poster image URL</FieldLabel>
                    <Input value={campaignImageUrl} onChange={(e) => setCampaignImageUrl(e.target.value)} placeholder="https://…" />
                  </Field>
                  <Field>
                    <FieldLabel>Caption (optional)</FieldLabel>
                    <Textarea value={campaignCaption} onChange={(e) => setCampaignCaption(e.target.value)} rows={2} />
                  </Field>
                  <Button
                    type="button"
                    variant="outline"
                    disabled={campaignSending || !campaignImageUrl}
                    onClick={async () => {
                      setCampaignSending(true)
                      const res = await sendWhatsAppCampaign({
                        mode: "image",
                        imageUrl: campaignImageUrl,
                        caption: campaignCaption || undefined,
                        segment: campaignSegment,
                      })
                      setCampaignSending(false)
                      setWaMessage(res.message ?? null)
                    }}
                  >
                    Send poster to segment ({campaignAudience})
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}
        </TabsContent>

        {/* AI Settings — Unified Single Form with Clean Section Cards */}
        <TabsContent value="ai">
          <form onSubmit={handleAiSubmit} className="space-y-6">
            {aiMessage && (
              <p className={`text-sm p-3 rounded-md ${aiMessage.startsWith('AI settings saved') ? 'bg-emerald-500/10 text-emerald-600 font-medium border border-emerald-500/30' : 'bg-destructive/10 text-destructive font-medium border border-destructive/30'}`}>
                {aiMessage}
              </p>
            )}

            {/* CARD 1: AI MODEL & PERSONALITY */}
            <Card>
              <CardHeader className="cursor-pointer select-none" onClick={() => toggleSection('ai_card_1')}>
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Bot className="h-5 w-5 text-muted-foreground" />
                    <div>
                      <CardTitle>AI Model & Personality</CardTitle>
                      <CardDescription>Choose your AI model strategy, routing rules, and base persona</CardDescription>
                    </div>
                  </div>
                  <Button type="button" variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground shrink-0">
                    <ChevronDown className={`h-4 w-4 transition-transform duration-200 ${!collapsedSections['ai_card_1'] ? 'rotate-180' : ''}`} />
                  </Button>
                </div>
              </CardHeader>
              {!collapsedSections['ai_card_1'] && (
                <CardContent className="space-y-5">
                  <div className="grid gap-4 md:grid-cols-2">
                    <Field>
                      <FieldLabel htmlFor="aiModel">AI Model Selection Strategy</FieldLabel>
                      <Select
                        value={aiModelMode === 'specific' && aiModelId ? `model:${aiModelId}` : aiModelMode}
                        onValueChange={(v) => {
                          if (v === 'auto' || v === 'platform_default') {
                            setAiModelMode(v)
                            setAiModelId('')
                          } else if (v.startsWith('model:')) {
                            setAiModelMode('specific')
                            setAiModelId(v.replace('model:', ''))
                          }
                        }}
                      >
                        <SelectTrigger id="aiModel">
                          <SelectValue placeholder="Select model strategy" />
                        </SelectTrigger>
                        <SelectContent>
                          {(aiPlanCapabilities?.allowedModelModes ?? ['auto', 'platform_default', 'specific']).includes('auto') && (
                            <SelectItem value="auto">Auto (Best Value — lowest cost enabled model)</SelectItem>
                          )}
                          {(aiPlanCapabilities?.allowedModelModes ?? ['auto', 'platform_default', 'specific']).includes('platform_default') && (
                            <SelectItem value="platform_default">Platform Default Model</SelectItem>
                          )}
                          {(aiPlanCapabilities?.allowedModelModes ?? ['auto', 'platform_default', 'specific']).includes('specific') && availableAiModels.length > 0 && (
                            <SelectGroup>
                              <SelectLabel>Specific Model (Enterprise)</SelectLabel>
                              {availableAiModels.map((m) => (
                                <SelectItem key={m.id} value={`model:${m.id}`}>
                                  {m.displayName} ({m.provider}) — ${m.inputCostPerMillion.toFixed(2)}/${m.outputCostPerMillion.toFixed(2)} per 1M
                                </SelectItem>
                              ))}
                            </SelectGroup>
                          )}
                        </SelectContent>
                      </Select>
                    </Field>

                    <Field>
                      <FieldLabel htmlFor="aiReplyMode">Reply Routing Strategy</FieldLabel>
                      <Select value={aiReplyMode} onValueChange={(v) => setAiReplyMode(v as 'ai_first' | 'balanced')}>
                        <SelectTrigger id="aiReplyMode">
                          <SelectValue placeholder="Select routing mode" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="ai_first">AI-First (Recommended — AI generates replies informed by FAQs)</SelectItem>
                          <SelectItem value="balanced">Balanced (Keyword shortcuts & direct FAQ matches first)</SelectItem>
                        </SelectContent>
                      </Select>
                    </Field>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2">
                    <Field>
                      <FieldLabel htmlFor="personality">AI Greeting & Context Persona</FieldLabel>
                      <Textarea
                        id="personality"
                        value={aiGreeting}
                        onChange={(e) => setAiGreeting(e.target.value)}
                        rows={3}
                        placeholder="e.g. Welcome! I'm your assistant for QuickBite. How can I help you today?"
                      />
                    </Field>

                    <Field>
                      <FieldLabel htmlFor="responseStyle">Response Tone & Style</FieldLabel>
                      <Select value={aiTone} onValueChange={setAiTone}>
                        <SelectTrigger id="responseStyle">
                          <SelectValue placeholder="Select style" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="formal">Formal & Professional</SelectItem>
                          <SelectItem value="balanced">Balanced & Friendly</SelectItem>
                          <SelectItem value="casual">Casual & Conversational</SelectItem>
                        </SelectContent>
                      </Select>
                      <div className="mt-4 space-y-3">
                        <div className="flex items-center justify-between">
                          <div>
                            <p className="text-xs font-medium text-foreground">Auto-reply Messages</p>
                            <p className="text-[11px] text-muted-foreground">Enable automated AI replies across channels</p>
                          </div>
                          <Switch checked={autoReplyEnabled} onCheckedChange={setAutoReplyEnabled} />
                        </div>
                        <div className="flex items-center justify-between">
                          <div>
                            <p className="text-xs font-medium text-foreground">Reply in Customer&apos;s Language</p>
                            <p className="text-[11px] text-muted-foreground">Detect and match customer language</p>
                          </div>
                          <Switch checked={replyInCustomerLanguage} onCheckedChange={setReplyInCustomerLanguage} />
                        </div>
                      </div>
                    </Field>
                  </div>

                  {!replyInCustomerLanguage && (
                    <Field>
                      <FieldLabel>Default Fallback Language Code</FieldLabel>
                      <Input
                        value={defaultReplyLanguage}
                        onChange={(e) => setDefaultReplyLanguage(e.target.value)}
                        placeholder="en"
                      />
                    </Field>
                  )}
                </CardContent>
              )}
            </Card>

            {/* CARD 2: COMMERCE AI AGENT & AUTOMATION */}
            <Card>
              <CardHeader className="cursor-pointer select-none" onClick={() => toggleSection('ai_card_2')}>
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Zap className="h-5 w-5 text-muted-foreground" />
                    <div>
                      <CardTitle>Commerce AI Agent & Automation</CardTitle>
                      <CardDescription>Tool-using AI worker for product catalog, orders, and proactive outreach</CardDescription>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    {agentCommerceEnabled && (
                      <Badge variant="default" className="text-[10px] uppercase tracking-wide">
                        Agent Active
                      </Badge>
                    )}
                    <Button type="button" variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground shrink-0">
                      <ChevronDown className={`h-4 w-4 transition-transform duration-200 ${!collapsedSections['ai_card_2'] ? 'rotate-180' : ''}`} />
                    </Button>
                  </div>
                </div>
              </CardHeader>
              {!collapsedSections['ai_card_2'] && (
                <CardContent className="space-y-5">
                  <div className="rounded-lg border p-4 space-y-4 bg-muted/10">
                    <div className="flex items-start justify-between gap-4">
                      <div className="space-y-1">
                        <p className="font-semibold text-foreground text-sm">Agent Commerce Mode</p>
                        <p className="text-xs text-muted-foreground">
                          Empowers the AI employee to search products, process orders, check inventory, and issue refunds directly in chat.
                        </p>
                      </div>
                      <Switch checked={agentCommerceEnabled} onCheckedChange={setAgentCommerceEnabled} />
                    </div>

                    {agentCommerceEnabled && (
                      <div className="space-y-4 pt-3 border-t border-border/60">
                        <div className="flex items-center justify-between">
                          <div>
                            <p className="text-xs font-semibold text-foreground">Proactive Customer Outreach</p>
                            <p className="text-[11px] text-muted-foreground">Automatically follow up on abandoned carts and send payment confirmations</p>
                          </div>
                          <Switch checked={agentProactiveEnabled} onCheckedChange={setAgentProactiveEnabled} />
                        </div>

                        <div className="flex items-center justify-between">
                          <div>
                            <p className="text-xs font-semibold text-foreground">Morning Commerce Brief on WhatsApp</p>
                            <p className="text-[11px] text-muted-foreground">Receive daily 7:00 AM sales & AI performance summaries on WhatsApp</p>
                          </div>
                          <Switch
                            checked={agentMorningBriefWhatsappEnabled}
                            onCheckedChange={setAgentMorningBriefWhatsappEnabled}
                          />
                        </div>

                        {agentMorningBriefWhatsappEnabled && (
                          <Field>
                            <FieldLabel htmlFor="ownerWhatsappPhone">Owner WhatsApp Phone Number</FieldLabel>
                            <Input
                              id="ownerWhatsappPhone"
                              value={ownerWhatsappPhone}
                              onChange={(e) => setOwnerWhatsappPhone(e.target.value)}
                              placeholder="e.g. 254712345678"
                            />
                          </Field>
                        )}
                      </div>
                    )}
                  </div>

                  {webWidgetToken && (
                    <div className="rounded-lg border bg-muted/20 p-4 text-xs space-y-2">
                      <p className="font-semibold text-foreground">Web Chat Widget Token & Embed Code</p>
                      <p className="font-mono text-muted-foreground truncate">Token: {webWidgetToken}</p>
                      {widgetScriptUrl && companyIdForEmbed && (
                        <pre className="overflow-x-auto rounded bg-background p-2.5 text-[10px] font-mono whitespace-pre-wrap">{`<script
  src="${widgetScriptUrl}"
  data-company-id="${companyIdForEmbed}"
  data-widget-token="${webWidgetToken}"
  data-api-base="${typeof window !== "undefined" ? window.location.origin : ""}"
  async
></script>`}</pre>
                      )}
                    </div>
                  )}
                </CardContent>
              )}
            </Card>

            {/* CARD 3: VOICE NOTES & SPEECH SYNTHESIS */}
            <Card>
              <CardHeader className="cursor-pointer select-none" onClick={() => toggleSection('ai_card_3')}>
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Smartphone className="h-5 w-5 text-muted-foreground" />
                    <div>
                      <CardTitle>Voice Notes & Speech Synthesis (TTS)</CardTitle>
                      <CardDescription>Transcribe customer audio voice notes and reply with AI voice output</CardDescription>
                    </div>
                  </div>
                  <Button type="button" variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground shrink-0">
                    <ChevronDown className={`h-4 w-4 transition-transform duration-200 ${!collapsedSections['ai_card_3'] ? 'rotate-180' : ''}`} />
                  </Button>
                </div>
              </CardHeader>
              {!collapsedSections['ai_card_3'] && (
                <CardContent className="space-y-5">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="font-medium text-foreground text-sm">Enable Voice Note Replies</p>
                      <p className="text-xs text-muted-foreground">Transcribe voice notes from WhatsApp and generate spoken audio responses</p>
                    </div>
                    <Switch checked={agentVoiceReplyEnabled} onCheckedChange={setAgentVoiceReplyEnabled} />
                  </div>

                  {agentVoiceReplyEnabled && (
                    <div className="grid gap-4 md:grid-cols-2 p-4 rounded-lg bg-muted/20 border">
                      <Field>
                        <FieldLabel>Outbound Audio Reply Mode</FieldLabel>
                        <Select value={agentVoiceReplyMode} onValueChange={(v) => setAgentVoiceReplyMode(v as 'voice_only' | 'dual_text_and_voice' | 'text_only')}>
                          <SelectTrigger><SelectValue /></SelectTrigger>
                          <SelectContent>
                            <SelectItem value="dual_text_and_voice">Dual Mode (Text Message + Audio Voice Note)</SelectItem>
                            <SelectItem value="voice_only">Voice Note Audio Only</SelectItem>
                            <SelectItem value="text_only">Text Message Fallback Only</SelectItem>
                          </SelectContent>
                        </Select>
                      </Field>

                      <Field>
                        <FieldLabel>Brand Voice Persona / Speaker</FieldLabel>
                        <Select value={agentVoiceId} onValueChange={setAgentVoiceId}>
                          <SelectTrigger><SelectValue /></SelectTrigger>
                          <SelectContent>
                            <SelectItem value="nova">Nova (Warm & Professional Female)</SelectItem>
                            <SelectItem value="alloy">Alloy (Balanced Neutral)</SelectItem>
                            <SelectItem value="echo">Echo (Warm Male)</SelectItem>
                            <SelectItem value="fable">Fable (Expressive British)</SelectItem>
                            <SelectItem value="onyx">Onyx (Deep Authoritative Male)</SelectItem>
                            <SelectItem value="shimmer">Shimmer (Clear & Energetic Female)</SelectItem>
                          </SelectContent>
                        </Select>
                      </Field>
                    </div>
                  )}
                </CardContent>
              )}
            </Card>

            {/* CARD 4: BUSINESS DNA & DIGITAL TWIN */}
            <Card>
              <CardHeader className="cursor-pointer select-none" onClick={() => toggleSection('ai_card_4')}>
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Building2 className="h-5 w-5 text-muted-foreground" />
                    <div>
                      <CardTitle>Business DNA & Digital Twin</CardTitle>
                      <CardDescription>Define your brand philosophy, service values, and AI strategic modeling</CardDescription>
                    </div>
                  </div>
                  <Button type="button" variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground shrink-0">
                    <ChevronDown className={`h-4 w-4 transition-transform duration-200 ${!collapsedSections['ai_card_4'] ? 'rotate-180' : ''}`} />
                  </Button>
                </div>
              </CardHeader>
              {!collapsedSections['ai_card_4'] && (
                <CardContent className="space-y-6">
                  <OnboardingInterviewPanel
                    onComplete={() => {
                      mutate("company-settings")
                    }}
                  />

                  <Field>
                    <FieldLabel>Business DNA Preset</FieldLabel>
                    <Select
                      value={businessDnaPreset}
                      onValueChange={(v) =>
                        applyBusinessDnaPreset(v as 'industry_default' | 'luxury_brand' | 'friendly_cafe' | 'custom')
                      }
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Choose a personality" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="industry_default">
                          Industry Default ({industry})
                        </SelectItem>
                        {businessDnaPresets.luxury_brand && (
                          <SelectItem value="luxury_brand">
                            {businessDnaPresets.luxury_brand.label ?? 'Luxury Brand'}
                          </SelectItem>
                        )}
                        {businessDnaPresets.friendly_cafe && (
                          <SelectItem value="friendly_cafe">
                            {businessDnaPresets.friendly_cafe.label ?? 'Friendly Café'}
                          </SelectItem>
                        )}
                        <SelectItem value="custom">Custom Brand DNA</SelectItem>
                      </SelectContent>
                    </Select>
                  </Field>

                  {businessDnaPreset !== 'industry_default' && (
                    <div className="grid gap-4 md:grid-cols-2 rounded-lg border bg-muted/10 p-4">
                      <Field>
                        <FieldLabel>Brand Tone</FieldLabel>
                        <Input
                          value={businessDna.tone ?? ''}
                          onChange={(e) => {
                            setBusinessDnaPreset('custom')
                            setBusinessDna((d) => ({ ...d, tone: e.target.value }))
                          }}
                          placeholder="e.g. luxury, calm, high-end"
                        />
                      </Field>
                      <Field>
                        <FieldLabel>Risk Tolerance</FieldLabel>
                        <Select
                          value={businessDna.risk_tolerance ?? 'medium'}
                          onValueChange={(v) => {
                            setBusinessDnaPreset('custom')
                            setBusinessDna((d) => ({
                              ...d,
                              risk_tolerance: v as 'low' | 'medium' | 'high',
                            }))
                          }}
                        >
                          <SelectTrigger><SelectValue /></SelectTrigger>
                          <SelectContent>
                            <SelectItem value="low">Low — cautious, escalate early</SelectItem>
                            <SelectItem value="medium">Medium — balanced autonomous decisions</SelectItem>
                            <SelectItem value="high">High — max autonomy</SelectItem>
                          </SelectContent>
                        </Select>
                      </Field>
                      <Field className="md:col-span-2">
                        <FieldLabel>Core Values (comma-separated)</FieldLabel>
                        <Input
                          value={(businessDna.values ?? []).join(', ')}
                          onChange={(e) => {
                            setBusinessDnaPreset('custom')
                            setBusinessDna((d) => ({
                              ...d,
                              values: e.target.value.split(',').map((s) => s.trim()).filter(Boolean),
                            }))
                          }}
                          placeholder="quality, speed, discretion"
                        />
                      </Field>
                    </div>
                  )}

                  {Object.keys(agentBusinessGoalCatalog).length > 0 && (
                    <Field>
                      <FieldLabel>Target Business Goals</FieldLabel>
                      <div className="grid gap-2 sm:grid-cols-2 mt-1">
                        {Object.entries(agentBusinessGoalCatalog).map(([key, label]) => (
                          <label key={key} className="flex items-center gap-2 rounded border bg-background p-2 text-xs cursor-pointer">
                            <input
                              type="checkbox"
                              className="h-4 w-4 rounded border-border"
                              checked={agentBusinessGoals.includes(key)}
                              onChange={(e) => {
                                setAgentBusinessGoals((prev) =>
                                  e.target.checked ? [...prev, key] : prev.filter((g) => g !== key)
                                )
                              }}
                            />
                            <div>
                              <p className="font-medium text-foreground capitalize">{key.replace(/_/g, ' ')}</p>
                              <p className="text-[10px] text-muted-foreground">{label}</p>
                            </div>
                          </label>
                        ))}
                      </div>
                    </Field>
                  )}

                  <Field>
                    <FieldLabel>Digital Twin Context Modeling</FieldLabel>
                    <div className="grid gap-3 md:grid-cols-2 mt-1">
                      {(['mission', 'brand_voice', 'sales_strategy', 'pricing_rules', 'competitors', 'target_customers'] as const).map((key) => (
                        <Textarea
                          key={key}
                          rows={2}
                          placeholder={key.replace(/_/g, ' ').toUpperCase()}
                          value={digitalTwin[key] ?? ''}
                          onChange={(e) => setDigitalTwin((prev) => ({ ...prev, [key]: e.target.value }))}
                        />
                      ))}
                    </div>
                  </Field>
                </CardContent>
              )}
            </Card>

            {/* CARD 5: ADVANCED CONTROLS & GOVERNANCE */}
            <Card>
              <CardHeader className="cursor-pointer select-none" onClick={() => toggleSection('ai_card_5')}>
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Settings2 className="h-5 w-5 text-muted-foreground" />
                    <div>
                      <CardTitle>Advanced AI Controls & Governance</CardTitle>
                      <CardDescription>Specialist council debate, continuous learning memory, and prompt debugging</CardDescription>
                    </div>
                  </div>
                  <Button type="button" variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground shrink-0">
                    <ChevronDown className={`h-4 w-4 transition-transform duration-200 ${!collapsedSections['ai_card_5'] ? 'rotate-180' : ''}`} />
                  </Button>
                </div>
              </CardHeader>
              {!collapsedSections['ai_card_5'] && (
                <CardContent className="space-y-5">
                  <div className="space-y-4">
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="font-medium text-foreground text-sm">Agent Council Internal Debate</p>
                        <p className="text-xs text-muted-foreground">Enable specialist agent internal review before sending replies</p>
                      </div>
                      <Switch checked={agentCouncilEnabled} onCheckedChange={setAgentCouncilEnabled} />
                    </div>

                    <div className="flex items-center justify-between">
                      <div>
                        <p className="font-medium text-foreground text-sm">Learn from Past Conversations</p>
                        <p className="text-xs text-muted-foreground">Improve future AI replies using successful past exchanges</p>
                      </div>
                      <Switch
                        checked={learnFromConversations}
                        onCheckedChange={setLearnFromConversations}
                        disabled={!learnFromConversationsEditable}
                      />
                    </div>

                    <div className="flex items-center justify-between">
                      <div>
                        <p className="font-medium text-foreground text-sm">In-App & Team Notifications</p>
                        <p className="text-xs text-muted-foreground">Notify staff when human escalation occurs</p>
                      </div>
                      <Switch checked={notificationsEnabled} onCheckedChange={setNotificationsEnabled} />
                    </div>

                    <div className="flex items-center justify-between border-t pt-3">
                      <div>
                        <p className="font-medium text-foreground text-sm">Developer Mode & Prompt Debugger</p>
                        <p className="text-xs text-muted-foreground">Log raw LLM prompts and enable prompt inspector</p>
                      </div>
                      <Switch checked={devModeEnabled} onCheckedChange={setDevModeEnabled} />
                    </div>
                  </div>

                  <div className="rounded-lg border p-4 bg-muted/10 space-y-3">
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="text-xs font-semibold text-foreground">Learning Memory (GDPR Compliant)</p>
                        <p className="text-[11px] text-muted-foreground">Export stored conversation learning samples for compliance or fine-tuning</p>
                      </div>
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={async () => {
                          try {
                            const blob = await exportLearningSamples()
                            const url = URL.createObjectURL(blob)
                            const a = document.createElement("a")
                            a.href = url
                            a.download = `learning-samples-${new Date().toISOString().slice(0, 10)}.csv`
                            a.click()
                            URL.revokeObjectURL(url)
                          } catch {
                            setAiMessage("Export failed.")
                          }
                        }}
                      >
                        Export CSV
                      </Button>
                    </div>
                  </div>
                </CardContent>
              )}
            </Card>

            {/* SINGLE SAVE BUTTON AT THE BOTTOM OF THE AI TAB */}
            <div className="flex flex-col sm:flex-row items-center justify-between gap-4 rounded-lg border bg-background p-4 shadow-sm">
              <div>
                <p className="text-sm font-semibold text-foreground">Save All AI Settings</p>
                <p className="text-xs text-muted-foreground">Applies changes across models, commerce tools, voice output, and Business DNA.</p>
              </div>
              <Button type="submit" disabled={aiSaving} size="lg" className="w-full sm:w-auto">
                {aiSaving ? "Saving All AI Settings…" : "Save AI Settings"}
              </Button>
            </div>
          </form>
        </TabsContent>

        <TabsContent value="byok">
          <Card className="mt-6">
            <CardHeader>
              <CardTitle>Your AI API keys (BYOK)</CardTitle>
              <CardDescription>
                {aiPlanCapabilities?.allowByok
                  ? 'Use your own OpenAI key to avoid platform AI spend limits. Platform keys are used when mode is Platform or Company preferred.'
                  : 'Available on Professional and Enterprise plans. Upgrade to add your own OpenAI API key.'}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {!aiPlanCapabilities?.allowByok ? (
                <p className="text-sm text-muted-foreground">
                  Your {aiPlanCapabilities?.plan ?? 'current'} plan uses platform AI keys only ($
                  {aiPlanCapabilities && 'aiCostLimitUsd' in aiPlanCapabilities ? String((aiPlanCapabilities as { aiCostLimitUsd?: number }).aiCostLimitUsd ?? 5) : '5'}
                  /mo included).
                </p>
              ) : (
                <>
                  {aiUsageSummary && (
                    <div className="text-sm text-muted-foreground space-y-1">
                      <p>
                        This period: {String(aiUsageSummary.totalRequests ?? 0)} requests · platform billed $
                        {String(aiUsageSummary.platformBilledCostUsd ?? 0)}
                        {aiUsageSummary.platformCostLimitUsd != null
                          ? ` / $${String(aiUsageSummary.platformCostLimitUsd)} limit`
                          : ''}
                      </p>
                      {aiUsageExtras?.learningEmbeddingCoveragePercent != null && (
                        <p>Learning memory embedding coverage: {aiUsageExtras.learningEmbeddingCoveragePercent}%</p>
                      )}
                      {(aiUsageExtras?.byCredentialSource ?? []).map((row) => (
                        <p key={row.source}>
                          {row.source}: {row.requests} requests · ${row.billedCostUsd.toFixed(4)} billed
                        </p>
                      ))}
                    </div>
                  )}
                  <Field>
                    <FieldLabel>Credential mode</FieldLabel>
                    <Select value={aiCredentialMode} onValueChange={(v) => setAiCredentialMode(v as typeof aiCredentialMode)}>
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="platform">Platform keys only</SelectItem>
                        {(aiPlanCapabilities?.allowedCredentialModes ?? []).includes('company_preferred') && (
                          <SelectItem value="company_preferred">My key first, then platform</SelectItem>
                        )}
                        {(aiPlanCapabilities?.allowedCredentialModes ?? []).includes('company') && (
                          <SelectItem value="company">My keys only (required)</SelectItem>
                        )}
                      </SelectContent>
                    </Select>
                  </Field>
                  <Field>
                    <FieldLabel>OpenAI API key</FieldLabel>
                    <Input
                      type="password"
                      value={openaiApiKey}
                      onChange={(e) => setOpenaiApiKey(e.target.value)}
                      placeholder={openaiKeyConfigured ? '•••••••• (configured — enter to replace)' : 'sk-…'}
                    />
                  </Field>
                  <Button type="button" onClick={handleByokSave} disabled={byokSaving}>
                    {byokSaving ? 'Saving…' : 'Save API key settings'}
                  </Button>
                </>
              )}
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

        {/* Order Payments — cleanly separated payment methods with per-option save and check indicators */}
        <TabsContent value="order-payments" className="space-y-6">
          {/* Top Master Hero Card */}
          <Card className="shadow-sm">
            <CardContent className="p-6">
              <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div className="flex items-start gap-4">
                  <div className="rounded-xl p-3 bg-muted text-foreground shrink-0">
                    <CreditCard className="h-6 w-6" />
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <h2 className="text-xl font-semibold tracking-tight text-foreground">Collect Payment for Orders</h2>
                      {ordersCollectPaymentEnabled ? (
                        <Badge variant="default" className="gap-1 font-normal">
                          <Check className="h-3 w-3" /> Active & Collecting
                        </Badge>
                      ) : (
                        <Badge variant="secondary" className="font-normal">
                          Payments Paused
                        </Badge>
                      )}
                      {optionSaving['collectPayment'] && (
                        <Loader2 className="h-4 w-4 animate-spin text-muted-foreground ml-1" />
                      )}
                      {optionSaved['collectPayment'] && (
                        <Badge variant="outline" className="gap-1 font-medium text-xs">
                          <Check className="h-3 w-3" /> Saved
                        </Badge>
                      )}
                    </div>
                    <p className="text-sm text-muted-foreground mt-1">
                      Choose whether to collect payment after orders are placed. Turn off to automatically confirm orders without requiring upfront payment.
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-3 shrink-0 self-end md:self-center">
                  <span className="text-sm font-medium text-foreground">
                    {ordersCollectPaymentEnabled ? "Enabled" : "Disabled"}
                  </span>
                  <Switch
                    checked={ordersCollectPaymentEnabled}
                    onCheckedChange={(v) => handleToggleOption('collectPayment', setOrdersCollectPaymentEnabled, v, 'ordersCollectPaymentEnabled')}
                    disabled={optionSaving['collectPayment']}
                  />
                </div>
              </div>

              {!ordersCollectPaymentEnabled && (
                <div className="mt-4 p-3 rounded-lg bg-muted/50 border border-border text-xs text-muted-foreground flex items-center gap-2">
                  <AlertCircle className="h-4 w-4 shrink-0 text-muted-foreground" />
                  <span>Payment collection is turned off. The chatbot will skip payment options and confirm customer orders immediately.</span>
                </div>
              )}
            </CardContent>
          </Card>

          <div className="space-y-6">
            {/* Category 1: Digital & Mobile Payment Gateways */}
            <div className="space-y-4">
              <div>
                <h3 className="text-lg font-semibold text-foreground flex items-center gap-2">
                  <Smartphone className="h-5 w-5 text-muted-foreground" />
                  Digital & Mobile Payment Gateways
                </h3>
                <p className="text-sm text-muted-foreground">
                  Instant online checkout links and mobile money push notifications processed automatically.
                </p>
              </div>

              <div className="grid gap-4">
                {/* M-Pesa Payment Card */}
                <Card className={`transition-all duration-200 ${ordersAcceptMpesa ? 'bg-card shadow-sm' : 'opacity-85 bg-card/60'}`}>
                  <CardContent className="p-5 space-y-4">
                    <div className={`flex items-start justify-between gap-4 ${ordersAcceptMpesa ? 'cursor-pointer select-none' : ''}`} onClick={() => ordersAcceptMpesa && toggleSection('mpesa')}>
                      <div className="flex items-start gap-3">
                        <div className="rounded-lg p-2.5 bg-muted text-foreground shrink-0 mt-0.5">
                          <Smartphone className="h-5 w-5" />
                        </div>
                        <div>
                          <div className="flex items-center gap-2">
                            <h4 className="font-semibold text-foreground">M-Pesa (STK Push)</h4>
                            <Badge variant="outline" className="text-xs font-medium">
                              Mobile Money
                            </Badge>
                            {optionSaving['mpesaToggle'] && (
                              <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />
                            )}
                            {optionSaved['mpesaToggle'] && (
                              <Badge variant="outline" className="gap-1 font-medium text-xs">
                                <Check className="h-3 w-3" /> Saved
                              </Badge>
                            )}
                          </div>
                          <p className="text-sm text-muted-foreground mt-0.5">
                            Customer receives an instant M-Pesa payment prompt on their phone during WhatsApp checkout.
                          </p>
                        </div>
                      </div>
                      <div className="flex items-center gap-2 shrink-0" onClick={(e) => e.stopPropagation()}>
                        <Switch
                          checked={ordersAcceptMpesa}
                          onCheckedChange={(v) => handleToggleOption('mpesaToggle', setOrdersAcceptMpesa, v, 'ordersAcceptMpesa')}
                          disabled={!ordersCollectPaymentEnabled || optionSaving['mpesaToggle']}
                        />
                        {ordersAcceptMpesa && (
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-muted-foreground"
                            onClick={(e) => {
                              e.stopPropagation()
                              toggleSection('mpesa')
                            }}
                          >
                            <ChevronDown className={`h-4 w-4 transition-transform duration-200 ${!collapsedSections['mpesa'] ? 'rotate-180' : ''}`} />
                          </Button>
                        )}
                      </div>
                    </div>

                    {/* Integrated M-Pesa Custom Configuration Box */}
                    {!collapsedSections['mpesa'] && ordersAcceptMpesa && (
                      <div className="mt-4 pt-4 border-t border-border/60 rounded-lg bg-muted/40 p-4 space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                          <div className="flex items-center gap-2">
                            <Settings2 className="h-4 w-4 text-muted-foreground" />
                            <span className="text-sm font-semibold text-foreground">M-Pesa Account Credentials (Optional)</span>
                          </div>
                          {settings?.orderPaymentMpesaConfigured ? (
                            <div className="flex items-center gap-2">
                              <Badge variant="default" className="gap-1 font-normal text-xs"><Check className="h-3 w-3" /> Custom Credentials Active</Badge>
                              <Button type="button" variant="outline" size="sm" className="h-7 text-xs" onClick={handleClearMpesaConfig}>Clear</Button>
                            </div>
                          ) : (
                            <Badge variant="secondary" className="text-xs font-normal">Using Platform Default</Badge>
                          )}
                        </div>

                        <p className="text-xs text-muted-foreground">
                          Add your Lipa Na M-Pesa PayBill or Till number so payments go directly to your business bank or till.
                        </p>

                        <div className="grid gap-4 md:grid-cols-2">
                          <Field>
                            <FieldLabel>Account Type</FieldLabel>
                            <Select value={mpesaType} onValueChange={(v) => setMpesaType(v as 'paybill' | 'till')}>
                              <SelectTrigger><SelectValue /></SelectTrigger>
                              <SelectContent>
                                <SelectItem value="paybill">PayBill (Business Number)</SelectItem>
                                <SelectItem value="till">Till (Buy Goods & Services)</SelectItem>
                              </SelectContent>
                            </Select>
                          </Field>
                          <Field>
                            <FieldLabel>{mpesaType === 'till' ? 'Till Number' : 'PayBill Shortcode'}</FieldLabel>
                            <Input
                              placeholder={mpesaType === 'till' ? 'e.g. 123456' : 'e.g. 174379'}
                              value={mpesaShortcode}
                              onChange={(e) => setMpesaShortcode(e.target.value)}
                            />
                          </Field>
                        </div>

                        <Field>
                          <FieldLabel>Lipa Na M-Pesa Passkey</FieldLabel>
                          {isMasked(mpesaPasskey) && !replacingMpesaSecret[mpesaSecretKey("passkey")] ? (
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
                                Replace Passkey
                              </Button>
                            </div>
                          ) : (
                            <div className="space-y-1">
                              <Input
                                type="password"
                                placeholder="Enter Lipa Na M-Pesa Passkey"
                                value={mpesaPasskey}
                                onChange={(e) => setMpesaPasskey(e.target.value)}
                              />
                              {replacingMpesaSecret[mpesaSecretKey("passkey")] && (
                                <Button
                                  type="button"
                                  variant="ghost"
                                  size="sm"
                                  className="h-8 text-xs text-muted-foreground"
                                  onClick={() => {
                                    setReplacingMpesaSecret((p) => {
                                      const n = { ...p }
                                      delete n[mpesaSecretKey("passkey")]
                                      return n
                                    })
                                    mutate("company-settings")
                                  }}
                                >
                                  Cancel Replace
                                </Button>
                              )}
                            </div>
                          )}
                        </Field>

                        <div className="grid gap-4 md:grid-cols-2">
                          <Field>
                            <FieldLabel>Consumer Key (Optional)</FieldLabel>
                            <Input placeholder="Daraja Consumer Key" value={mpesaConsumerKey} onChange={(e) => setMpesaConsumerKey(e.target.value)} />
                          </Field>
                          <Field>
                            <FieldLabel>Consumer Secret (Optional)</FieldLabel>
                            {isMasked(mpesaConsumerSecret) && !replacingMpesaSecret[mpesaSecretKey("consumer_secret")] ? (
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
                            ) : (
                              <div className="space-y-1">
                                <Input
                                  type="password"
                                  placeholder="Daraja Consumer Secret"
                                  value={mpesaConsumerSecret}
                                  onChange={(e) => setMpesaConsumerSecret(e.target.value)}
                                />
                                {replacingMpesaSecret[mpesaSecretKey("consumer_secret")] && (
                                  <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 text-xs text-muted-foreground"
                                    onClick={() => {
                                      setReplacingMpesaSecret((p) => {
                                        const n = { ...p }
                                        delete n[mpesaSecretKey("consumer_secret")]
                                        return n
                                      })
                                      mutate("company-settings")
                                    }}
                                  >
                                    Cancel Replace
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
                              <SelectItem value="sandbox">Sandbox (Testing Environment)</SelectItem>
                              <SelectItem value="production">Production (Live Environment)</SelectItem>
                            </SelectContent>
                          </Select>
                        </Field>

                        <div className="pt-2 flex items-center justify-end gap-2 border-t border-border/40">
                          <Button
                            type="button"
                            size="sm"
                            disabled={optionSaving['mpesaConfig']}
                            onClick={handleSaveMpesaConfig}
                            className="gap-1.5"
                          >
                            {optionSaving['mpesaConfig'] ? (
                              <>
                                <Loader2 className="h-3.5 w-3.5 animate-spin" /> Saving…
                              </>
                            ) : optionSaved['mpesaConfig'] ? (
                              <>
                                <Check className="h-3.5 w-3.5 text-foreground" /> Saved!
                              </>
                            ) : (
                              <>
                                <Check className="h-3.5 w-3.5" /> Save M-Pesa Credentials
                              </>
                            )}
                          </Button>
                        </div>
                      </div>
                    )}
                  </CardContent>
                </Card>

                {/* Card (Stripe) Payment Method Card */}
                <Card className={`transition-all duration-200 ${ordersAcceptStripe ? 'bg-card shadow-sm' : 'opacity-85 bg-card/60'}`}>
                  <CardContent className="p-5 space-y-4">
                    <div className={`flex items-start justify-between gap-4 ${ordersAcceptStripe ? 'cursor-pointer select-none' : ''}`} onClick={() => ordersAcceptStripe && toggleSection('stripe')}>
                      <div className="flex items-start gap-3">
                        <div className="rounded-lg p-2.5 bg-muted text-foreground shrink-0 mt-0.5">
                          <CreditCard className="h-5 w-5" />
                        </div>
                        <div>
                          <div className="flex items-center gap-2">
                            <h4 className="font-semibold text-foreground">Card (Stripe)</h4>
                            <Badge variant="outline" className="text-xs font-medium">
                              Cards & Digital Wallets
                            </Badge>
                            {optionSaving['stripeToggle'] && (
                              <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />
                            )}
                            {optionSaved['stripeToggle'] && (
                              <Badge variant="outline" className="gap-1 font-medium text-xs">
                                <Check className="h-3 w-3" /> Saved
                              </Badge>
                            )}
                          </div>
                          <p className="text-sm text-muted-foreground mt-0.5">
                            Customer receives a checkout link to pay securely by Visa, Mastercard, or Apple Pay online.
                          </p>
                        </div>
                      </div>
                      <div className="flex items-center gap-2 shrink-0" onClick={(e) => e.stopPropagation()}>
                        <Switch
                          checked={ordersAcceptStripe}
                          onCheckedChange={(v) => handleToggleOption('stripeToggle', setOrdersAcceptStripe, v, 'ordersAcceptStripe')}
                          disabled={!ordersCollectPaymentEnabled || optionSaving['stripeToggle']}
                        />
                        {ordersAcceptStripe && (
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-muted-foreground"
                            onClick={(e) => {
                              e.stopPropagation()
                              toggleSection('stripe')
                            }}
                          >
                            <ChevronDown className={`h-4 w-4 transition-transform duration-200 ${!collapsedSections['stripe'] ? 'rotate-180' : ''}`} />
                          </Button>
                        )}
                      </div>
                    </div>

                    {/* Integrated Stripe Custom Configuration Box */}
                    {!collapsedSections['stripe'] && ordersAcceptStripe && (
                      <div className="mt-4 pt-4 border-t border-border/60 rounded-lg bg-muted/40 p-4 space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                          <div className="flex items-center gap-2">
                            <Settings2 className="h-4 w-4 text-muted-foreground" />
                            <span className="text-sm font-semibold text-foreground">Stripe Account Credentials (Optional)</span>
                          </div>
                          {settings?.orderPaymentStripeConfigured ? (
                            <div className="flex items-center gap-2">
                              <Badge variant="default" className="gap-1 font-normal text-xs"><Check className="h-3 w-3" /> Custom Stripe Configured</Badge>
                              <Button type="button" variant="outline" size="sm" className="h-7 text-xs" onClick={handleClearStripeConfig}>Clear</Button>
                            </div>
                          ) : (
                            <Badge variant="outline" className="text-xs font-normal">Merchant Credentials Required</Badge>
                          )}
                        </div>

                        <p className="text-xs text-muted-foreground">
                          Add your Stripe Secret Key so payments generated by the chatbot are credited directly to your Stripe account.
                        </p>

                        <div className="grid gap-4 md:grid-cols-2">
                          <Field>
                            <FieldLabel>Stripe Secret Key</FieldLabel>
                            {isMasked(stripeSecret) && !replacingStripeSecret ? (
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
                                  Replace Key
                                </Button>
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
                                    className="h-8 text-xs text-muted-foreground"
                                    onClick={() => {
                                      setReplacingStripeSecret(false)
                                      mutate("company-settings")
                                    }}
                                  >
                                    Cancel Replace
                                  </Button>
                                )}
                              </div>
                            )}
                          </Field>

                          <Field>
                            <FieldLabel>Settlement Currency</FieldLabel>
                            <Input placeholder="kes, usd, eur, etc." value={stripeCurrency} onChange={(e) => setStripeCurrency(e.target.value)} />
                          </Field>
                        </div>

                        <Field>
                          <FieldLabel>Environment Mode</FieldLabel>
                          <Select value={stripeEnv} onValueChange={(v) => setStripeEnv(v as 'sandbox' | 'production')}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                              <SelectItem value="sandbox">Sandbox (Testing Environment)</SelectItem>
                              <SelectItem value="production">Production (Live Environment)</SelectItem>
                            </SelectContent>
                          </Select>
                        </Field>

                        <div className="pt-2 flex items-center justify-end gap-2 border-t border-border/40">
                          <Button
                            type="button"
                            size="sm"
                            disabled={optionSaving['stripeConfig']}
                            onClick={handleSaveStripeConfig}
                            className="gap-1.5"
                          >
                            {optionSaving['stripeConfig'] ? (
                              <>
                                <Loader2 className="h-3.5 w-3.5 animate-spin" /> Saving…
                              </>
                            ) : optionSaved['stripeConfig'] ? (
                              <>
                                <Check className="h-3.5 w-3.5 text-foreground" /> Saved!
                              </>
                            ) : (
                              <>
                                <Check className="h-3.5 w-3.5" /> Save Stripe Credentials
                              </>
                            )}
                          </Button>
                        </div>
                      </div>
                    )}
                  </CardContent>
                </Card>

                {/* Paystack Payment Card */}
                <Card className={`transition-all duration-200 ${ordersAcceptPaystack ? 'bg-card shadow-sm' : 'opacity-85 bg-card/60'}`}>
                  <CardContent className="p-5 space-y-4">
                    <div className={`flex items-start justify-between gap-4 ${ordersAcceptPaystack ? 'cursor-pointer select-none' : ''}`} onClick={() => ordersAcceptPaystack && toggleSection('paystack')}>
                      <div className="flex items-start gap-3">
                        <div className="rounded-lg p-2.5 bg-muted text-foreground shrink-0 mt-0.5">
                          <Zap className="h-5 w-5" />
                        </div>
                        <div>
                          <div className="flex items-center gap-2">
                            <h4 className="font-semibold text-foreground">Paystack</h4>
                            <Badge variant="outline" className="text-xs font-medium">
                              Multi-channel Checkout
                            </Badge>
                            {optionSaving['paystackToggle'] && (
                              <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />
                            )}
                            {optionSaved['paystackToggle'] && (
                              <Badge variant="outline" className="gap-1 font-medium text-xs">
                                <Check className="h-3 w-3" /> Saved
                              </Badge>
                            )}
                          </div>
                          <p className="text-sm text-muted-foreground mt-0.5">
                            Customer receives a Paystack link supporting cards, bank transfers, and mobile money options.
                          </p>
                        </div>
                      </div>
                      <div className="flex items-center gap-2 shrink-0" onClick={(e) => e.stopPropagation()}>
                        <Switch
                          checked={ordersAcceptPaystack}
                          onCheckedChange={(v) => handleToggleOption('paystackToggle', setOrdersAcceptPaystack, v, 'ordersAcceptPaystack')}
                          disabled={!ordersCollectPaymentEnabled || optionSaving['paystackToggle']}
                        />
                        {ordersAcceptPaystack && (
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-muted-foreground"
                            onClick={(e) => {
                              e.stopPropagation()
                              toggleSection('paystack')
                            }}
                          >
                            <ChevronDown className={`h-4 w-4 transition-transform duration-200 ${!collapsedSections['paystack'] ? 'rotate-180' : ''}`} />
                          </Button>
                        )}
                      </div>
                    </div>

                    {/* Integrated Paystack Custom Configuration Box */}
                    {!collapsedSections['paystack'] && ordersAcceptPaystack && (
                      <div className="mt-4 pt-4 border-t border-border/60 rounded-lg bg-muted/40 p-4 space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                          <div className="flex items-center gap-2">
                            <Settings2 className="h-4 w-4 text-muted-foreground" />
                            <span className="text-sm font-semibold text-foreground">Paystack Account Credentials (Optional)</span>
                          </div>
                          {settings?.orderPaymentPaystackConfigured ? (
                            <div className="flex items-center gap-2">
                              <Badge variant="default" className="gap-1 font-normal text-xs"><Check className="h-3 w-3" /> Custom Paystack Configured</Badge>
                              <Button type="button" variant="outline" size="sm" className="h-7 text-xs" onClick={handleClearPaystackConfig}>Clear</Button>
                            </div>
                          ) : (
                            <Badge variant="outline" className="text-xs font-normal">Merchant Credentials Required</Badge>
                          )}
                        </div>

                        <p className="text-xs text-muted-foreground">
                          Add your Paystack Secret Key so payments generated by the chatbot are credited directly to your Paystack account.
                        </p>

                        <div className="grid gap-4 md:grid-cols-2">
                          <Field>
                            <FieldLabel>Paystack Secret Key</FieldLabel>
                            {isMasked(paystackSecretKey) && !replacingPaystackSecret ? (
                              <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <Input type="text" readOnly className="font-mono text-sm" value={paystackSecretKey} />
                                <Button
                                  type="button"
                                  variant="outline"
                                  size="sm"
                                  className="shrink-0"
                                  onClick={() => {
                                    setReplacingPaystackSecret(true)
                                    setPaystackSecretKey("")
                                  }}
                                >
                                  Replace Key
                                </Button>
                              </div>
                            ) : (
                              <div className="space-y-1">
                                <Input
                                  type="password"
                                  placeholder="sk_live_... or sk_test_..."
                                  value={paystackSecretKey}
                                  onChange={(e) => setPaystackSecretKey(e.target.value)}
                                />
                                {replacingPaystackSecret && (
                                  <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 text-xs text-muted-foreground"
                                    onClick={() => {
                                      setReplacingPaystackSecret(false)
                                      mutate("company-settings")
                                    }}
                                  >
                                    Cancel Replace
                                  </Button>
                                )}
                              </div>
                            )}
                          </Field>

                          <Field>
                            <FieldLabel>Public Key (Optional)</FieldLabel>
                            <Input
                              placeholder="pk_live_... or pk_test_..."
                              value={paystackPublicKey}
                              onChange={(e) => setPaystackPublicKey(e.target.value)}
                            />
                          </Field>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                          <Field>
                            <FieldLabel>Settlement Currency</FieldLabel>
                            <Input
                              placeholder="kes, ngn, usd, ghs, zar, etc."
                              value={paystackCurrency}
                              onChange={(e) => setPaystackCurrency(e.target.value)}
                            />
                          </Field>
                          <Field>
                            <FieldLabel>Environment Mode</FieldLabel>
                            <Select value={paystackEnv} onValueChange={(v) => setPaystackEnv(v as 'sandbox' | 'production')}>
                              <SelectTrigger><SelectValue /></SelectTrigger>
                              <SelectContent>
                                <SelectItem value="sandbox">Sandbox (Testing Environment)</SelectItem>
                                <SelectItem value="production">Production (Live Environment)</SelectItem>
                              </SelectContent>
                            </Select>
                          </Field>
                        </div>

                        <div className="pt-2 flex items-center justify-end gap-2 border-t border-border/40">
                          <Button
                            type="button"
                            size="sm"
                            disabled={optionSaving['paystackConfig']}
                            onClick={handleSavePaystackConfig}
                            className="gap-1.5"
                          >
                            {optionSaving['paystackConfig'] ? (
                              <>
                                <Loader2 className="h-3.5 w-3.5 animate-spin" /> Saving…
                              </>
                            ) : optionSaved['paystackConfig'] ? (
                              <>
                                <Check className="h-3.5 w-3.5 text-foreground" /> Saved!
                              </>
                            ) : (
                              <>
                                <Check className="h-3.5 w-3.5" /> Save Paystack Credentials
                              </>
                            )}
                          </Button>
                        </div>
                      </div>
                    )}
                  </CardContent>
                </Card>

                {/* Pesapal Payment Card */}
                <Card className={`transition-all duration-200 ${ordersAcceptPesapal ? 'bg-card shadow-sm' : 'opacity-85 bg-card/60'}`}>
                  <CardContent className="p-5 space-y-4">
                    <div className={`flex items-start justify-between gap-4 ${ordersAcceptPesapal ? 'cursor-pointer select-none' : ''}`} onClick={() => ordersAcceptPesapal && toggleSection('pesapal')}>
                      <div className="flex items-start gap-3">
                        <div className="rounded-lg p-2.5 bg-muted text-foreground shrink-0 mt-0.5">
                          <CreditCard className="h-5 w-5" />
                        </div>
                        <div>
                          <div className="flex items-center gap-2">
                            <h4 className="font-semibold text-foreground">Pesapal</h4>
                            <Badge variant="outline" className="text-xs font-medium">
                              Cards, Mobile Money & Bank
                            </Badge>
                            {optionSaving['pesapalToggle'] && (
                              <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />
                            )}
                            {optionSaved['pesapalToggle'] && (
                              <Badge variant="outline" className="gap-1 font-medium text-xs">
                                <Check className="h-3 w-3" /> Saved
                              </Badge>
                            )}
                          </div>
                          <p className="text-sm text-muted-foreground mt-0.5">
                            Customers pay online supporting M-Pesa, Airtel Money, Cards, and Bank Transfers via Pesapal API v3.
                          </p>
                        </div>
                      </div>
                      <div className="flex items-center gap-2 shrink-0" onClick={(e) => e.stopPropagation()}>
                        <Switch
                          checked={ordersAcceptPesapal}
                          onCheckedChange={(v) => handleToggleOption('pesapalToggle', setOrdersAcceptPesapal, v, 'ordersAcceptPesapal')}
                          disabled={!ordersCollectPaymentEnabled || optionSaving['pesapalToggle']}
                        />
                        {ordersAcceptPesapal && (
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-muted-foreground"
                            onClick={(e) => {
                              e.stopPropagation()
                              toggleSection('pesapal')
                            }}
                          >
                            <ChevronDown className={`h-4 w-4 transition-transform duration-200 ${!collapsedSections['pesapal'] ? 'rotate-180' : ''}`} />
                          </Button>
                        )}
                      </div>
                    </div>

                    {/* Integrated Pesapal Custom Configuration Box */}
                    {!collapsedSections['pesapal'] && ordersAcceptPesapal && (
                      <div className="mt-4 pt-4 border-t border-border/60 rounded-lg bg-muted/40 p-4 space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                          <div className="flex items-center gap-2">
                            <Settings2 className="h-4 w-4 text-muted-foreground" />
                            <span className="text-sm font-semibold text-foreground">Pesapal API v3 Credentials</span>
                          </div>
                          {settings?.orderPaymentPesapalConfigured ? (
                            <div className="flex items-center gap-2">
                              <Badge variant="default" className="gap-1 font-normal text-xs"><Check className="h-3 w-3" /> Custom Pesapal Configured</Badge>
                              <Button type="button" variant="outline" size="sm" className="h-7 text-xs" onClick={handleClearPesapalConfig}>Clear</Button>
                            </div>
                          ) : (
                            <Badge variant="outline" className="text-xs font-normal">Merchant Credentials Required</Badge>
                          )}
                        </div>

                        <p className="text-xs text-muted-foreground">
                          Enter your Pesapal API v3 Consumer Key and Consumer Secret from your Pesapal developer account.
                        </p>

                        <div className="grid gap-4 md:grid-cols-2">
                          <Field>
                            <FieldLabel>Pesapal Consumer Key</FieldLabel>
                            <Input
                              placeholder="Consumer Key from Pesapal dashboard"
                              value={pesapalConsumerKey}
                              onChange={(e) => setPesapalConsumerKey(e.target.value)}
                            />
                          </Field>

                          <Field>
                            <FieldLabel>Pesapal Consumer Secret</FieldLabel>
                            {isMasked(pesapalConsumerSecret) && !replacingPesapalSecret ? (
                              <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <Input type="text" readOnly className="font-mono text-sm" value={pesapalConsumerSecret} />
                                <Button
                                  type="button"
                                  variant="outline"
                                  size="sm"
                                  className="shrink-0"
                                  onClick={() => {
                                    setReplacingPesapalSecret(true)
                                    setPesapalConsumerSecret("")
                                  }}
                                >
                                  Replace Secret
                                </Button>
                              </div>
                            ) : (
                              <div className="space-y-1">
                                <Input
                                  type="password"
                                  placeholder="Consumer secret from Pesapal dashboard"
                                  value={pesapalConsumerSecret}
                                  onChange={(e) => setPesapalConsumerSecret(e.target.value)}
                                />
                                {replacingPesapalSecret && (
                                  <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 text-xs text-muted-foreground"
                                    onClick={() => {
                                      setReplacingPesapalSecret(false)
                                      mutate("company-settings")
                                    }}
                                  >
                                    Cancel Replace
                                  </Button>
                                )}
                              </div>
                            )}
                          </Field>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                          <Field>
                            <FieldLabel>Default Currency</FieldLabel>
                            <Input
                              placeholder="kes, usd, ugx, tzs, rwf"
                              value={pesapalCurrency}
                              onChange={(e) => setPesapalCurrency(e.target.value)}
                            />
                          </Field>
                          <Field>
                            <FieldLabel>Environment Mode</FieldLabel>
                            <Select value={pesapalEnv} onValueChange={(v) => setPesapalEnv(v as 'sandbox' | 'production')}>
                              <SelectTrigger><SelectValue /></SelectTrigger>
                              <SelectContent>
                                <SelectItem value="sandbox">Sandbox (cybqa.pesapal.com)</SelectItem>
                                <SelectItem value="production">Production (pay.pesapal.com)</SelectItem>
                              </SelectContent>
                            </Select>
                          </Field>
                        </div>

                        <div className="pt-2 flex items-center justify-end gap-2 border-t border-border/40">
                          <Button
                            type="button"
                            size="sm"
                            disabled={optionSaving['pesapalConfig']}
                            onClick={handleSavePesapalConfig}
                            className="gap-1.5"
                          >
                            {optionSaving['pesapalConfig'] ? (
                              <>
                                <Loader2 className="h-3.5 w-3.5 animate-spin" /> Saving…
                              </>
                            ) : optionSaved['pesapalConfig'] ? (
                              <>
                                <Check className="h-3.5 w-3.5 text-foreground" /> Saved!
                              </>
                            ) : (
                              <>
                                <Check className="h-3.5 w-3.5" /> Save Pesapal Credentials
                              </>
                            )}
                          </Button>
                        </div>
                      </div>
                    )}
                  </CardContent>
                </Card>

                {/* Flutterwave Payment Card */}
                <Card className={`transition-all duration-200 ${ordersAcceptFlutterwave ? 'bg-card shadow-sm' : 'opacity-85 bg-card/60'}`}>
                  <CardContent className="p-5 space-y-4">
                    <div className={`flex items-start justify-between gap-4 ${ordersAcceptFlutterwave ? 'cursor-pointer select-none' : ''}`} onClick={() => ordersAcceptFlutterwave && toggleSection('flutterwave')}>
                      <div className="flex items-start gap-3">
                        <div className="rounded-lg p-2.5 bg-muted text-foreground shrink-0 mt-0.5">
                          <CreditCard className="h-5 w-5" />
                        </div>
                        <div>
                          <div className="flex items-center gap-2">
                            <h4 className="font-semibold text-foreground">Flutterwave</h4>
                            <Badge variant="outline" className="text-xs font-medium">
                              Cards, Mobile Money & Bank
                            </Badge>
                            {optionSaving['flutterwaveToggle'] && (
                              <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />
                            )}
                            {optionSaved['flutterwaveToggle'] && (
                              <Badge variant="outline" className="gap-1 font-medium text-xs">
                                <Check className="h-3 w-3" /> Saved
                              </Badge>
                            )}
                          </div>
                          <p className="text-sm text-muted-foreground mt-0.5">
                            Accept online payments via Flutterwave (Cards, Mobile Money, Bank Transfer, USSD).
                          </p>
                        </div>
                      </div>
                      <div className="flex items-center gap-2 shrink-0" onClick={(e) => e.stopPropagation()}>
                        <Switch
                          checked={ordersAcceptFlutterwave}
                          onCheckedChange={(v) => handleToggleOption('flutterwaveToggle', setOrdersAcceptFlutterwave, v, 'ordersAcceptFlutterwave')}
                          disabled={!ordersCollectPaymentEnabled || optionSaving['flutterwaveToggle']}
                        />
                        {ordersAcceptFlutterwave && (
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-muted-foreground"
                            onClick={(e) => {
                              e.stopPropagation()
                              toggleSection('flutterwave')
                            }}
                          >
                            <ChevronDown className={`h-4 w-4 transition-transform duration-200 ${!collapsedSections['flutterwave'] ? 'rotate-180' : ''}`} />
                          </Button>
                        )}
                      </div>
                    </div>

                    {/* Integrated Flutterwave Custom Configuration Box */}
                    {!collapsedSections['flutterwave'] && ordersAcceptFlutterwave && (
                      <div className="mt-4 pt-4 border-t border-border/60 rounded-lg bg-muted/40 p-4 space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                          <div className="flex items-center gap-2">
                            <Settings2 className="h-4 w-4 text-muted-foreground" />
                            <span className="text-sm font-semibold text-foreground">Flutterwave v3 Credentials</span>
                          </div>
                          {settings?.orderPaymentFlutterwaveConfigured ? (
                            <div className="flex items-center gap-2">
                              <Badge variant="default" className="gap-1 font-normal text-xs"><Check className="h-3 w-3" /> Custom Flutterwave Configured</Badge>
                              <Button type="button" variant="outline" size="sm" className="h-7 text-xs" onClick={handleClearFlutterwaveConfig}>Clear</Button>
                            </div>
                          ) : (
                            <Badge variant="outline" className="text-xs font-normal">Merchant Credentials Required</Badge>
                          )}
                        </div>

                        <p className="text-xs text-muted-foreground">
                          Enter your Flutterwave Secret Key from your Flutterwave dashboard settings.
                        </p>

                        <div className="grid gap-4 md:grid-cols-2">
                          <Field>
                            <FieldLabel>Public Key (Optional)</FieldLabel>
                            <Input
                              placeholder="FLWPUBK_TEST-..."
                              value={flutterwavePublicKey}
                              onChange={(e) => setFlutterwavePublicKey(e.target.value)}
                            />
                          </Field>

                          <Field>
                            <FieldLabel>Secret Key</FieldLabel>
                            {isMasked(flutterwaveSecretKey) && !replacingFlutterwaveSecret ? (
                              <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <Input type="text" readOnly className="font-mono text-sm" value={flutterwaveSecretKey} />
                                <Button
                                  type="button"
                                  variant="outline"
                                  size="sm"
                                  className="shrink-0"
                                  onClick={() => {
                                    setReplacingFlutterwaveSecret(true)
                                    setFlutterwaveSecretKey("")
                                  }}
                                >
                                  Replace Secret
                                </Button>
                              </div>
                            ) : (
                              <div className="space-y-1">
                                <Input
                                  type="password"
                                  placeholder="FLWSECK_TEST-..."
                                  value={flutterwaveSecretKey}
                                  onChange={(e) => setFlutterwaveSecretKey(e.target.value)}
                                />
                                {replacingFlutterwaveSecret && (
                                  <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 text-xs text-muted-foreground"
                                    onClick={() => {
                                      setReplacingFlutterwaveSecret(false)
                                      mutate("company-settings")
                                    }}
                                  >
                                    Cancel Replace
                                  </Button>
                                )}
                              </div>
                            )}
                          </Field>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                          <Field>
                            <FieldLabel>Default Currency</FieldLabel>
                            <Input
                              placeholder="kes, ngn, usd, ghs, zar"
                              value={flutterwaveCurrency}
                              onChange={(e) => setFlutterwaveCurrency(e.target.value)}
                            />
                          </Field>
                          <Field>
                            <FieldLabel>Environment Mode</FieldLabel>
                            <Select value={flutterwaveEnv} onValueChange={(v) => setFlutterwaveEnv(v as 'sandbox' | 'production')}>
                              <SelectTrigger><SelectValue /></SelectTrigger>
                              <SelectContent>
                                <SelectItem value="sandbox">Sandbox (Testing)</SelectItem>
                                <SelectItem value="production">Production (Live)</SelectItem>
                              </SelectContent>
                            </Select>
                          </Field>
                        </div>

                        <div className="pt-2 flex items-center justify-end gap-2 border-t border-border/40">
                          <Button
                            type="button"
                            size="sm"
                            disabled={optionSaving['flutterwaveConfig']}
                            onClick={handleSaveFlutterwaveConfig}
                            className="gap-1.5"
                          >
                            {optionSaving['flutterwaveConfig'] ? (
                              <>
                                <Loader2 className="h-3.5 w-3.5 animate-spin" /> Saving…
                              </>
                            ) : optionSaved['flutterwaveConfig'] ? (
                              <>
                                <Check className="h-3.5 w-3.5 text-foreground" /> Saved!
                              </>
                            ) : (
                              <>
                                <Check className="h-3.5 w-3.5" /> Save Flutterwave Credentials
                              </>
                            )}
                          </Button>
                        </div>
                      </div>
                    )}
                  </CardContent>
                </Card>

                {/* PayPal Payment Card */}
                <Card className={`transition-all duration-200 ${ordersAcceptPayPal ? 'bg-card shadow-sm' : 'opacity-85 bg-card/60'}`}>
                  <CardContent className="p-5 space-y-4">
                    <div className={`flex items-start justify-between gap-4 ${ordersAcceptPayPal ? 'cursor-pointer select-none' : ''}`} onClick={() => ordersAcceptPayPal && toggleSection('paypal')}>
                      <div className="flex items-start gap-3">
                        <div className="rounded-lg p-2.5 bg-muted text-foreground shrink-0 mt-0.5">
                          <CreditCard className="h-5 w-5" />
                        </div>
                        <div>
                          <div className="flex items-center gap-2">
                            <h4 className="font-semibold text-foreground">PayPal</h4>
                            <Badge variant="outline" className="text-xs font-medium">
                              Cards & PayPal Balance
                            </Badge>
                            {optionSaving['paypalToggle'] && (
                              <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />
                            )}
                            {optionSaved['paypalToggle'] && (
                              <Badge variant="outline" className="gap-1 font-medium text-xs">
                                <Check className="h-3 w-3" /> Saved
                              </Badge>
                            )}
                          </div>
                          <p className="text-sm text-muted-foreground mt-0.5">
                            Accept payments worldwide via PayPal and debit/credit cards.
                          </p>
                        </div>
                      </div>
                      <div className="flex items-center gap-2 shrink-0" onClick={(e) => e.stopPropagation()}>
                        <Switch
                          checked={ordersAcceptPayPal}
                          onCheckedChange={(v) => handleToggleOption('paypalToggle', setOrdersAcceptPayPal, v, 'ordersAcceptPayPal')}
                          disabled={!ordersCollectPaymentEnabled || optionSaving['paypalToggle']}
                        />
                        {ordersAcceptPayPal && (
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-muted-foreground"
                            onClick={(e) => {
                              e.stopPropagation()
                              toggleSection('paypal')
                            }}
                          >
                            <ChevronDown className={`h-4 w-4 transition-transform duration-200 ${!collapsedSections['paypal'] ? 'rotate-180' : ''}`} />
                          </Button>
                        )}
                      </div>
                    </div>

                    {/* Integrated PayPal Custom Configuration Box */}
                    {!collapsedSections['paypal'] && ordersAcceptPayPal && (
                      <div className="mt-4 pt-4 border-t border-border/60 rounded-lg bg-muted/40 p-4 space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                          <div className="flex items-center gap-2">
                            <Settings2 className="h-4 w-4 text-muted-foreground" />
                            <span className="text-sm font-semibold text-foreground">PayPal REST Credentials</span>
                          </div>
                          {settings?.orderPaymentPayPalConfigured ? (
                            <div className="flex items-center gap-2">
                              <Badge variant="default" className="gap-1 font-normal text-xs"><Check className="h-3 w-3" /> Custom PayPal Configured</Badge>
                              <Button type="button" variant="outline" size="sm" className="h-7 text-xs" onClick={handleClearPayPalConfig}>Clear</Button>
                            </div>
                          ) : (
                            <Badge variant="outline" className="text-xs font-normal">Merchant Credentials Required</Badge>
                          )}
                        </div>

                        <p className="text-xs text-muted-foreground">
                          Enter your PayPal REST App Client ID and Secret from the PayPal Developer Dashboard.
                        </p>

                        <div className="grid gap-4 md:grid-cols-2">
                          <Field>
                            <FieldLabel>Client ID</FieldLabel>
                            <Input
                              placeholder="Your PayPal Client ID"
                              value={paypalClientId}
                              onChange={(e) => setPaypalClientId(e.target.value)}
                            />
                          </Field>

                          <Field>
                            <FieldLabel>Client Secret</FieldLabel>
                            {isMasked(paypalClientSecret) && !replacingPayPalSecret ? (
                              <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <Input type="text" readOnly className="font-mono text-sm" value={paypalClientSecret} />
                                <Button
                                  type="button"
                                  variant="outline"
                                  size="sm"
                                  className="shrink-0"
                                  onClick={() => {
                                    setReplacingPayPalSecret(true)
                                    setPaypalClientSecret("")
                                  }}
                                >
                                  Replace Secret
                                </Button>
                              </div>
                            ) : (
                              <div className="space-y-1">
                                <Input
                                  type="password"
                                  placeholder="Your PayPal Client Secret"
                                  value={paypalClientSecret}
                                  onChange={(e) => setPaypalClientSecret(e.target.value)}
                                />
                                {replacingPayPalSecret && (
                                  <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 text-xs text-muted-foreground"
                                    onClick={() => {
                                      setReplacingPayPalSecret(false)
                                      mutate("company-settings")
                                    }}
                                  >
                                    Cancel Replace
                                  </Button>
                                )}
                              </div>
                            )}
                          </Field>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                          <Field>
                            <FieldLabel>Default Currency</FieldLabel>
                            <Input
                              placeholder="usd, eur, gbp, cad, aud"
                              value={paypalCurrency}
                              onChange={(e) => setPaypalCurrency(e.target.value)}
                            />
                          </Field>
                          <Field>
                            <FieldLabel>Environment Mode</FieldLabel>
                            <Select value={paypalEnv} onValueChange={(v) => setPaypalEnv(v as 'sandbox' | 'production')}>
                              <SelectTrigger><SelectValue /></SelectTrigger>
                              <SelectContent>
                                <SelectItem value="sandbox">Sandbox (Testing)</SelectItem>
                                <SelectItem value="production">Production (Live)</SelectItem>
                              </SelectContent>
                            </Select>
                          </Field>
                        </div>

                        <div className="pt-2 flex items-center justify-end gap-2 border-t border-border/40">
                          <Button
                            type="button"
                            size="sm"
                            disabled={optionSaving['paypalConfig']}
                            onClick={handleSavePayPalConfig}
                            className="gap-1.5"
                          >
                            {optionSaving['paypalConfig'] ? (
                              <>
                                <Loader2 className="h-3.5 w-3.5 animate-spin" /> Saving…
                              </>
                            ) : optionSaved['paypalConfig'] ? (
                              <>
                                <Check className="h-3.5 w-3.5 text-foreground" /> Saved!
                              </>
                            ) : (
                              <>
                                <Check className="h-3.5 w-3.5" /> Save PayPal Credentials
                              </>
                            )}
                          </Button>
                        </div>
                      </div>
                    )}
                  </CardContent>
                </Card>
              </div>
            </div>

            {/* Category 2: Offline & Manual Payment Options */}
            <div className="space-y-4 pt-6 border-t border-border">
              <div>
                <h3 className="text-lg font-semibold text-foreground flex items-center gap-2">
                  <Banknote className="h-5 w-5 text-muted-foreground" />
                  Offline & Manual Payment Options
                </h3>
                <p className="text-sm text-muted-foreground">
                  Allow customers to pay in cash upon delivery or using custom manual instructions.
                </p>
              </div>

              <div className="grid gap-4">
                {/* Cash on Delivery Card */}
                <Card className={`transition-all duration-200 ${ordersAcceptCod ? 'bg-card shadow-sm' : 'opacity-85 bg-card/60'}`}>
                  <CardContent className="p-5">
                    <div className="flex items-start justify-between gap-4">
                      <div className="flex items-start gap-3">
                        <div className="rounded-lg p-2.5 bg-muted text-foreground shrink-0 mt-0.5">
                          <Banknote className="h-5 w-5" />
                        </div>
                        <div>
                          <div className="flex items-center gap-2">
                            <h4 className="font-semibold text-foreground">Cash on Delivery (COD)</h4>
                            <Badge variant="outline" className="text-xs font-medium">
                              Pay on Arrival
                            </Badge>
                            {optionSaving['codToggle'] && (
                              <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />
                            )}
                            {optionSaved['codToggle'] && (
                              <Badge variant="outline" className="gap-1 font-medium text-xs">
                                <Check className="h-3 w-3" /> Saved
                              </Badge>
                            )}
                          </div>
                          <p className="text-sm text-muted-foreground mt-0.5">
                            Customer pays cash when the order is delivered; order is confirmed immediately.
                          </p>
                        </div>
                      </div>
                      <Switch
                        checked={ordersAcceptCod}
                        onCheckedChange={(v) => handleToggleOption('codToggle', setOrdersAcceptCod, v, 'ordersAcceptCod')}
                        disabled={!ordersCollectPaymentEnabled || optionSaving['codToggle']}
                      />
                    </div>
                  </CardContent>
                </Card>

                {/* Custom Manual Payment Instructions Card */}
                <Card className="bg-card shadow-sm">
                  <CardContent className="p-5 space-y-3">
                    <div className="flex items-start justify-between gap-4 cursor-pointer select-none" onClick={() => toggleSection('manualInstructions')}>
                      <div className="flex items-start gap-3">
                        <div className="rounded-lg p-2.5 bg-muted text-foreground shrink-0 mt-0.5">
                          <FileText className="h-5 w-5" />
                        </div>
                        <div>
                          <div className="flex items-center gap-2">
                            <h4 className="font-semibold text-foreground">Custom Manual Payment Instructions</h4>
                            {optionSaving['manualInstructions'] && (
                              <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />
                            )}
                            {optionSaved['manualInstructions'] && (
                              <Badge variant="outline" className="gap-1 font-medium text-xs">
                                <Check className="h-3 w-3" /> Saved
                              </Badge>
                            )}
                          </div>
                          <p className="text-sm text-muted-foreground mt-0.5">
                            Additional manual payment notes (e.g. manual Till number, deposit details, or custom instructions) displayed when customers choose manual payment.
                          </p>
                        </div>
                      </div>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8 text-muted-foreground shrink-0"
                        onClick={(e) => {
                          e.stopPropagation()
                          toggleSection('manualInstructions')
                        }}
                      >
                        <ChevronDown className={`h-4 w-4 transition-transform duration-200 ${!collapsedSections['manualInstructions'] ? 'rotate-180' : ''}`} />
                      </Button>
                    </div>

                    {!collapsedSections['manualInstructions'] && (
                      <>
                        <Textarea
                          placeholder="e.g. Pay via M-Pesa to Till 123456 (MyShop). Include order number in transaction reference."
                          value={orderPaymentManualInstructions}
                          onChange={(e) => setOrderPaymentManualInstructions(e.target.value)}
                          rows={3}
                          className="text-sm"
                          disabled={!ordersCollectPaymentEnabled}
                        />

                        <div className="pt-2 flex items-center justify-end gap-2">
                          <Button
                            type="button"
                            size="sm"
                            disabled={!ordersCollectPaymentEnabled || optionSaving['manualInstructions']}
                            onClick={handleSaveManualInstructions}
                            className="gap-1.5"
                          >
                            {optionSaving['manualInstructions'] ? (
                              <>
                                <Loader2 className="h-3.5 w-3.5 animate-spin" /> Saving…
                              </>
                            ) : optionSaved['manualInstructions'] ? (
                              <>
                                <Check className="h-3.5 w-3.5 text-foreground" /> Saved!
                              </>
                            ) : (
                              <>
                                <Check className="h-3.5 w-3.5" /> Save Instructions
                              </>
                            )}
                          </Button>
                        </div>
                      </>
                    )}
                  </CardContent>
                </Card>
              </div>
            </div>

            {/* Category 3: Delivery Fees & Payment Reminders */}
            <div className="space-y-4 pt-6 border-t border-border">
              <div>
                <h3 className="text-lg font-semibold text-foreground flex items-center gap-2">
                  <Package className="h-5 w-5 text-primary" />
                  Delivery Charges & Payment Reminders
                </h3>
                <p className="text-sm text-muted-foreground">
                  Manage order fulfillment fees and automated WhatsApp payment recovery.
                </p>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                {/* Delivery Fees Card */}
                <Card className="bg-card shadow-sm">
                  <CardContent className="p-5 space-y-4">
                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <div className="flex items-center gap-2">
                          <h4 className="font-semibold text-foreground">Charge Delivery Fees</h4>
                          {optionSaving['deliveryFeesToggle'] && (
                            <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />
                          )}
                          {optionSaved['deliveryFeesToggle'] && (
                            <Badge variant="outline" className="bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400 gap-1 font-medium text-xs">
                              <Check className="h-3 w-3" /> Saved
                            </Badge>
                          )}
                        </div>
                        <p className="text-xs text-muted-foreground mt-0.5">
                          Automatically add shipping/delivery costs to orders.
                        </p>
                      </div>
                      <Switch
                        checked={deliveryFeesEnabled}
                        onCheckedChange={(v) => handleToggleOption('deliveryFeesToggle', setDeliveryFeesEnabled, v, 'deliveryFeesEnabled')}
                        disabled={optionSaving['deliveryFeesToggle']}
                      />
                    </div>

                    {deliveryFeesEnabled && (
                      <div className="space-y-3 pt-2 border-t border-border/40">
                        <Field>
                          <FieldLabel className="text-xs">Default Delivery Fee</FieldLabel>
                          <Input
                            type="number"
                            placeholder="0"
                            value={defaultDeliveryFee}
                            onChange={(e) => setDefaultDeliveryFee(e.target.value)}
                          />
                        </Field>
                        <Field>
                          <FieldLabel className="text-xs">Free Delivery Threshold (Optional)</FieldLabel>
                          <Input
                            type="number"
                            placeholder="e.g. 5000"
                            value={freeDeliveryAbove}
                            onChange={(e) => setFreeDeliveryAbove(e.target.value)}
                          />
                        </Field>
                        <div className="pt-2 flex items-center justify-end gap-2">
                          <Button
                            type="button"
                            size="sm"
                            disabled={optionSaving['deliveryFeesConfig']}
                            onClick={handleSaveDeliveryFeesConfig}
                            className="gap-1.5"
                          >
                            {optionSaving['deliveryFeesConfig'] ? (
                              <>
                                <Loader2 className="h-3.5 w-3.5 animate-spin" /> Saving…
                              </>
                            ) : optionSaved['deliveryFeesConfig'] ? (
                              <>
                                <Check className="h-3.5 w-3.5 text-emerald-400" /> Saved!
                              </>
                            ) : (
                              <>
                                <Check className="h-3.5 w-3.5" /> Save Delivery Fees
                              </>
                            )}
                          </Button>
                        </div>
                      </div>
                    )}
                  </CardContent>
                </Card>

                {/* Automatic Payment Recovery Card */}
                <Card className="bg-card shadow-sm">
                  <CardContent className="p-5 space-y-4">
                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <div className="flex items-center gap-2">
                          <h4 className="font-semibold text-foreground flex items-center gap-1.5">
                            <Clock className="h-4 w-4 text-primary" />
                            Automatic Payment Recovery
                          </h4>
                          {optionSaving['paymentRecoveryToggle'] && (
                            <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />
                          )}
                          {optionSaved['paymentRecoveryToggle'] && (
                            <Badge variant="outline" className="bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400 gap-1 font-medium text-xs">
                              <Check className="h-3 w-3" /> Saved
                            </Badge>
                          )}
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">
                          Send WhatsApp reminders with a payment link to customers with unpaid orders.
                        </p>
                      </div>
                      <Switch
                        checked={paymentRecoveryEnabled}
                        onCheckedChange={(v) => handleToggleOption('paymentRecoveryToggle', setPaymentRecoveryEnabled, v, 'paymentRecoveryEnabled')}
                        disabled={optionSaving['paymentRecoveryToggle']}
                      />
                    </div>

                    <div className="p-3 rounded-lg bg-muted/60 border border-border text-xs text-muted-foreground flex items-start gap-2">
                      <AlertCircle className="h-4 w-4 shrink-0 text-primary mt-0.5" />
                      <span>When enabled, unpaid order follow-ups will be sent automatically to help recover abandoned checkouts.</span>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </div>
          </div>
        </TabsContent>
      </Tabs>
    </div>
  )
}
