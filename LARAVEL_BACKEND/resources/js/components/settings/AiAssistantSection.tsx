"use client"

import { useEffect, useState } from "react"
import { Bot, ChevronDown, KeyRound, ShoppingBag } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Switch } from "@/components/ui/switch"
import { Badge } from "@/components/ui/badge"
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Field, FieldLabel } from "@/components/ui/field"
import { cn } from "@/lib/utils"
import { useCompanySettings } from "@/lib/api-hooks"
import { useSubscription } from "@/lib/api-hooks"
import {
  updateSettings,
  getCompanyAiProviders,
  updateCompanyAiProvider,
  getCompanyAiUsage,
  exportLearningSamples,
} from "@/lib/api-actions"
import { apiRequest } from "@/lib/api-client"
import { useSWRConfig } from "swr"
import { OnboardingInterviewPanel } from "@/components/agent/OnboardingInterviewPanel"
import { SettingSection, SettingRow, SaveBar, GrowthBadge } from "./shared"
import { LockedFeatureGate } from "@/components/shared/upgrade-prompt"
import type { BusinessDnaPreset, BusinessDnaSettings } from "@/lib/api-hooks"

const TWIN_FIELDS = ["mission", "brand_voice", "sales_strategy", "pricing_rules", "competitors", "target_customers"] as const

