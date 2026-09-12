"use client"

import { useEffect, useState } from "react"
import Link from "next/link"
import {
  Check,
  ChevronLeft,
  ChevronRight,
  Copy,
  ExternalLink,
  FileText,
  Megaphone,
  MessageSquare,
  Settings2,
  Smartphone,
} from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Badge } from "@/components/ui/badge"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Field, FieldLabel } from "@/components/ui/field"
import { useSubscription, useWhatsAppNumbers } from "@/lib/api-hooks"
import {
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
  type WhatsAppStatus,
  type WhatsAppTemplate,
} from "@/lib/api-actions"
import { SettingSection } from "./shared"
import { LockedFeatureGate } from "@/components/shared/upgrade-prompt"

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

const STEPS_EMBEDDED = [
  { step: 1 as const, title: "Method", desc: "Recommended path" },
  { step: 3 as const, title: "Connect", desc: "Authorize WhatsApp" },
  { step: 4 as const, title: "Done", desc: "You're live" },
]
const STEPS_MANUAL = [
  { step: 1 as const, title: "Method", desc: "OAuth vs manual" },
  { step: 2 as const, title: "Checklist", desc: "Readiness" },
  { step: 3 as const, title: "Credentials", desc: "Meta API keys" },
  { step: 4 as const, title: "Done", desc: "You're live" },
]

