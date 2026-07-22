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
import { Building2, MessageSquare, Bot, Users, Bell, Plus, Trash2, Check, CreditCard } from "lucide-react"
import { OnboardingInterviewPanel } from "@/components/agent/OnboardingInterviewPanel"

function isMasked(val: unknown): boolean {
  return typeof val === "string" && val.startsWith("••••")
}

function mpesaSecretKey(field: "passkey" | "consumer_secret") {
  return `mpesa:${field}`
}
// API: GET /api/company/settings (useCompanySettings), PUT /api/company/settings (updateSettings)
import { useCompanySettings, useCompanyTeam, useWhatsAppNumbers, type BusinessDnaPreset, type BusinessDnaSettings } from "@/lib/api-hooks"
import { apiRequest } from "@/lib/api-client"
import { CATALOG_CURRENCY_OPTIONS, normalizeCurrencyCode } from "@/lib/format-currency"
import { useSWRConfig } from "swr"
import {
  updateSettings,
  getCompanyAiProviders,
  updateCompanyAiProvider,
  getCompanyAiUsage,
  exportLearningSamples,
  getWhatsAppStatus,
  disconnectWhatsApp,
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
  const { data: settings } = useCompanySettings()
  const { data: teamMembers = [] } = useCompanyTeam()
  const { data: whatsappNumbers = [] } = useWhatsAppNumbers()
  const [activeTab, setActiveTab] = useState("profile")
  const [profileSaving, setProfileSaving] = useState(false)
  const [profileError, setProfileError] = useState<string | null>(null)
  const [profileSuccess, setProfileSuccess] = useState(false)
  const [businessName, setBusinessName] = useState("QuickBite Restaurant")
  const [industry, setIndustry] = useState<'retail' | 'restaurant' | 'services' | 'other'>('other')
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
  const [waMessage, setWaMessage] = useState<string | null>(null)
  const [waEmbeddedLoading, setWaEmbeddedLoading] = useState(false)
  const [waManualLoading, setWaManualLoading] = useState(false)
  const [waManualPhoneNumberId, setWaManualPhoneNumberId] = useState("")
  const [waManualAccessToken, setWaManualAccessToken] = useState("")
  const [waManualWabaId, setWaManualWabaId] = useState("")
  const [waManualDisplayPhone, setWaManualDisplayPhone] = useState("")
  const [waManualRegistrationPin, setWaManualRegistrationPin] = useState("")
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
      getWhatsAppCampaignAudience(campaignSegment).then((a) => setCampaignAudience(a.uniqueCustomers)).catch(() => {})
    }
  }, [activeTab, campaignSegment])

  const handleWhatsAppDisconnect = async () => {
    setWaMessage(null)
    setWaLoading(true)
    const result = await disconnectWhatsApp()
    setWaMessage(result.message ?? (result.success ? "Disconnected." : "Failed."))
    loadWhatsAppStatus()
  }

  const handleManualConnect = async (e: React.FormEvent) => {
    e.preventDefault()
    setWaMessage(null)
    if (waStatus?.platformBillingReady === false) {
      setWaMessage("Platform WhatsApp billing is enabled but not configured. Contact your administrator.")
      return
    }
    setWaManualLoading(true)
    try {
      const result = await connectWhatsApp({
        phoneNumberId: waManualPhoneNumberId.trim(),
        accessToken: waManualAccessToken.trim(),
        whatsappBusinessAccountId: waManualWabaId.trim() || undefined,
        displayPhoneNumber: waManualDisplayPhone.trim() || undefined,
        registrationPin: waManualRegistrationPin.trim() || undefined,
      })
      setWaMessage(result.message ?? (result.success ? "WhatsApp connected." : "Connection failed."))
      if (result.success) {
        setWaManualAccessToken("")
        setWaManualRegistrationPin("")
        await loadWhatsAppStatus()
        await loadWhatsAppTemplates()
      }
    } catch (err) {
      setWaMessage(err instanceof Error ? err.message : "Manual connection failed.")
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
      if (result.success) {
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
  const [stripeCurrency, setStripeCurrency] = useState('usd')
  /** User clicked Replace on a masked M-Pesa / Stripe secret field */
  const [replacingMpesaSecret, setReplacingMpesaSecret] = useState<Record<string, boolean>>({})
  const [replacingStripeSecret, setReplacingStripeSecret] = useState(false)

  /** AI tab — persisted via PUT /api/company/settings (aiGreeting, aiTone, booleans). Model is platform-wide, not saved here. */
  const [aiGreeting, setAiGreeting] = useState(
    'You are a friendly and helpful customer service assistant for a restaurant. Be polite, professional, and helpful.'
  )
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
      if (settings.ordersAcceptPaystack != null) setOrdersAcceptPaystack(settings.ordersAcceptPaystack)
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
      if (settings.learnFromConversationsEditable != null) {
        setLearnFromConversationsEditable(settings.learnFromConversationsEditable)
      }
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

  useEffect(() => {
    apiRequest<{ models: Array<{ id: string; displayName: string; provider: string; inputCostPerMillion: number; outputCostPerMillion: number }> }>(
      '/api/company/ai-models'
    )
      .then((data) => setAvailableAiModels(data.models ?? []))
      .catch(() => setAvailableAiModels([]))
  }, [])

  useEffect(() => {
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
      .catch(() => {})
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
  }, [])

  const handleByokSave = async () => {
    setByokSaving(true)
    const payload: { credentialMode: string; apiKey?: string } = { credentialMode: aiCredentialMode }
    if (openaiApiKey.trim()) payload.apiKey = openaiApiKey.trim()
    const result = await updateCompanyAiProvider('openai', payload)
    setByokSaving(false)
    if (result.success) {
      setOpenaiApiKey('')
      setOpenaiKeyConfigured(true)
      getCompanyAiUsage('30d').then((data) => setAiUsageSummary(data.summary)).catch(() => {})
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
      agentMorningBriefWhatsappEnabled,
      ownerWhatsappPhone: ownerWhatsappPhone.trim() || null,
      agentBusinessGoals,
      businessDna: businessDnaPayload(),
      digitalTwin: Object.keys(digitalTwin).length > 0 ? digitalTwin : null,
      agentCouncilEnabled,
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
      industry,
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

  const handleOrderPaymentsSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setOrderPaymentsMessage(null)
    setOrderPaymentsSaving(true)
    const payload: Parameters<typeof updateSettings>[0] = {
      ordersCollectPaymentEnabled,
      orderPaymentManualInstructions: orderPaymentManualInstructions.trim() || null,
      ordersAcceptMpesa,
      ordersAcceptStripe,
      ordersAcceptPaystack,
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
                    <FieldLabel htmlFor="industry">Industry</FieldLabel>
                    <p className="text-sm text-muted-foreground mb-2">
                      Used for CRM follow-up templates and portfolio insights matching.
                    </p>
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
                  </Field>

                  <Field>
                    <FieldLabel htmlFor="attributionRetentionDays">Attribution data retention (days)</FieldLabel>
                    <p className="text-sm text-muted-foreground mb-2">
                      How long to keep social attribution data (30–730 days). Leave empty for platform default.
                    </p>
                    <Input
                      id="attributionRetentionDays"
                      type="number"
                      min={30}
                      max={730}
                      placeholder="e.g. 365"
                      value={attributionRetentionDays}
                      onChange={(e) => setAttributionRetentionDays(e.target.value)}
                    />
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

        <TabsContent value="whatsapp">
          <Card>
            <CardHeader>
              <CardTitle>WhatsApp Business</CardTitle>
              <CardDescription>
                Connect via Facebook (recommended) or paste Meta API credentials manually if your administrator enabled that option.
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
                    {waStatus.metaBillingModel === "solution_partner" && (
                      <p>Platform credit line: {waStatus.creditLineShared ? "Attached" : "Pending"}</p>
                    )}
                  </div>
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
                  {waStatus?.embeddedSignupEnabled && (
                    <div className="rounded-lg border border-border bg-muted/30 p-4 space-y-3">
                      <p className="text-sm font-medium text-foreground">Connect with Facebook</p>
                      <p className="text-sm text-muted-foreground">
                        Sign in with Facebook, select or create your WhatsApp Business account, and verify your phone number with OTP. No Meta Developer setup required on your side.
                        {waStatus.requiresMetaPaymentMethod === false && (
                          <> WhatsApp usage is billed through the platform — you do not need to add a payment method in Meta.</>
                        )}
                      </p>
                      {waStatus.platformBillingReady === false && (
                        <p className="text-sm text-amber-600">
                          Platform billing is enabled but not fully configured. Contact your administrator before connecting.
                        </p>
                      )}
                      <Button type="button" onClick={handleEmbeddedSignup} disabled={waEmbeddedLoading || waStatus.platformBillingReady === false}>
                        {waEmbeddedLoading ? "Opening Meta signup…" : "Connect with Facebook"}
                      </Button>
                    </div>
                  )}

                  {waStatus?.manualConnectEnabled && (
                    <div className="rounded-lg border border-border p-4 space-y-4">
                      <div className="space-y-1">
                        <p className="text-sm font-medium text-foreground">Manual connection</p>
                        <p className="text-sm text-muted-foreground">
                          Paste credentials from Meta Developer Console → WhatsApp → API Setup (Phone number ID and permanent access token).
                          {waStatus?.webhookUrl && (
                            <> Platform webhook URL: <code className="text-xs">{waStatus.webhookUrl}</code></>
                          )}
                        </p>
                      </div>
                      <form onSubmit={handleManualConnect} className="space-y-3">
                        <Field>
                          <FieldLabel htmlFor="waManualPhoneNumberId">Phone Number ID</FieldLabel>
                          <Input
                            id="waManualPhoneNumberId"
                            value={waManualPhoneNumberId}
                            onChange={(e) => setWaManualPhoneNumberId(e.target.value)}
                            placeholder="From Meta → WhatsApp → API Setup"
                            required
                          />
                        </Field>
                        <Field>
                          <FieldLabel htmlFor="waManualAccessToken">Permanent access token</FieldLabel>
                          <Input
                            id="waManualAccessToken"
                            type="password"
                            value={waManualAccessToken}
                            onChange={(e) => setWaManualAccessToken(e.target.value)}
                            placeholder="System user or permanent token"
                            required
                          />
                        </Field>
                        <Field>
                          <FieldLabel htmlFor="waManualWabaId">WhatsApp Business Account ID (optional)</FieldLabel>
                          <Input
                            id="waManualWabaId"
                            value={waManualWabaId}
                            onChange={(e) => setWaManualWabaId(e.target.value)}
                            placeholder="Recommended for webhook subscription"
                          />
                        </Field>
                        <Field>
                          <FieldLabel htmlFor="waManualDisplayPhone">Display phone number (optional)</FieldLabel>
                          <Input
                            id="waManualDisplayPhone"
                            value={waManualDisplayPhone}
                            onChange={(e) => setWaManualDisplayPhone(e.target.value)}
                            placeholder="+254712345678"
                          />
                        </Field>
                        <Field>
                          <FieldLabel htmlFor="waManualRegistrationPin">Two-step verification PIN</FieldLabel>
                          <Input
                            id="waManualRegistrationPin"
                            type="password"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            maxLength={6}
                            value={waManualRegistrationPin}
                            onChange={(e) => setWaManualRegistrationPin(e.target.value.replace(/\D/g, "").slice(0, 6))}
                            placeholder="6-digit PIN from WhatsApp Manager"
                          />
                          <p className="text-xs text-muted-foreground">
                            Required if this number already has two-step verification enabled in Meta. Leave blank only for a brand-new number.
                          </p>
                        </Field>
                        <Button type="submit" disabled={waManualLoading || waStatus?.platformBillingReady === false}>
                          {waManualLoading ? "Connecting…" : "Connect manually"}
                        </Button>
                      </form>
                    </div>
                  )}

                  {!waStatus?.embeddedSignupEnabled && !waStatus?.manualConnectEnabled && (
                    <div className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-foreground">
                      WhatsApp connection is not available yet. Ask your platform administrator to enable Embedded Signup or manual connection in Admin → Settings → Integrations.
                    </div>
                  )}

                  <div className="rounded-lg border border-border p-4 space-y-2">
                    <p className="text-sm font-medium text-foreground">Before you connect</p>
                    <ul className="text-sm text-muted-foreground list-disc pl-5 space-y-1">
                      <li>Use a number that can receive SMS or voice OTP (Embedded Signup) or is already on Cloud API (manual).</li>
                      <li>Do not use a number already linked to another WhatsApp API provider.</li>
                      {waStatus?.requiresMetaPaymentMethod !== false ? (
                        <li>Add a payment method to your WhatsApp Business account in Meta when prompted (required for messaging).</li>
                      ) : (
                        <li>WhatsApp conversation fees are billed through the platform — no Meta payment card required.</li>
                      )}
                      {waStatus?.embeddedSignupEnabled && (
                        <li>Finish all Meta popup steps without closing the window.</li>
                      )}
                    </ul>
                  </div>
                </div>
              )}
              {waMessage && <p className="text-sm text-muted-foreground">{waMessage}</p>}
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
                        <SelectItem value="auto">Auto (best value — picks lowest-cost enabled model)</SelectItem>
                      )}
                      {(aiPlanCapabilities?.allowedModelModes ?? ['auto', 'platform_default', 'specific']).includes('platform_default') && (
                        <SelectItem value="platform_default">Platform default</SelectItem>
                      )}
                      {(aiPlanCapabilities?.allowedModelModes ?? ['auto', 'platform_default', 'specific']).includes('specific') && availableAiModels.length > 0 && (
                        <SelectGroup>
                          <SelectLabel>Specific model (Enterprise)</SelectLabel>
                          {availableAiModels.map((m) => (
                            <SelectItem key={m.id} value={`model:${m.id}`}>
                              {m.displayName} ({m.provider}) — ${m.inputCostPerMillion.toFixed(2)}/${m.outputCostPerMillion.toFixed(2)} per 1M
                            </SelectItem>
                          ))}
                        </SelectGroup>
                      )}
                    </SelectContent>
                  </Select>
                  <p className="text-xs text-muted-foreground mt-1">
                    {aiPlanCapabilities?.plan === 'starter'
                      ? 'Starter plan uses Auto model selection only. Upgrade for platform default or a specific model.'
                      : aiPlanCapabilities?.plan === 'professional'
                        ? 'Professional: Auto or platform default. Upgrade to Enterprise to pick a specific model.'
                        : 'Auto selects the cheapest configured model with a valid API key.'}
                  </p>
                </Field>

                <Field>
                  <FieldLabel htmlFor="aiReplyMode">Reply routing</FieldLabel>
                  <Select value={aiReplyMode} onValueChange={(v) => setAiReplyMode(v as 'ai_first' | 'balanced')}>
                    <SelectTrigger id="aiReplyMode">
                      <SelectValue placeholder="Select routing mode" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="ai_first">AI-first (recommended) — AI answers questions; FAQs inform the model</SelectItem>
                      <SelectItem value="balanced">Balanced — keyword shortcuts and direct FAQ matches before AI</SelectItem>
                    </SelectContent>
                  </Select>
                  <p className="text-xs text-muted-foreground mt-1">
                    AI-first uses your knowledge base and products in the system prompt. Canned replies are only used when AI is unavailable.
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
                      <p className="font-medium text-foreground">Reply in customer&apos;s language</p>
                      <p className="text-sm text-muted-foreground">When off, use the default language below</p>
                    </div>
                    <Switch checked={replyInCustomerLanguage} onCheckedChange={setReplyInCustomerLanguage} />
                  </div>

                  {!replyInCustomerLanguage && (
                    <Field>
                      <FieldLabel>Default reply language</FieldLabel>
                      <Input
                        value={defaultReplyLanguage}
                        onChange={(e) => setDefaultReplyLanguage(e.target.value)}
                        placeholder="en"
                      />
                      <p className="text-xs text-muted-foreground mt-1">ISO code, e.g. en, sw, ar, fr</p>
                    </Field>
                  )}

                  <div className="rounded-lg border border-primary/20 bg-primary/5 p-4 space-y-4">
                    <div className="flex items-start justify-between gap-4">
                      <div className="space-y-1">
                        <div className="flex items-center gap-2">
                          <p className="font-semibold text-foreground">Agent commerce mode</p>
                          {agentCommerceEnabled && (
                            <Badge variant="default" className="text-[10px] uppercase tracking-wide">
                              ON
                            </Badge>
                          )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                          Turns on the AI employee with tools (search products, orders, memory, refunds).
                          Requires <strong>Auto-reply</strong> enabled and an OpenAI-compatible provider.
                        </p>
                      </div>
                      <Switch checked={agentCommerceEnabled} onCheckedChange={setAgentCommerceEnabled} />
                    </div>

                    {agentCommerceEnabled && !autoReplyEnabled && (
                      <p className="text-sm text-amber-700 dark:text-amber-400 rounded-md bg-amber-500/10 px-3 py-2">
                        Enable <strong>Auto-reply</strong> above so the agent can respond on WhatsApp.
                      </p>
                    )}

                  {agentCommerceEnabled && (
                    <>
                      <div className="flex items-center justify-between pl-4 border-l-2 border-primary/30">
                        <div>
                          <p className="font-medium text-foreground">Proactive agent outreach</p>
                          <p className="text-sm text-muted-foreground">
                            AI follows up on abandoned carts and personalizes payment confirmations
                          </p>
                        </div>
                        <Switch checked={agentProactiveEnabled} onCheckedChange={setAgentProactiveEnabled} />
                      </div>

                      <div className="flex items-center justify-between">
                        <div>
                          <p className="font-medium text-foreground">Voice note replies (TTS)</p>
                          <p className="text-sm text-muted-foreground">
                            When customers send voice notes, reply with synthesized audio when possible
                          </p>
                        </div>
                        <Switch checked={agentVoiceReplyEnabled} onCheckedChange={setAgentVoiceReplyEnabled} />
                      </div>

                      <div className="flex items-center justify-between">
                        <div>
                          <p className="font-medium text-foreground">Morning brief on WhatsApp</p>
                          <p className="text-sm text-muted-foreground">
                            Send the daily commerce brief to the owner via WhatsApp at 7:00 AM
                          </p>
                        </div>
                        <Switch
                          checked={agentMorningBriefWhatsappEnabled}
                          onCheckedChange={setAgentMorningBriefWhatsappEnabled}
                        />
                      </div>

                      {agentMorningBriefWhatsappEnabled && (
                        <div className="space-y-2 pl-4 border-l-2 border-primary/30">
                          <FieldLabel htmlFor="ownerWhatsappPhone">Owner WhatsApp number</FieldLabel>
                          <Input
                            id="ownerWhatsappPhone"
                            value={ownerWhatsappPhone}
                            onChange={(e) => setOwnerWhatsappPhone(e.target.value)}
                            placeholder="e.g. 254712345678"
                          />
                          <p className="text-xs text-muted-foreground">
                            Falls back to company owner profile phone or company phone if empty.
                          </p>
                        </div>
                      )}

                      {webWidgetToken && (
                        <div className="rounded-md border bg-muted/30 p-3 text-xs space-y-2">
                          <p className="font-medium">Web chat widget</p>
                          <p className="font-mono break-all text-muted-foreground">Token: {webWidgetToken}</p>
                          {channelIngestSecret && (
                            <p className="font-mono break-all text-muted-foreground">
                              Webhook secret: {channelIngestSecret}
                            </p>
                          )}
                          {channelWebhookUrls && (
                            <div className="space-y-1 text-muted-foreground">
                              <p>Email webhook: {channelWebhookUrls.email}</p>
                              <p>Instagram DM: {channelWebhookUrls.instagramDm}</p>
                              <p className="text-[11px]">Header: X-Channel-Ingest-Secret</p>
                            </div>
                          )}
                          {widgetScriptUrl && companyIdForEmbed && (
                            <pre className="overflow-x-auto rounded bg-background p-2 text-[10px] whitespace-pre-wrap">{`<script
  src="${widgetScriptUrl}"
  data-company-id="${companyIdForEmbed}"
  data-widget-token="${webWidgetToken}"
  data-api-base="${typeof window !== "undefined" ? window.location.origin : ""}"
  async
></script>`}</pre>
                          )}
                        </div>
                      )}

                      {Object.keys(agentBusinessGoalCatalog).length > 0 && (
                        <Field>
                          <FieldLabel>Business goals</FieldLabel>
                          <p className="text-xs text-muted-foreground mb-2">
                            The agent optimizes conversations toward these objectives
                          </p>
                          <div className="space-y-2">
                            {Object.entries(agentBusinessGoalCatalog).map(([key, label]) => (
                              <label key={key} className="flex items-start gap-2 text-sm cursor-pointer">
                                <input
                                  type="checkbox"
                                  className="mt-1"
                                  checked={agentBusinessGoals.includes(key)}
                                  onChange={(e) => {
                                    setAgentBusinessGoals((prev) =>
                                      e.target.checked ? [...prev, key] : prev.filter((g) => g !== key)
                                    )
                                  }}
                                />
                                <span>
                                  <span className="font-medium text-foreground">{key.replace(/_/g, ' ')}</span>
                                  <span className="block text-muted-foreground text-xs">{label}</span>
                                </span>
                              </label>
                            ))}
                          </div>
                        </Field>
                      )}

                      <OnboardingInterviewPanel
                        onComplete={() => {
                          mutate("company-settings")
                        }}
                      />

                      <Field>
                        <FieldLabel>Business DNA</FieldLabel>
                        <p className="text-xs text-muted-foreground mb-2">
                          Same question, different voice — a luxury brand and a friendly café should answer differently.
                        </p>
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
                              Industry default ({industry})
                            </SelectItem>
                            {businessDnaPresets.luxury_brand && (
                              <SelectItem value="luxury_brand">
                                {businessDnaPresets.luxury_brand.label ?? 'Luxury brand'}
                              </SelectItem>
                            )}
                            {businessDnaPresets.friendly_cafe && (
                              <SelectItem value="friendly_cafe">
                                {businessDnaPresets.friendly_cafe.label ?? 'Friendly café'}
                              </SelectItem>
                            )}
                            <SelectItem value="custom">Custom (edit fields below)</SelectItem>
                          </SelectContent>
                        </Select>
                        {businessDnaPreset !== 'industry_default' && (
                          <div className="mt-3 space-y-3 rounded-md border bg-background/80 p-3">
                            {businessDnaPreset === 'luxury_brand' && businessDnaPresets.luxury_brand?.description && (
                              <p className="text-xs text-muted-foreground">{businessDnaPresets.luxury_brand.description}</p>
                            )}
                            {businessDnaPreset === 'friendly_cafe' && businessDnaPresets.friendly_cafe?.description && (
                              <p className="text-xs text-muted-foreground">{businessDnaPresets.friendly_cafe.description}</p>
                            )}
                            <Field>
                              <FieldLabel>Tone</FieldLabel>
                              <Input
                                value={businessDna.tone ?? ''}
                                onChange={(e) => {
                                  setBusinessDnaPreset('custom')
                                  setBusinessDna((d) => ({ ...d, tone: e.target.value }))
                                }}
                                placeholder="e.g. luxury and calm"
                              />
                            </Field>
                            <Field>
                              <FieldLabel>Core values (comma-separated)</FieldLabel>
                              <Input
                                value={(businessDna.values ?? []).join(', ')}
                                onChange={(e) => {
                                  setBusinessDnaPreset('custom')
                                  setBusinessDna((d) => ({
                                    ...d,
                                    values: e.target.value.split(',').map((s) => s.trim()).filter(Boolean),
                                  }))
                                }}
                                placeholder="quality, discretion, craftsmanship"
                              />
                            </Field>
                            <Field>
                              <FieldLabel>Risk tolerance</FieldLabel>
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
                                <SelectTrigger>
                                  <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                  <SelectItem value="low">Low — cautious, escalate early</SelectItem>
                                  <SelectItem value="medium">Medium — balanced</SelectItem>
                                  <SelectItem value="high">High — more autonomous offers</SelectItem>
                                </SelectContent>
                              </Select>
                            </Field>
                            <Field>
                              <FieldLabel>Service philosophy</FieldLabel>
                              <Textarea
                                rows={2}
                                value={businessDna.service_philosophy ?? ''}
                                onChange={(e) => {
                                  setBusinessDnaPreset('custom')
                                  setBusinessDna((d) => ({ ...d, service_philosophy: e.target.value }))
                                }}
                              />
                            </Field>
                            <Field>
                              <FieldLabel>Communication style</FieldLabel>
                              <Textarea
                                rows={2}
                                value={businessDna.communication_style ?? ''}
                                onChange={(e) => {
                                  setBusinessDnaPreset('custom')
                                  setBusinessDna((d) => ({ ...d, communication_style: e.target.value }))
                                }}
                              />
                            </Field>
                          </div>
                        )}
                      </Field>
                    </>
                  )}
                  </div>

                  <Field>
                    <FieldLabel>Digital twin</FieldLabel>
                    <p className="text-xs text-muted-foreground mb-2">
                      Mission, brand voice, and strategy — how the agent models your business.
                    </p>
                    <div className="space-y-2">
                      {(['mission', 'brand_voice', 'sales_strategy', 'pricing_rules', 'competitors', 'target_customers'] as const).map((key) => (
                        <Textarea
                          key={key}
                          rows={2}
                          placeholder={key.replace(/_/g, ' ')}
                          value={digitalTwin[key] ?? ''}
                          onChange={(e) => setDigitalTwin((prev) => ({ ...prev, [key]: e.target.value }))}
                        />
                      ))}
                    </div>
                  </Field>

                  <div className="flex items-center justify-between">
                    <div>
                      <p className="font-medium text-foreground">Agent council</p>
                      <p className="text-sm text-muted-foreground">
                        Enable internal specialist debate before the Chief Agent replies.
                      </p>
                    </div>
                    <Switch checked={agentCouncilEnabled} onCheckedChange={setAgentCouncilEnabled} />
                  </div>

                  <div className="flex items-center justify-between">
                    <div>
                      <p className="font-medium text-foreground">Learn from conversations</p>
                      <p className="text-sm text-muted-foreground">
                        {learnFromConversationsEditable
                          ? "Use past WhatsApp AI exchanges to improve reply consistency"
                          : "Controlled by platform administrator"}
                      </p>
                    </div>
                    <Switch
                      checked={learnFromConversations}
                      onCheckedChange={setLearnFromConversations}
                      disabled={!learnFromConversationsEditable}
                    />
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

          <Card className="mt-6">
            <CardHeader>
              <CardTitle>Learning memory (GDPR)</CardTitle>
              <CardDescription>Export conversation learning samples stored for your company</CardDescription>
            </CardHeader>
            <CardContent>
              <Button
                type="button"
                variant="outline"
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
                Download learning samples CSV
              </Button>
            </CardContent>
          </Card>

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

        {/* Order Payments — enable M-Pesa and/or Stripe for customer orders */}
        <TabsContent value="order-payments">
          <Card>
            <CardHeader>
              <CardTitle>Collect payment for orders</CardTitle>
              <CardDescription>
                Choose whether to collect payment after orders. You can use M-Pesa, card (Stripe), Paystack, and/or manual payment details (e.g. bank account). Turn off to skip payment and only confirm the order.
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
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Paystack</p>
                    <p className="text-sm text-muted-foreground">Customer gets a Paystack payment link (cards, bank, mobile money)</p>
                  </div>
                  <Switch checked={ordersAcceptPaystack} onCheckedChange={setOrdersAcceptPaystack} disabled={!ordersCollectPaymentEnabled} />
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