export function AiAssistantSection() {
  const { mutate } = useSWRConfig()
  const { data: settings } = useCompanySettings()
  const { data: subscription } = useSubscription()
  const isStarter = (subscription?.plan ?? "free") === "free"

  const [aiGreeting, setAiGreeting] = useState("")
  const [aiTone, setAiTone] = useState("balanced")
  const [aiModelMode, setAiModelMode] = useState<"auto" | "platform_default" | "specific">("auto")
  const [aiModelId, setAiModelId] = useState("")
  const [aiReplyMode, setAiReplyMode] = useState<"ai_first" | "balanced">("ai_first")
  const [availableAiModels, setAvailableAiModels] = useState<
    { id: string; displayName: string; provider: string; inputCostPerMillion: number; outputCostPerMillion: number }[]
  >([])
  const [autoReplyEnabled, setAutoReplyEnabled] = useState(false)
  const [replyInCustomerLanguage, setReplyInCustomerLanguage] = useState(true)
  const [defaultReplyLanguage, setDefaultReplyLanguage] = useState("")
  const [agentCommerceEnabled, setAgentCommerceEnabled] = useState(false)
  const [agentProactiveEnabled, setAgentProactiveEnabled] = useState(false)
  const [agentVoiceReplyEnabled, setAgentVoiceReplyEnabled] = useState(false)
  const [agentVoiceReplyMode, setAgentVoiceReplyMode] = useState<"voice_only" | "dual_text_and_voice" | "text_only">("dual_text_and_voice")
  const [agentVoiceId, setAgentVoiceId] = useState("nova")
  const [agentMorningBriefWhatsappEnabled, setAgentMorningBriefWhatsappEnabled] = useState(false)
  const [ownerWhatsappPhone, setOwnerWhatsappPhone] = useState("")
  const [webWidgetToken, setWebWidgetToken] = useState<string | null>(null)
  const [widgetScriptUrl, setWidgetScriptUrl] = useState<string | null>(null)
  const [companyIdForEmbed, setCompanyIdForEmbed] = useState<number | null>(null)
  const [agentBusinessGoals, setAgentBusinessGoals] = useState<string[]>([])
  const [agentBusinessGoalCatalog, setAgentBusinessGoalCatalog] = useState<Record<string, string>>({})
  const [businessDnaPreset, setBusinessDnaPreset] = useState<"industry_default" | "luxury_brand" | "friendly_cafe" | "custom">("industry_default")
  const [businessDna, setBusinessDna] = useState<BusinessDnaSettings>({})
  const [businessDnaPresets, setBusinessDnaPresets] = useState<Record<string, BusinessDnaPreset>>({})
  const [digitalTwin, setDigitalTwin] = useState<Record<string, string>>({})
  const [agentCouncilEnabled, setAgentCouncilEnabled] = useState(false)
  const [learnFromConversations, setLearnFromConversations] = useState(true)
  const [learnFromConversationsEditable, setLearnFromConversationsEditable] = useState(true)
  const [devModeEnabled, setDevModeEnabled] = useState(false)
  const [notificationsEnabled, setNotificationsEnabled] = useState(false)
  const [industry, setIndustry] = useState("business")
  const [aiCredentialMode, setAiCredentialMode] = useState<"platform" | "company" | "company_preferred">("platform")
  const [openaiApiKey, setOpenaiApiKey] = useState("")
  const [openaiKeyConfigured, setOpenaiKeyConfigured] = useState(false)
  const [aiPlanCapabilities, setAiPlanCapabilities] = useState<{
    allowedModelModes: string[]
    allowByok: boolean
    allowedCredentialModes: string[]
    plan?: string
    aiCostLimitUsd?: number | null
  } | null>(null)
  const [aiUsageSummary, setAiUsageSummary] = useState<Record<string, unknown> | null>(null)
  const [aiUsageExtras, setAiUsageExtras] = useState<{
    byCredentialSource?: { source: string; requests: number; billedCostUsd: number }[]
    learningEmbeddingCoveragePercent?: number
  } | null>(null)

  const [advancedOpen, setAdvancedOpen] = useState(false)
  const [twinOpen, setTwinOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const [saved, setSaved] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [byokSaving, setByokSaving] = useState(false)

  useEffect(() => {
    if (!settings) return
    if (settings.industry) setIndustry(settings.industry)
    if (settings.aiGreeting?.trim()) setAiGreeting(settings.aiGreeting)
    const tone = settings.aiTone?.trim().toLowerCase()
    if (tone === "formal" || tone === "balanced" || tone === "casual") setAiTone(tone)
    if (settings.aiModelMode) setAiModelMode(settings.aiModelMode)
    if (settings.aiModelId) setAiModelId(settings.aiModelId)
    if (settings.aiReplyMode === "ai_first" || settings.aiReplyMode === "balanced") setAiReplyMode(settings.aiReplyMode)
    if (["platform", "company", "company_preferred"].includes(settings.aiCredentialMode ?? "")) {
      setAiCredentialMode(settings.aiCredentialMode as typeof aiCredentialMode)
    }
    if (settings.replyInCustomerLanguage != null) setReplyInCustomerLanguage(settings.replyInCustomerLanguage)
    if (settings.defaultReplyLanguage != null) setDefaultReplyLanguage(settings.defaultReplyLanguage)
    if (settings.aiPlanCapabilities) setAiPlanCapabilities(settings.aiPlanCapabilities)
    if (settings.effectiveAiModelMode && settings.aiModelMode !== settings.effectiveAiModelMode) {
      setAiModelMode(settings.effectiveAiModelMode as typeof aiModelMode)
      if (settings.effectiveAiModelMode !== "specific") setAiModelId("")
    }
    if (settings.autoReplyEnabled != null) setAutoReplyEnabled(settings.autoReplyEnabled)
    if (settings.agentCommerceEnabled != null) setAgentCommerceEnabled(settings.agentCommerceEnabled)
    if (settings.agentProactiveEnabled != null) setAgentProactiveEnabled(settings.agentProactiveEnabled)
    if (settings.agentVoiceReplyEnabled != null) setAgentVoiceReplyEnabled(settings.agentVoiceReplyEnabled)
    if (settings.agentVoiceReplyMode != null) setAgentVoiceReplyMode(settings.agentVoiceReplyMode)
    if (settings.agentVoiceId != null) setAgentVoiceId(settings.agentVoiceId)
    if (settings.agentMorningBriefWhatsappEnabled != null) setAgentMorningBriefWhatsappEnabled(settings.agentMorningBriefWhatsappEnabled)
    if (settings.ownerWhatsappPhone != null) setOwnerWhatsappPhone(settings.ownerWhatsappPhone)
    if (settings.webWidgetToken != null) setWebWidgetToken(settings.webWidgetToken)
    if (settings.widgetScriptUrl) setWidgetScriptUrl(settings.widgetScriptUrl)
    if (settings.companyId != null) setCompanyIdForEmbed(settings.companyId)
    if (settings.agentBusinessGoals) setAgentBusinessGoals(settings.agentBusinessGoals)
    if (settings.agentBusinessGoalCatalog) setAgentBusinessGoalCatalog(settings.agentBusinessGoalCatalog)
    if (settings.businessDnaPresets) setBusinessDnaPresets(settings.businessDnaPresets)
    if (settings.businessDna) setBusinessDna(settings.businessDna)
    if (settings.businessDnaCustom != null) setBusinessDnaPreset(settings.businessDnaCustom ? "custom" : "industry_default")
    if (settings.digitalTwin) setDigitalTwin(settings.digitalTwin)
    if (settings.agentCouncilEnabled != null) setAgentCouncilEnabled(settings.agentCouncilEnabled)
    if (settings.learnFromConversations != null) setLearnFromConversations(settings.learnFromConversations)
    if (settings.devModeEnabled != null) setDevModeEnabled(settings.devModeEnabled)
    if (settings.learnFromConversationsEditable != null) setLearnFromConversationsEditable(settings.learnFromConversationsEditable)
    if (settings.notificationsEnabled != null) setNotificationsEnabled(settings.notificationsEnabled)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [settings])

  useEffect(() => {
    apiRequest<{ models: typeof availableAiModels }>("/api/company/ai-models")
      .then((data) => setAvailableAiModels(data.models ?? []))
      .catch(() => setAvailableAiModels([]))
    getCompanyAiProviders()
      .then((data) => {
        if (data.credentialMode) setAiCredentialMode(data.credentialMode as typeof aiCredentialMode)
        if (data.aiPlanCapabilities) setAiPlanCapabilities(data.aiPlanCapabilities)
        setOpenaiKeyConfigured(!!data.providers?.find((p) => p.slug === "openai")?.apiKeyConfigured)
      })
      .catch(() => {})
    getCompanyAiUsage("30d")
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
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const applyPreset = (key: typeof businessDnaPreset) => {
    setBusinessDnaPreset(key)
    if (key === "industry_default") {
      setBusinessDna({})
      return
    }
    if (key === "custom") return
    const preset = businessDnaPresets[key]
    if (preset) {
      const { label: _l, description: _d, ...dna } = preset
      setBusinessDna(dna)
    }
  }

  const dnaPayload = (): BusinessDnaSettings | null => {
    if (businessDnaPreset === "industry_default") return null
    return {
      tone: businessDna.tone?.trim() || undefined,
      values: businessDna.values?.filter((v) => v.trim() !== "") ?? undefined,
      risk_tolerance: businessDna.risk_tolerance,
      service_philosophy: businessDna.service_philosophy?.trim() || undefined,
      escalation_culture: businessDna.escalation_culture?.trim() || undefined,
      communication_style: businessDna.communication_style?.trim() || undefined,
    }
  }

  const save = async () => {
    setSaving(true)
    setError(null)
    setSaved(false)
    const result = await updateSettings({
      aiGreeting: aiGreeting.trim(),
      aiTone: aiTone.trim(),
      aiModelMode,
      aiModelId: aiModelMode === "specific" && aiModelId ? aiModelId : null,
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
      businessDna: dnaPayload(),
      digitalTwin: Object.keys(digitalTwin).length > 0 ? digitalTwin : null,
      agentCouncilEnabled,
      learnFromConversations,
      devModeEnabled,
      notificationsEnabled,
    })
    setSaving(false)
    if (!result.success) {
      setError(result.message ?? "Couldn't save AI settings.")
      return
    }
    setSaved(true)
    setTimeout(() => setSaved(false), 3000)
    mutate("company-settings")
  }

  const saveByok = async () => {
    setByokSaving(true)
    const payload: { credentialMode: string; apiKey?: string } = { credentialMode: aiCredentialMode }
    if (openaiApiKey.trim()) payload.apiKey = openaiApiKey.trim()
    const result = await updateCompanyAiProvider("openai", payload)
    setByokSaving(false)
    if (result.success) {
      setOpenaiApiKey("")
      setOpenaiKeyConfigured(true)
      getCompanyAiUsage("30d").then((data) => setAiUsageSummary(data.summary)).catch(() => {})
    }
    setNotice(result.success ? "API key settings saved." : (result.message ?? "Couldn't save API key."))
  }

  const exportSamples = async () => {
    try {
      const blob = await exportLearningSamples()
      const url = URL.createObjectURL(blob)
      const a = document.createElement("a")
      a.href = url
      a.download = `learning-samples-${new Date().toISOString().slice(0, 10)}.csv`
      a.click()
      URL.revokeObjectURL(url)
    } catch {
      setError("Export failed.")
    }
  }

  const allowedModes = aiPlanCapabilities?.allowedModelModes ?? ["auto", "platform_default", "specific"]

  // Starter is a manual workspace — no AI conversations, no assistant to configure.
  if (isStarter) {
    return (
      <LockedFeatureGate
        icon={Bot}
        title="The AI assistant lives on Growth"
        description="On Starter you reply to customers yourself from the inbox. Growth adds the AI assistant — auto-replies, selling agent, voice notes, and brand voice — with 1,000 conversations a month."
        planLabel="Starter (KSh 0)"
      />
    )
  }

  return (
    <div className="space-y-4">
      {notice && (
        <p className="rounded-xl border border-border bg-muted/40 px-4 py-2.5 text-[13px] text-muted-foreground">{notice}</p>
      )}

      <SettingSection
        title="Assistant personality"
        description="How your AI greets customers, sounds, and when it jumps in."
        badge={autoReplyEnabled ? <Badge className="text-[11px]">Auto-reply on</Badge> : <Badge variant="secondary" className="text-[11px] font-normal">Auto-reply off</Badge>}
      >
        <div className="space-y-1.5">
          <FieldLabel htmlFor="ai-greeting">Greeting & persona</FieldLabel>
          <Textarea
            id="ai-greeting"
            value={aiGreeting}
            onChange={(e) => setAiGreeting(e.target.value)}
            rows={2}
            placeholder="e.g. Welcome! I'm your assistant. How can I help you today?"
          />
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="space-y-1.5">
            <FieldLabel>Tone</FieldLabel>
            <Select value={aiTone} onValueChange={setAiTone}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="formal">Formal & professional</SelectItem>
                <SelectItem value="balanced">Balanced & friendly</SelectItem>
                <SelectItem value="casual">Casual & conversational</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <FieldLabel>Reply routing</FieldLabel>
            <Select value={aiReplyMode} onValueChange={(v) => setAiReplyMode(v as typeof aiReplyMode)}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="ai_first">AI-first (recommended)</SelectItem>
                <SelectItem value="balanced">Balanced (FAQ shortcuts first)</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
        <div className="space-y-1.5">
          <FieldLabel>Model strategy</FieldLabel>
          <Select
            value={aiModelMode === "specific" && aiModelId ? `model:${aiModelId}` : aiModelMode}
            onValueChange={(v) => {
              if (v === "auto" || v === "platform_default") {
                setAiModelMode(v)
                setAiModelId("")
              } else if (v.startsWith("model:")) {
                setAiModelMode("specific")
                setAiModelId(v.replace("model:", ""))
              }
            }}
          >
            <SelectTrigger><SelectValue placeholder="Select model strategy" /></SelectTrigger>
            <SelectContent>
              {allowedModes.includes("auto") && <SelectItem value="auto">Auto — best value model</SelectItem>}
              {allowedModes.includes("platform_default") && <SelectItem value="platform_default">Platform default</SelectItem>}
              {allowedModes.includes("specific") && availableAiModels.length > 0 && (
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
        </div>
        <div className="divide-y divide-border rounded-xl border border-border">
          <div className="px-4 py-1">
            <SettingRow
              label="Auto-reply"
              hint="Let AI answer across channels"
              control={<Switch checked={autoReplyEnabled} onCheckedChange={setAutoReplyEnabled} />}
            />
          </div>
          <div className="px-4 py-1">
            <SettingRow
              label="Reply in customer's language"
              hint="Detect and match automatically"
              control={<Switch checked={replyInCustomerLanguage} onCheckedChange={setReplyInCustomerLanguage} />}
            />
          </div>
        </div>
        {!replyInCustomerLanguage && (
          <div className="space-y-1.5">
            <FieldLabel>Fallback language code</FieldLabel>
            <Input value={defaultReplyLanguage} onChange={(e) => setDefaultReplyLanguage(e.target.value)} placeholder="en" className="sm:max-w-[200px]" />
          </div>
        )}
      </SettingSection>

      <SettingSection
        title="Selling"
        description="A tool-using AI that searches products, takes orders, and follows up."
        badge={agentCommerceEnabled ? <Badge className="gap-1 text-[11px]"><ShoppingBag className="h-3 w-3" /> Agent active</Badge> : undefined}
      >
        <SettingRow
          label="Commerce mode"
          hint="Process orders, check stock, issue refunds in chat"
          control={<Switch checked={agentCommerceEnabled} onCheckedChange={setAgentCommerceEnabled} />}
        />
        {agentCommerceEnabled && (
          <div className="space-y-4 rounded-xl bg-muted/40 p-4">
            <SettingRow
              label="Proactive outreach"
              hint="Abandoned carts, payment confirmations"
              control={<Switch checked={agentProactiveEnabled} onCheckedChange={setAgentProactiveEnabled} />}
            />
            <SettingRow
              label="Morning brief on WhatsApp"
              hint="Daily 7:00 AM sales summary"
              control={<Switch checked={agentMorningBriefWhatsappEnabled} onCheckedChange={setAgentMorningBriefWhatsappEnabled} />}
            />
            {agentMorningBriefWhatsappEnabled && (
              <div className="space-y-1.5">
                <FieldLabel htmlFor="owner-phone">Owner WhatsApp number</FieldLabel>
                <Input id="owner-phone" value={ownerWhatsappPhone} onChange={(e) => setOwnerWhatsappPhone(e.target.value)} placeholder="254712345678" className="sm:max-w-[280px]" />
              </div>
            )}
          </div>
        )}
        {webWidgetToken && (
          <details className="rounded-xl border border-border px-4 py-3 text-xs">
            <summary className="cursor-pointer font-medium text-foreground">Website chat widget embed code</summary>
            <p className="mt-1 truncate font-mono text-muted-foreground">Token: {webWidgetToken}</p>
            {widgetScriptUrl && companyIdForEmbed && (
              <pre className="mt-2 overflow-x-auto whitespace-pre-wrap rounded bg-muted/60 p-2.5 font-mono text-[10px]">{`<script
  src="${widgetScriptUrl}"
  data-company-id="${companyIdForEmbed}"
  data-widget-token="${webWidgetToken}"
  data-api-base="${typeof window !== "undefined" ? window.location.origin : ""}"
  async
></script>`}</pre>
            )}
          </details>
        )}
      </SettingSection>

      <SettingSection title="Voice" description="Understand voice notes and answer with speech.">
        <SettingRow
          label="Voice note replies"
          hint="Transcribe audio, reply with voice"
          control={<Switch checked={agentVoiceReplyEnabled} onCheckedChange={setAgentVoiceReplyEnabled} />}
        />
        {agentVoiceReplyEnabled && (
          <div className="grid gap-3 rounded-xl bg-muted/40 p-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <FieldLabel>Reply mode</FieldLabel>
              <Select value={agentVoiceReplyMode} onValueChange={(v) => setAgentVoiceReplyMode(v as typeof agentVoiceReplyMode)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="dual_text_and_voice">Text + voice note</SelectItem>
                  <SelectItem value="voice_only">Voice note only</SelectItem>
                  <SelectItem value="text_only">Text only</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <FieldLabel>Voice</FieldLabel>
              <Select value={agentVoiceId} onValueChange={setAgentVoiceId}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="nova">Nova — warm & professional</SelectItem>
                  <SelectItem value="alloy">Alloy — balanced neutral</SelectItem>
                  <SelectItem value="echo">Echo — warm male</SelectItem>
                  <SelectItem value="fable">Fable — expressive British</SelectItem>
                  <SelectItem value="onyx">Onyx — deep authoritative</SelectItem>
                  <SelectItem value="shimmer">Shimmer — clear & energetic</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        )}
      </SettingSection>

      <SettingSection title="Brand voice" description="Teach the AI how your business speaks. Start with the interview.">
        <OnboardingInterviewPanel onComplete={() => mutate("company-settings")} />
        <div className="space-y-1.5">
          <FieldLabel>Personality preset</FieldLabel>
          <Select value={businessDnaPreset} onValueChange={(v) => applyPreset(v as typeof businessDnaPreset)}>
            <SelectTrigger><SelectValue placeholder="Choose a personality" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="industry_default">Industry default ({industry})</SelectItem>
              {businessDnaPresets.luxury_brand && (
                <SelectItem value="luxury_brand">{businessDnaPresets.luxury_brand.label ?? "Luxury Brand"}</SelectItem>
              )}
              {businessDnaPresets.friendly_cafe && (
                <SelectItem value="friendly_cafe">{businessDnaPresets.friendly_cafe.label ?? "Friendly Café"}</SelectItem>
              )}
              <SelectItem value="custom">Custom brand DNA</SelectItem>
            </SelectContent>
          </Select>
        </div>
        {businessDnaPreset !== "industry_default" && (
          <div className="grid gap-3 rounded-xl bg-muted/40 p-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <FieldLabel>Brand tone</FieldLabel>
              <Input
                value={businessDna.tone ?? ""}
                onChange={(e) => { setBusinessDnaPreset("custom"); setBusinessDna((d) => ({ ...d, tone: e.target.value })) }}
                placeholder="e.g. luxury, calm, high-end"
              />
            </div>
            <div className="space-y-1.5">
              <FieldLabel>Risk tolerance</FieldLabel>
              <Select
                value={businessDna.risk_tolerance ?? "medium"}
                onValueChange={(v) => { setBusinessDnaPreset("custom"); setBusinessDna((d) => ({ ...d, risk_tolerance: v as "low" | "medium" | "high" })) }}
              >
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="low">Low — escalate early</SelectItem>
                  <SelectItem value="medium">Medium — balanced</SelectItem>
                  <SelectItem value="high">High — max autonomy</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5 sm:col-span-2">
              <FieldLabel>Core values (comma-separated)</FieldLabel>
              <Input
                value={(businessDna.values ?? []).join(", ")}
                onChange={(e) => { setBusinessDnaPreset("custom"); setBusinessDna((d) => ({ ...d, values: e.target.value.split(",").map((s) => s.trim()).filter(Boolean) })) }}
                placeholder="quality, speed, discretion"
              />
            </div>
          </div>
        )}
        {Object.keys(agentBusinessGoalCatalog).length > 0 && (
          <div className="space-y-1.5">
            <FieldLabel>Business goals</FieldLabel>
            <div className="grid gap-2 sm:grid-cols-2">
              {Object.entries(agentBusinessGoalCatalog).map(([key, label]) => (
                <label key={key} className="flex cursor-pointer items-center gap-2 rounded-lg border border-border bg-background p-2 text-xs">
                  <input
                    type="checkbox"
                    className="h-4 w-4 rounded border-border"
                    checked={agentBusinessGoals.includes(key)}
                    onChange={(e) => setAgentBusinessGoals((prev) => (e.target.checked ? [...prev, key] : prev.filter((g) => g !== key)))}
                  />
                  <span>
                    <span className="block font-medium capitalize text-foreground">{key.replace(/_/g, " ")}</span>
                    <span className="block text-[10px] text-muted-foreground">{label}</span>
                  </span>
                </label>
              ))}
            </div>
          </div>
        )}
        <div className="rounded-xl border border-border">
          <button type="button" onClick={() => setTwinOpen((v) => !v)} className="flex w-full items-center justify-between px-4 py-3 text-left">
            <span>
              <span className="block text-sm font-medium text-foreground">Business background (digital twin)</span>
              <span className="block text-xs text-muted-foreground">Mission, pricing rules, competitors — deep context for smarter replies.</span>
            </span>
            <ChevronDown className={cn("h-4 w-4 text-muted-foreground transition-transform", twinOpen && "rotate-180")} />
          </button>
          {twinOpen && (
            <div className="grid gap-3 border-t border-border/60 p-4 sm:grid-cols-2">
              {TWIN_FIELDS.map((key) => (
                <Textarea
                  key={key}
                  rows={2}
                  placeholder={key.replace(/_/g, " ").toUpperCase()}
                  value={digitalTwin[key] ?? ""}
                  onChange={(e) => setDigitalTwin((prev) => ({ ...prev, [key]: e.target.value }))}
                />
              ))}
            </div>
          )}
        </div>
      </SettingSection>

      <SettingSection title="Advanced" description="Governance, learning memory, and your own API keys.">
        <button
          type="button"
          onClick={() => setAdvancedOpen((v) => !v)}
          className="flex w-full items-center justify-between rounded-xl border border-border px-4 py-3 text-left hover:bg-muted/40"
        >
          <span className="flex items-center gap-2 text-sm font-medium text-foreground">
            <Bot className="h-4 w-4 text-muted-foreground" />
            {advancedOpen ? "Hide advanced controls" : "Show advanced controls"}
          </span>
          <ChevronDown className={cn("h-4 w-4 text-muted-foreground transition-transform", advancedOpen && "rotate-180")} />
        </button>
        {advancedOpen && (
          <div className="space-y-4">
            <div className="divide-y divide-border rounded-xl border border-border">
              <div className="px-4 py-1">
                <SettingRow
                  label="Agent council review"
                  hint="Specialist agents debate before replying"
                  control={<Switch checked={agentCouncilEnabled} onCheckedChange={setAgentCouncilEnabled} />}
                />
              </div>
              <div className="px-4 py-1">
                <SettingRow
                  label="Learn from conversations"
                  hint={learnFromConversationsEditable ? "Improve replies from past wins" : "Managed by platform admin"}
                  control={<Switch checked={learnFromConversations} onCheckedChange={setLearnFromConversations} disabled={!learnFromConversationsEditable} />}
                />
              </div>
              <div className="px-4 py-1">
                <SettingRow
                  label="Escalation alerts"
                  hint="Notify staff when a human is needed"
                  control={<Switch checked={notificationsEnabled} onCheckedChange={setNotificationsEnabled} />}
                />
              </div>
              <div className="px-4 py-1">
                <SettingRow
                  label="Developer mode"
                  hint="Log raw prompts for debugging"
                  control={<Switch checked={devModeEnabled} onCheckedChange={setDevModeEnabled} />}
                />
              </div>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-muted/40 p-4">
              <div>
                <p className="text-sm font-medium text-foreground">Learning memory export</p>
                <p className="text-xs text-muted-foreground">GDPR-compliant CSV of stored samples.</p>
              </div>
              <Button type="button" variant="outline" size="sm" onClick={exportSamples}>
                Export CSV
              </Button>
            </div>

            {/* BYOK — previously an unreachable tab, now lives here */}
            <div className="space-y-3 rounded-xl border border-border p-4">
              <div className="flex flex-wrap items-center gap-2">
                <p className="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                  <KeyRound className="h-4 w-4 text-muted-foreground" /> Your own OpenAI key
                </p>
                {!aiPlanCapabilities?.allowByok && <GrowthBadge />}
              </div>
              {!aiPlanCapabilities?.allowByok ? (
                <p className="text-[13px] text-muted-foreground">
                  Available on Growth and Custom — upgrade to add your own key. Your plan uses platform keys
                  {aiPlanCapabilities?.aiCostLimitUsd != null && (
                    <> (${String(aiPlanCapabilities.aiCostLimitUsd)}/mo included)</>
                  )}
                  .
                </p>
              ) : (
                <>
                  {aiUsageSummary && (
                    <p className="text-xs text-muted-foreground">
                      This period: {String(aiUsageSummary.totalRequests ?? 0)} requests · platform billed $
                      {String(aiUsageSummary.platformBilledCostUsd ?? 0)}
                      {aiUsageSummary.platformCostLimitUsd != null && ` / $${String(aiUsageSummary.platformCostLimitUsd)} limit`}
                      {aiUsageExtras?.learningEmbeddingCoveragePercent != null &&
                        ` · memory coverage ${aiUsageExtras.learningEmbeddingCoveragePercent}%`}
                    </p>
                  )}
                  <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1.5">
                      <FieldLabel>Credential mode</FieldLabel>
                      <Select value={aiCredentialMode} onValueChange={(v) => setAiCredentialMode(v as typeof aiCredentialMode)}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="platform">Platform keys only</SelectItem>
                          {(aiPlanCapabilities?.allowedCredentialModes ?? []).includes("company_preferred") && (
                            <SelectItem value="company_preferred">My key first, then platform</SelectItem>
                          )}
                          {(aiPlanCapabilities?.allowedCredentialModes ?? []).includes("company") && (
                            <SelectItem value="company">My keys only</SelectItem>
                          )}
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="space-y-1.5">
                      <FieldLabel>OpenAI API key</FieldLabel>
                      <Input
                        type="password"
                        value={openaiApiKey}
                        onChange={(e) => setOpenaiApiKey(e.target.value)}
                        placeholder={openaiKeyConfigured ? "•••••••• (configured — enter to replace)" : "sk-…"}
                      />
                    </div>
                  </div>
                  <Button type="button" size="sm" variant="outline" onClick={saveByok} disabled={byokSaving}>
                    {byokSaving ? "Saving…" : "Save API key settings"}
                  </Button>
                </>
              )}
            </div>
          </div>
        )}
        <SaveBar saving={saving} saved={saved} error={error} onSave={save} label="Save AI settings" />
      </SettingSection>
    </div>
  )
}