export function WhatsAppSection() {
  const { data: subscription } = useSubscription()
  const { data: whatsappNumbers = [] } = useWhatsAppNumbers()
  const isStarter = (subscription?.plan ?? "free") === "free"

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
  const [tplLoading, setTplLoading] = useState(false)

  const [waStep, setWaStep] = useState<1 | 2 | 3 | 4>(1)
  const [waMethod, setWaMethod] = useState<"embedded" | "manual">("embedded")
  const [waChecklist, setWaChecklist] = useState({ phoneReady: false, metaAccess: false, noConflict: false })
  const [copiedWebhook, setCopiedWebhook] = useState(false)

  const manualConnectEnabled = waStatus?.manualConnectEnabled === true

  useEffect(() => {
    if (!manualConnectEnabled && waMethod === "manual") {
      setWaMethod("embedded")
      if (waStep === 2) setWaStep(1)
    }
  }, [manualConnectEnabled, waMethod, waStep])

  const loadTemplates = async () => {
    try {
      setWaTemplates(await listWhatsAppTemplates())
    } catch {
      setWaTemplates([])
    }
  }

  const loadStatus = async () => {
    setWaLoading(true)
    try {
      setWaStatus(await getWhatsAppStatus())
    } finally {
      setWaLoading(false)
    }
  }

  useEffect(() => {
    loadStatus()
    loadTemplates()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

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

  const handleDisconnect = async () => {
    setWaMessage(null)
    setWaLoading(true)
    const result = await disconnectWhatsApp()
    setWaMessage(result.message ?? (result.success ? "Disconnected." : "Failed."))
    loadStatus()
  }

  const handleResubscribe = async () => {
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
      await loadStatus()
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
      setWaMessage("Meta App Secret is required. Use the App Secret from the same Meta Developer app that created this access token.")
      setWaMessageError(true)
      return
    }
    if (!webhookVerifyToken) {
      setWaMessage("Webhook verify token is required. Set any string in Meta → Your App → WhatsApp → Configuration, and paste the same value here.")
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
        await loadStatus()
        await loadTemplates()
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
      window.FB.init({ appId: cfg.appId, cookie: true, xfbml: false, version: cfg.graphVersion || "v21.0" })
      const finishPromise = waitForEmbeddedSignupFinish()
      const loginExtras: Record<string, unknown> = {
        setup: {},
        featureType: cfg.enableCoexist ? "coex" : "",
        sessionInfoVersion: "3",
      }
      const code = await new Promise<string | null>((resolve) => {
        window.FB?.login(
          (response) => resolve(response?.authResponse?.code ?? null),
          { config_id: cfg.configId, response_type: "code", override_default_response_type: true, extras: loginExtras }
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
        await loadStatus()
        await loadTemplates()
      }
    } catch (e) {
      setWaMessage(e instanceof Error ? e.message : "Embedded signup failed.")
    } finally {
      setWaEmbeddedLoading(false)
    }
  }

  if (isStarter && !waStatus?.connected && !waLoading) {
    return (
      <LockedFeatureGate
        icon={MessageSquare}
        title="WhatsApp selling lives on Growth"
        description="Your Starter plan covers the storefront, bookings, and dine-in with M-Pesa checkout. When you're ready to sell where customers already chat, Growth connects your WhatsApp number with AI replies, campaigns, and a shared team inbox."
        planLabel="Starter (KSh 0)"
      />
    )
  }

  const steps = waMethod === "embedded" ? STEPS_EMBEDDED : STEPS_MANUAL
  const copyWebhookUrl = async () => {
    const url = waStatus?.webhookUrl ?? "http://localhost:8080/api/whatsapp/webhook"
    try {
      await navigator.clipboard.writeText(url)
      setCopiedWebhook(true)
      setTimeout(() => setCopiedWebhook(false), 2000)
    } catch {
      /* clipboard unavailable */
    }
  }

  return (
    <div className="space-y-4">
      {waMessage && (
        <p className={`rounded-xl border px-4 py-2.5 text-[13px] ${waMessageError ? "border-destructive/40 bg-destructive/10 text-destructive" : "border-border bg-muted/40 text-muted-foreground"}`}>
          {waMessage}
        </p>
      )}

      <SettingSection
        title="WhatsApp connection"
        description={
          waStatus?.connected
            ? "Your number, health, and webhook at a glance."
            : "Connect with Facebook in about two minutes — no Meta Developer account needed."
        }
        badge={
          waStatus?.connected ? (
            <Badge className="gap-1 text-[11px]"><Check className="h-3 w-3" /> Connected</Badge>
          ) : waLoading && !waStatus ? undefined : (
            <Badge variant="secondary" className="text-[11px] font-normal">Not connected</Badge>
          )
        }
      >
        {waLoading && !waStatus ? (
          <p className="py-4 text-center text-sm text-muted-foreground">Checking connection…</p>
        ) : waStatus?.connected ? (
          <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-2">
              {waStatus.displayPhoneNumber && (
                <span className="text-sm font-medium text-foreground">{waStatus.displayPhoneNumber}</span>
              )}
              {waStatus.qualityRating && <Badge variant="outline">Quality: {waStatus.qualityRating}</Badge>}
              {waStatus.displayNameStatus && <Badge variant="outline">Display name: {waStatus.displayNameStatus}</Badge>}
            </div>
            <dl className="grid gap-2 rounded-xl bg-muted/40 p-4 text-[13px] sm:grid-cols-2">
              <div className="flex justify-between gap-2"><dt className="text-muted-foreground">Webhook</dt><dd className="font-medium text-foreground">{waStatus.webhookSubscribed ? "Subscribed" : "Not subscribed"}</dd></div>
              <div className="flex justify-between gap-2"><dt className="text-muted-foreground">Phone</dt><dd className="font-medium text-foreground">{waStatus.phoneRegistered ? "Registered" : "Not registered"}</dd></div>
              {waStatus.connectedVia === "manual" && (
                <>
                  <div className="flex justify-between gap-2"><dt className="text-muted-foreground">App secret</dt><dd className="font-medium text-foreground">{waStatus.hasMetaAppSecret ? "Saved" : "Missing"}</dd></div>
                  <div className="flex justify-between gap-2"><dt className="text-muted-foreground">Verify token</dt><dd className="font-medium text-foreground">{waStatus.hasWebhookVerifyToken ? "Saved" : "Missing"}</dd></div>
                </>
              )}
              {waStatus.metaBillingModel === "solution_partner" && waStatus.connectedVia !== "manual" && (
                <div className="flex justify-between gap-2"><dt className="text-muted-foreground">Credit line</dt><dd className="font-medium text-foreground">{waStatus.creditLineShared ? "Attached" : "Pending"}</dd></div>
              )}
            </dl>
            {(!waStatus.webhookSubscribed || (waStatus.connectedVia === "manual" && (!waStatus.hasMetaAppSecret || !waStatus.hasWebhookVerifyToken))) && (
              <div className="space-y-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4">
                <p className="text-[13px] text-foreground">
                  {waStatus.connectedVia === "manual" && (!waStatus.hasMetaAppSecret || !waStatus.hasWebhookVerifyToken)
                    ? "Inbound messages won't arrive until your Meta App Secret and webhook verify token are saved. Use credentials from your own Meta Developer app."
                    : "Inbound messages won't arrive until the webhook is subscribed. The WhatsApp Business Account ID was likely missing during connect."}
                </p>
                {waStatus.connectedVia === "manual" && !waStatus.hasMetaAppSecret && (
                  <Field>
                    <FieldLabel htmlFor="waFixSecret">Meta App Secret</FieldLabel>
                    <Input id="waFixSecret" type="password" value={waFixMetaAppSecret} onChange={(e) => setWaFixMetaAppSecret(e.target.value)} placeholder="Meta → Your App → Settings → Basic" />
                  </Field>
                )}
                {waStatus.connectedVia === "manual" && !waStatus.hasWebhookVerifyToken && (
                  <Field>
                    <FieldLabel htmlFor="waFixToken">Webhook verify token</FieldLabel>
                    <Input id="waFixToken" type="password" value={waFixWebhookVerifyToken} onChange={(e) => setWaFixWebhookVerifyToken(e.target.value)} placeholder="Same token set in Meta → Webhooks" />
                  </Field>
                )}
                <Button type="button" size="sm" onClick={handleResubscribe} disabled={waLoading}>
                  {waLoading ? "Subscribing…" : "Fix inbound messages"}
                </Button>
              </div>
            )}
            {whatsappNumbers.length > 0 && (
              <div className="rounded-xl border border-border p-4">
                <p className="text-sm font-medium text-foreground">Connected numbers</p>
                <ul className="mt-1 space-y-1 text-sm text-muted-foreground">
                  {whatsappNumbers.map((n) => (
                    <li key={n.id}>{n.displayPhoneNumber || n.phoneNumberId}{n.status !== "active" && ` (${n.status})`}</li>
                  ))}
                </ul>
              </div>
            )}
            <Button variant="outline" size="sm" onClick={handleDisconnect} disabled={waLoading}>
              Disconnect WhatsApp
            </Button>
          </div>
        ) : (
          <div className="space-y-5">
            {/* Stepper */}
            <ol className="flex items-center gap-1 rounded-xl bg-muted/40 p-3">
              {steps.map((s, idx, arr) => {
                const order = arr.map((x) => x.step)
                const done = order.indexOf(waStep) > idx
                const active = waStep === s.step
                return (
                  <li key={s.step} className="flex flex-1 items-center gap-2 last:flex-none">
                    <button
                      type="button"
                      onClick={() => done && setWaStep(s.step)}
                      disabled={!done && !active}
                      className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold transition-colors ${
                        active ? "border-foreground bg-foreground text-background"
                        : done ? "cursor-pointer border-muted-foreground bg-muted/40 text-foreground"
                        : "cursor-not-allowed border-border text-muted-foreground opacity-60"
                      }`}
                    >
                      {done ? <Check className="h-3.5 w-3.5" /> : idx + 1}
                    </button>
                    <span className="hidden min-w-0 sm:block">
                      <span className={`block truncate text-xs ${active ? "font-semibold text-foreground" : "text-muted-foreground"}`}>{s.title}</span>
                      <span className="block truncate text-[10px] text-muted-foreground">{s.desc}</span>
                    </span>
                    {idx < arr.length - 1 && <ChevronRight className="mx-1 hidden h-4 w-4 shrink-0 text-muted-foreground/60 md:block" />}
                  </li>
                )
              })}
            </ol>

            {/* Step 1: method */}
            {waStep === 1 && (
              <div className="space-y-4">
                <div className={`grid gap-3 ${manualConnectEnabled ? "sm:grid-cols-2" : ""}`}>
                  <button
                    type="button"
                    onClick={() => setWaMethod("embedded")}
                    className={`space-y-2 rounded-xl border p-4 text-left transition-colors ${waMethod === "embedded" ? "border-primary bg-primary/[0.05]" : "border-border hover:border-muted-foreground/40"}`}
                  >
                    <span className="flex items-center justify-between">
                      <span className="flex items-center gap-2 text-sm font-semibold text-foreground">
                        <Smartphone className="h-4 w-4" /> Facebook signup
                      </span>
                      <Badge variant="outline" className="text-[10px]">Recommended</Badge>
                    </span>
                    <span className="block text-xs leading-relaxed text-muted-foreground">
                      Two-minute setup. Log in with Facebook, pick your number, verify by SMS. No Developer account needed.
                    </span>
                  </button>
                  {manualConnectEnabled && (
                    <button
                      type="button"
                      onClick={() => setWaMethod("manual")}
                      className={`space-y-2 rounded-xl border p-4 text-left transition-colors ${waMethod === "manual" ? "border-primary bg-primary/[0.05]" : "border-border hover:border-muted-foreground/40"}`}
                    >
                      <span className="flex items-center justify-between">
                        <span className="flex items-center gap-2 text-sm font-semibold text-foreground">
                          <Settings2 className="h-4 w-4" /> Manual setup
                        </span>
                        <Badge variant="outline" className="text-[10px]">Advanced</Badge>
                      </span>
                      <span className="block text-xs leading-relaxed text-muted-foreground">
                        Use your own Meta Developer app: system-user token, phone ID, app secret, verify token.
                      </span>
                    </button>
                  )}
                </div>
                <div className="flex justify-end border-t border-border pt-4">
                  <Button type="button" onClick={() => setWaStep(waMethod === "embedded" ? 3 : 2)}>
                    {waMethod === "embedded" ? "Continue with Facebook" : "Next: checklist"}
                    <ChevronRight className="ml-1 h-4 w-4" />
                  </Button>
                </div>
              </div>
            )}

            {/* Step 2: manual checklist */}
            {waStep === 2 && manualConnectEnabled && (
              <div className="space-y-4">
                <div className="flex flex-col gap-2 rounded-xl border border-primary/20 bg-primary/5 p-4 text-[13px] sm:flex-row sm:items-center sm:justify-between">
                  <p className="text-foreground">Prefer the easy path? <strong>Facebook signup</strong> needs no Developer account.</p>
                  <Button type="button" size="sm" variant="outline" onClick={() => { setWaMethod("embedded"); setWaStep(3) }}>
                    Use Facebook signup
                  </Button>
                </div>
                <div className="rounded-xl border border-border p-4">
                  <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-foreground">
                    <ExternalLink className="h-3.5 w-3.5" /> Free Meta Developer account (60 seconds)
                    <a href="/docs/Meta_Developer_WhatsApp_Setup_Guide.pdf" target="_blank" rel="noreferrer" download="Meta_Developer_WhatsApp_Setup_Guide.pdf" className="ml-auto inline-flex items-center gap-1 text-[11px] font-medium normal-case underline underline-offset-2 hover:text-primary">
                      <FileText className="h-3 w-3" /> PDF guide
                    </a>
                  </p>
                  <ol className="mt-2 list-decimal space-y-1 pl-5 text-xs text-muted-foreground">
                    <li>Go to <a href="https://developers.facebook.com" target="_blank" rel="noreferrer" className="font-medium text-foreground underline">developers.facebook.com</a> and log in with Facebook.</li>
                    <li>Click <strong>Get Started</strong>, accept terms, confirm phone/email.</li>
                    <li>Choose <strong>Business Owner</strong> and finish.</li>
                  </ol>
                </div>
                {([
                  ["phoneReady", "Phone can receive OTP", "A number that can receive SMS or voice calls during setup."],
                  ["noConflict", "Not on mobile WhatsApp", "This number must not be logged into the regular WhatsApp app."],
                  ["metaAccess", "Meta admin access", "A free Meta Developer account on your Facebook login."],
                ] as const).map(([key, title, desc]) => (
                  <label key={key} className="flex cursor-pointer items-start gap-3 text-sm">
                    <input
                      type="checkbox"
                      checked={waChecklist[key]}
                      onChange={(e) => setWaChecklist((p) => ({ ...p, [key]: e.target.checked }))}
                      className="mt-1 h-4 w-4 rounded border-border"
                    />
                    <span>
                      <span className="block font-medium text-foreground">{title}</span>
                      <span className="block text-xs text-muted-foreground">{desc}</span>
                    </span>
                  </label>
                ))}
                <div className="flex items-center justify-between border-t border-border pt-4">
                  <Button type="button" variant="outline" onClick={() => setWaStep(1)}>
                    <ChevronLeft className="mr-1 h-4 w-4" /> Back
                  </Button>
                  <Button
                    type="button"
                    onClick={() => setWaStep(3)}
                    disabled={!waChecklist.phoneReady || !waChecklist.metaAccess || !waChecklist.noConflict}
                  >
                    Proceed to credentials <ChevronRight className="ml-1 h-4 w-4" />
                  </Button>
                </div>
              </div>
            )}

            {/* Step 3: connect */}
            {waStep === 3 && (
              <div className="space-y-4">
                <div>
                  <h3 className="text-sm font-semibold text-foreground">
                    {waMethod === "embedded" ? "Connect with Facebook" : "Enter Meta API credentials"}
                  </h3>
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    {waMethod === "embedded"
                      ? "One click opens Meta's official window — sign in, pick your number, verify with SMS."
                      : "Paste credentials from your own Meta Developer app."}
                  </p>
                </div>
                {waMethod === "embedded" ? (
                  <div className="space-y-3 rounded-xl bg-muted/40 p-4">
                    <ol className="list-decimal space-y-1 pl-5 text-xs text-muted-foreground">
                      <li>Log in with your Facebook account.</li>
                      <li>Select your Business Portfolio and WhatsApp profile.</li>
                      <li>Verify your number with the 6-digit SMS code.</li>
                    </ol>
                    <p className="text-xs text-muted-foreground">Tip: allow pop-ups for this site — we connect automatically, no tokens to copy.</p>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border/60 pt-3">
                      <Button type="button" variant="outline" size="sm" onClick={() => setWaStep(1)}>
                        <ChevronLeft className="mr-1 h-4 w-4" /> Back
                      </Button>
                      <Button
                        type="button"
                        size="sm"
                        onClick={handleEmbeddedSignup}
                        disabled={waEmbeddedLoading || waStatus?.platformBillingReady === false}
                      >
                        <Smartphone className="mr-1.5 h-4 w-4" />
                        {waEmbeddedLoading ? "Opening Meta…" : "Continue with Facebook"}
                      </Button>
                    </div>
                  </div>
                ) : manualConnectEnabled ? (
                  <form onSubmit={handleManualConnect} className="space-y-4 rounded-xl border border-border p-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                      <Field>
                        <FieldLabel htmlFor="wa-phone-id">Phone Number ID</FieldLabel>
                        <Input id="wa-phone-id" value={waManualPhoneNumberId} onChange={(e) => setWaManualPhoneNumberId(e.target.value)} placeholder="e.g. 10482019283719" required />
                      </Field>
                      <Field>
                        <FieldLabel htmlFor="wa-waba-id">WhatsApp Business Account ID</FieldLabel>
                        <Input id="wa-waba-id" value={waManualWabaId} onChange={(e) => setWaManualWabaId(e.target.value)} placeholder="e.g. 10928374910283" required />
                      </Field>
                      <Field>
                        <FieldLabel htmlFor="wa-token">Permanent access token</FieldLabel>
                        <Input id="wa-token" type="password" value={waManualAccessToken} onChange={(e) => setWaManualAccessToken(e.target.value)} placeholder="EAAG…" required />
                      </Field>
                      <Field>
                        <FieldLabel htmlFor="wa-secret">Meta App Secret</FieldLabel>
                        <Input id="wa-secret" type="password" value={waManualMetaAppSecret} onChange={(e) => setWaManualMetaAppSecret(e.target.value)} placeholder="App → Settings → Basic" required />
                      </Field>
                    </div>
                    <Field>
                      <FieldLabel htmlFor="wa-verify">Webhook verify token</FieldLabel>
                      <Input id="wa-verify" type="password" value={waManualWebhookVerifyToken} onChange={(e) => setWaManualWebhookVerifyToken(e.target.value)} placeholder="Same token set in Meta → Webhooks" required />
                    </Field>
                    <div className="space-y-1.5">
                      <p className="text-xs font-medium text-foreground">Webhook callback URL <span className="font-normal text-muted-foreground">(paste into Meta)</span></p>
                      <div className="flex items-center justify-between gap-2 rounded-lg border border-border bg-muted/40 p-2 pl-3">
                        <code className="truncate text-xs">{waStatus?.webhookUrl ?? "http://localhost:8080/api/whatsapp/webhook"}</code>
                        <Button type="button" variant="ghost" size="sm" className="h-7 shrink-0 gap-1" onClick={copyWebhookUrl}>
                          {copiedWebhook ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                          {copiedWebhook ? "Copied" : "Copy"}
                        </Button>
                      </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                      <Field>
                        <FieldLabel htmlFor="wa-display">Display phone <span className="font-normal text-muted-foreground">(optional)</span></FieldLabel>
                        <Input id="wa-display" value={waManualDisplayPhone} onChange={(e) => setWaManualDisplayPhone(e.target.value)} placeholder="+254712345678" />
                      </Field>
                      <Field>
                        <FieldLabel htmlFor="wa-pin">2FA PIN <span className="font-normal text-muted-foreground">(optional, 6 digits)</span></FieldLabel>
                        <Input id="wa-pin" type="password" inputMode="numeric" maxLength={6} value={waManualRegistrationPin} onChange={(e) => setWaManualRegistrationPin(e.target.value.replace(/\D/g, "").slice(0, 6))} placeholder="6-digit PIN" />
                      </Field>
                    </div>
                    <div className="flex items-center justify-between border-t border-border pt-4">
                      <Button type="button" variant="outline" size="sm" onClick={() => setWaStep(2)}>
                        <ChevronLeft className="mr-1 h-4 w-4" /> Back
                      </Button>
                      <Button type="submit" size="sm" disabled={waManualLoading}>
                        {waManualLoading ? "Connecting…" : "Connect manually"}
                      </Button>
                    </div>
                  </form>
                ) : null}
              </div>
            )}

            {/* Step 4: done */}
            {waStep === 4 && (
              <div className="space-y-3 rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4">
                <p className="flex items-center gap-2 text-sm font-medium text-foreground">
                  <Check className="h-4 w-4 text-emerald-500" /> WhatsApp connected
                </p>
                <p className="text-xs text-muted-foreground">
                  Create message templates below, then run broadcasts from the campaign wizard.
                </p>
              </div>
            )}
          </div>
        )}
      </SettingSection>

      {waStatus?.connected && (
        <SettingSection
          title="Message templates"
          description="Approved templates for outbound marketing and notifications. Meta approval required."
          actions={
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={tplLoading}
              onClick={async () => {
                setTplLoading(true)
                const res = await syncWhatsAppTemplates()
                setTplLoading(false)
                setWaMessage(res.message ?? null)
                if (res.success) await loadTemplates()
              }}
            >
              Sync from Meta
            </Button>
          }
        >
          <form
            className="grid gap-3 rounded-xl bg-muted/40 p-4 sm:grid-cols-2"
            onSubmit={async (e) => {
              e.preventDefault()
              setTplLoading(true)
              const res = await createWhatsAppTemplate({ name: tplName, body: tplBody, category: tplCategory })
              setTplLoading(false)
              setWaMessage(res.message ?? null)
              if (res.success) {
                setTplName("")
                setTplBody("")
                await loadTemplates()
              }
            }}
          >
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
              <Textarea value={tplBody} onChange={(e) => setTplBody(e.target.value)} placeholder="Hello {{1}}, your order is ready." required rows={2} />
            </Field>
            <div className="flex items-end">
              <Button type="submit" size="sm" disabled={tplLoading}>{tplLoading ? "Submitting…" : "Submit to Meta"}</Button>
            </div>
          </form>
          {waTemplates.length > 0 ? (
            <div className="overflow-hidden rounded-xl border border-border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="hidden sm:table-cell">Category</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {waTemplates.map((t) => (
                    <TableRow key={t.id}>
                      <TableCell>
                        <p className="font-medium">{t.name}</p>
                        <p className="max-w-xs truncate text-xs text-muted-foreground">{t.bodyPreview}</p>
                      </TableCell>
                      <TableCell><Badge variant={t.status === "approved" ? "default" : "secondary"}>{t.status}</Badge></TableCell>
                      <TableCell className="hidden sm:table-cell">{t.category}</TableCell>
                      <TableCell className="text-right">
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={async () => {
                            const res = await deleteWhatsAppTemplate(t.id)
                            setWaMessage(res.message ?? null)
                            if (res.success) await loadTemplates()
                          }}
                        >
                          Delete
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          ) : (
            <p className="text-sm text-muted-foreground">No templates yet — create one above or sync from Meta.</p>
          )}
          <Link
            href="/dashboard/whatsapp/campaigns"
            className="flex items-center gap-3 rounded-xl border border-primary/20 bg-primary/[0.04] px-4 py-3 text-sm transition-colors hover:bg-primary/[0.08]"
          >
            <Megaphone className="h-4 w-4 shrink-0 text-primary" />
            <span className="flex-1">
              <span className="block font-medium text-foreground">Send a broadcast</span>
              <span className="block text-xs text-muted-foreground">Templates and posters go out from the campaign wizard.</span>
            </span>
            <ChevronRight className="h-4 w-4 text-muted-foreground" />
          </Link>
        </SettingSection>
      )}
    </div>
  )
}
