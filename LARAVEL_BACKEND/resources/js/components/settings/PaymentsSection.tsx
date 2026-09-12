"use client"

import Link from "next/link"
import { useEffect, useState } from "react"
import {
  AlertCircle,
  Check,
  ChevronDown,
  Clock,
  CreditCard,
  FileText,
  Landmark,
  Loader2,
  Smartphone,
  Truck,
  Wallet,
  Waves,
  Zap,
} from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Switch } from "@/components/ui/switch"
import { Badge } from "@/components/ui/badge"
import { cn } from "@/lib/utils"
import { useCompanySettings, useSubscription } from "@/lib/api-hooks"
import { updateSettings, type UpdateSettingsData } from "@/lib/api-actions"
import { useSWRConfig } from "swr"
import { SettingSection, SettingRow } from "./shared"
import {
  MpesaConfigForm,
  StripeConfigForm,
  PaystackConfigForm,
  PesapalConfigForm,
  FlutterwaveConfigForm,
  PayPalConfigForm,
  ConfigSaveFooter,
  type Env,
} from "./payment-gateways"

const GATEWAYS: {
  id: string
  name: string
  tagline: string
  icon: typeof CreditCard
}[] = [
  { id: "stripe", name: "Stripe", tagline: "Cards & digital wallets", icon: CreditCard },
  { id: "paystack", name: "Paystack", tagline: "Multi-channel checkout", icon: Zap },
  { id: "pesapal", name: "Pesapal", tagline: "Cards, mobile money & bank", icon: Wallet },
  { id: "flutterwave", name: "Flutterwave", tagline: "Cards, mobile money & bank", icon: Waves },
  { id: "paypal", name: "PayPal", tagline: "Cards & PayPal balance", icon: Landmark },
]

export function PaymentsSection() {
  const { mutate } = useSWRConfig()
  const { data: settings } = useCompanySettings()
  const { data: subscription } = useSubscription()
  const isStarter = (subscription?.plan ?? "free") === "free"

  const [collectEnabled, setCollectEnabled] = useState(true)
  const [acceptMpesa, setAcceptMpesa] = useState(false)
  const [acceptStripe, setAcceptStripe] = useState(false)
  const [acceptPaystack, setAcceptPaystack] = useState(false)
  const [acceptPesapal, setAcceptPesapal] = useState(false)
  const [acceptFlutterwave, setAcceptFlutterwave] = useState(false)
  const [acceptPayPal, setAcceptPayPal] = useState(false)
  const [acceptCod, setAcceptCod] = useState(false)
  const [manualInstructions, setManualInstructions] = useState("")
  const [deliveryEnabled, setDeliveryEnabled] = useState(false)
  const [defaultFee, setDefaultFee] = useState("")
  const [freeAbove, setFreeAbove] = useState("")
  const [recoveryEnabled, setRecoveryEnabled] = useState(true)

  const [mpesaType, setMpesaType] = useState<"paybill" | "till">("paybill")
  const [mpesaShortcode, setMpesaShortcode] = useState("")
  const [mpesaPasskey, setMpesaPasskey] = useState("")
  const [mpesaConsumerKey, setMpesaConsumerKey] = useState("")
  const [mpesaConsumerSecret, setMpesaConsumerSecret] = useState("")
  const [mpesaEnv, setMpesaEnv] = useState<Env>("sandbox")
  const [stripeSecret, setStripeSecret] = useState("")
  const [stripeCurrency, setStripeCurrency] = useState("kes")
  const [stripeEnv, setStripeEnv] = useState<Env>("sandbox")
  const [paystackSecret, setPaystackSecret] = useState("")
  const [paystackPublic, setPaystackPublic] = useState("")
  const [paystackCurrency, setPaystackCurrency] = useState("kes")
  const [paystackEnv, setPaystackEnv] = useState<Env>("sandbox")
  const [pesapalKey, setPesapalKey] = useState("")
  const [pesapalSecret, setPesapalSecret] = useState("")
  const [pesapalCurrency, setPesapalCurrency] = useState("kes")
  const [pesapalEnv, setPesapalEnv] = useState<Env>("sandbox")
  const [flwPublic, setFlwPublic] = useState("")
  const [flwSecret, setFlwSecret] = useState("")
  const [flwHash, setFlwHash] = useState("")
  const [flwCurrency, setFlwCurrency] = useState("kes")
  const [flwEnv, setFlwEnv] = useState<Env>("sandbox")
  const [paypalId, setPaypalId] = useState("")
  const [paypalSecret, setPaypalSecret] = useState("")
  const [paypalCurrency, setPaypalCurrency] = useState("usd")
  const [paypalEnv, setPaypalEnv] = useState<Env>("sandbox")

  const [replacingMpesa, setReplacingMpesa] = useState<Record<string, boolean>>({})
  const [replacingStripe, setReplacingStripe] = useState(false)
  const [replacingPaystack, setReplacingPaystack] = useState(false)
  const [replacingPesapal, setReplacingPesapal] = useState(false)
  const [replacingFlw, setReplacingFlw] = useState(false)
  const [replacingPaypal, setReplacingPaypal] = useState(false)

  const [expandedGateway, setExpandedGateway] = useState<string | null>(null)
  const [manualOpen, setManualOpen] = useState(false)
  const [saving, setSaving] = useState<Record<string, boolean>>({})
  const [saved, setSaved] = useState<Record<string, boolean>>({})
  const [notice, setNotice] = useState<string | null>(null)

  useEffect(() => {
    if (!settings) return
    if (settings.ordersCollectPaymentEnabled != null) setCollectEnabled(settings.ordersCollectPaymentEnabled)
    if (settings.orderPaymentManualInstructions != null) setManualInstructions(settings.orderPaymentManualInstructions)
    if (settings.ordersAcceptMpesa != null) setAcceptMpesa(settings.ordersAcceptMpesa)
    if (settings.ordersAcceptStripe != null) setAcceptStripe(settings.ordersAcceptStripe)
    if (settings.ordersAcceptPaystack != null) setAcceptPaystack(settings.ordersAcceptPaystack)
    if (settings.ordersAcceptPesapal != null) setAcceptPesapal(settings.ordersAcceptPesapal)
    if (settings.ordersAcceptFlutterwave != null) setAcceptFlutterwave(settings.ordersAcceptFlutterwave)
    if (settings.ordersAcceptPayPal != null) setAcceptPayPal(settings.ordersAcceptPayPal)
    if (settings.ordersAcceptCod != null) setAcceptCod(settings.ordersAcceptCod)
    if (settings.deliveryFeesEnabled != null) setDeliveryEnabled(settings.deliveryFeesEnabled)
    if (settings.defaultDeliveryFee != null) setDefaultFee(String(settings.defaultDeliveryFee))
    if (settings.freeDeliveryAbove != null) setFreeAbove(String(settings.freeDeliveryAbove))
    if (settings.paymentRecoveryEnabled != null) setRecoveryEnabled(settings.paymentRecoveryEnabled)

    const mpc = settings.orderPaymentMpesaConfig
    if (mpc) {
      if (mpc.type === "till" || mpc.type === "paybill") setMpesaType(mpc.type)
      if (mpc.shortcode) setMpesaShortcode(mpc.shortcode)
      if (mpc.passkey) setMpesaPasskey(mpc.passkey)
      setMpesaConsumerKey(mpc.consumer_key ?? "")
      setMpesaConsumerSecret(mpc.consumer_secret ?? "")
      if (mpc.env === "production" || mpc.env === "sandbox") setMpesaEnv(mpc.env)
    } else if (settings.orderPaymentMpesaConfigured === false) {
      setMpesaShortcode(""); setMpesaPasskey(""); setMpesaConsumerKey(""); setMpesaConsumerSecret("")
      setMpesaType("paybill"); setMpesaEnv("sandbox")
    }
    const st = settings.orderPaymentStripeConfig
    if (st) {
      if (st.secret) setStripeSecret(st.secret)
      if (st.currency) setStripeCurrency(st.currency)
      if (st.env === "production" || st.env === "sandbox") setStripeEnv(st.env)
    } else if (settings.orderPaymentStripeConfigured === false) {
      setStripeSecret(""); setStripeCurrency("kes"); setStripeEnv("sandbox")
    }
    const ps = settings.orderPaymentPaystackConfig
    if (ps) {
      if (ps.secret_key) setPaystackSecret(ps.secret_key)
      if (ps.public_key) setPaystackPublic(ps.public_key)
      if (ps.currency) setPaystackCurrency(ps.currency)
      if (ps.env === "production" || ps.env === "sandbox") setPaystackEnv(ps.env)
    } else if (settings.orderPaymentPaystackConfigured === false) {
      setPaystackSecret(""); setPaystackPublic(""); setPaystackCurrency("kes"); setPaystackEnv("sandbox")
    }
    const pes = settings.orderPaymentPesapalConfig
    if (pes) {
      if (pes.consumer_key) setPesapalKey(pes.consumer_key)
      if (pes.consumer_secret) setPesapalSecret(pes.consumer_secret)
      if (pes.currency) setPesapalCurrency(pes.currency)
      if (pes.env === "production" || pes.env === "sandbox") setPesapalEnv(pes.env)
    } else if (settings.orderPaymentPesapalConfigured === false) {
      setPesapalKey(""); setPesapalSecret(""); setPesapalCurrency("kes"); setPesapalEnv("sandbox")
    }
    const flw = settings.orderPaymentFlutterwaveConfig
    if (flw) {
      if (flw.public_key) setFlwPublic(flw.public_key)
      if (flw.secret_key) setFlwSecret(flw.secret_key)
      if (flw.secret_hash) setFlwHash(flw.secret_hash)
      if (flw.currency) setFlwCurrency(flw.currency)
      if (flw.env === "production" || flw.env === "sandbox") setFlwEnv(flw.env)
    } else if (settings.orderPaymentFlutterwaveConfigured === false) {
      setFlwPublic(""); setFlwSecret(""); setFlwHash(""); setFlwCurrency("kes"); setFlwEnv("sandbox")
    }
    const ppl = settings.orderPaymentPayPalConfig
    if (ppl) {
      if (ppl.client_id) setPaypalId(ppl.client_id)
      if (ppl.client_secret) setPaypalSecret(ppl.client_secret)
      if (ppl.currency) setPaypalCurrency(ppl.currency)
      if (ppl.env === "production" || ppl.env === "sandbox") setPaypalEnv(ppl.env)
    } else if (settings.orderPaymentPayPalConfigured === false) {
      setPaypalId(""); setPaypalSecret(""); setPaypalCurrency("usd"); setPaypalEnv("sandbox")
    }
  }, [settings])

  const refresh = () => mutate("company-settings")
  const markSaving = (k: string, v: boolean) => setSaving((p) => ({ ...p, [k]: v }))
  const markSaved = (k: string) => {
    setSaved((p) => ({ ...p, [k]: true }))
    setTimeout(() => setSaved((p) => ({ ...p, [k]: false })), 3000)
  }

  const toggleOption = async (
    key: string,
    setter: (v: boolean) => void,
    value: boolean,
    payloadKey: keyof UpdateSettingsData
  ) => {
    setter(value)
    markSaving(key, true)
    const res = await updateSettings({ [payloadKey]: value } as UpdateSettingsData)
    markSaving(key, false)
    if (res.success) {
      markSaved(key)
      refresh()
    }
  }

  const saveWith = async (key: string, payload: UpdateSettingsData, after?: () => void) => {
    markSaving(key, true)
    const res = await updateSettings(payload)
    markSaving(key, false)
    if (res.success) {
      markSaved(key)
      after?.()
      refresh()
    }
  }

  const save = (k: string) => ({ saving: !!saving[k], saved: !!saved[k] })

  const gatewayOn = (id: string) =>
    id === "stripe" ? acceptStripe
    : id === "paystack" ? acceptPaystack
    : id === "pesapal" ? acceptPesapal
    : id === "flutterwave" ? acceptFlutterwave
    : acceptPayPal

  const gatewayConfigured = (id: string) =>
    id === "stripe" ? settings?.orderPaymentStripeConfigured
    : id === "paystack" ? settings?.orderPaymentPaystackConfigured
    : id === "pesapal" ? settings?.orderPaymentPesapalConfigured
    : id === "flutterwave" ? settings?.orderPaymentFlutterwaveConfigured
    : settings?.orderPaymentPayPalConfigured

  const toggleGateway = (id: string, v: boolean) => {
    if (id === "stripe") return toggleOption("stripeToggle", setAcceptStripe, v, "ordersAcceptStripe")
    if (id === "paystack") return toggleOption("paystackToggle", setAcceptPaystack, v, "ordersAcceptPaystack")
    if (id === "pesapal") return toggleOption("pesapalToggle", setAcceptPesapal, v, "ordersAcceptPesapal")
    if (id === "flutterwave") return toggleOption("flutterwaveToggle", setAcceptFlutterwave, v, "ordersAcceptFlutterwave")
    return toggleOption("paypalToggle", setAcceptPayPal, v, "ordersAcceptPayPal")
  }

  const saveMpesa = () => saveWith("mpesaConfig", {
    orderPaymentMpesaConfig: {
      type: mpesaType,
      shortcode: mpesaShortcode.trim(),
      passkey: mpesaPasskey.trim(),
      consumer_key: mpesaConsumerKey.trim() || undefined,
      consumer_secret: mpesaConsumerSecret.trim() || undefined,
      env: mpesaEnv,
    },
  }, () => setReplacingMpesa({}))

  const clearMpesa = async () => {
    const res = await updateSettings({ orderPaymentMpesaConfig: null })
    setNotice(res.success ? "M-Pesa credentials cleared — platform default will be used." : (res.message ?? "Couldn't clear."))
    if (res.success) {
      setMpesaShortcode(""); setMpesaPasskey(""); setMpesaConsumerKey(""); setMpesaConsumerSecret("")
      setReplacingMpesa({})
      refresh()
    }
  }

  const clearStripe = async () => {
    const res = await updateSettings({ orderPaymentStripeConfig: null })
    setNotice(res.success ? "Stripe credentials cleared." : (res.message ?? "Couldn't clear."))
    if (res.success) {
      setStripeSecret(""); setReplacingStripe(false); refresh()
    }
  }

  return (
    <div className="space-y-4">
      {isStarter && (
        <p className="rounded-xl border border-border bg-muted/40 px-4 py-3 text-[13px] leading-relaxed text-muted-foreground">
          <span className="font-semibold text-foreground">M-Pesa is included on Starter</span> — connect it
          below to collect payment on orders. Card payments and multi-gateway checkout shine on{" "}
          <Link href="/dashboard/subscription#plans" className="font-medium text-primary hover:underline">
            Growth
          </Link>
          .
        </p>
      )}
      {notice && (
        <p className="rounded-xl border border-border bg-muted/40 px-4 py-2.5 text-[13px] text-muted-foreground">
          {notice}
        </p>
      )}

      <SettingSection
        title="Getting paid"
        description="Turn collection off to confirm orders without upfront payment."
        badge={
          collectEnabled ? (
            <Badge className="gap-1 text-[11px] font-normal"><Check className="h-3 w-3" /> Collecting</Badge>
          ) : (
            <Badge variant="secondary" className="text-[11px] font-normal">Paused</Badge>
          )
        }
        actions={
          <div className="flex items-center gap-2">
            {saving["collectPayment"] && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}
            <Switch
              checked={collectEnabled}
              onCheckedChange={(v) => toggleOption("collectPayment", setCollectEnabled, v, "ordersCollectPaymentEnabled")}
              disabled={saving["collectPayment"]}
            />
          </div>
        }
      >
        {!collectEnabled && (
          <p className="flex items-center gap-2 rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
            <AlertCircle className="h-4 w-4 shrink-0" />
            Payment collection is off. The assistant will skip payment options and confirm orders immediately.
          </p>
        )}
      </SettingSection>

      {/* M-Pesa: first-class on every plan */}
      <SettingSection
        title="M-Pesa"
        description="Instant STK push during checkout — the way most of your customers pay."
        badge={
          settings?.orderPaymentMpesaConfigured ? (
            <Badge className="text-[11px] font-normal">Custom connected</Badge>
          ) : (
            <Badge variant="secondary" className="text-[11px] font-normal">Platform default</Badge>
          )
        }
        actions={
          <div className="flex items-center gap-2">
            {saving["mpesaToggle"] && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}
            <Switch
              checked={acceptMpesa}
              onCheckedChange={(v) => toggleOption("mpesaToggle", setAcceptMpesa, v, "ordersAcceptMpesa")}
              disabled={!collectEnabled || saving["mpesaToggle"]}
            />
          </div>
        }
      >
        {acceptMpesa ? (
          <MpesaConfigForm
            type={mpesaType} setType={setMpesaType}
            shortcode={mpesaShortcode} setShortcode={setMpesaShortcode}
            passkey={mpesaPasskey} setPasskey={setMpesaPasskey}
            consumerKey={mpesaConsumerKey} setConsumerKey={setMpesaConsumerKey}
            consumerSecret={mpesaConsumerSecret} setConsumerSecret={setMpesaConsumerSecret}
            env={mpesaEnv} setEnv={setMpesaEnv}
            replacing={replacingMpesa} setReplacing={setReplacingMpesa}
            configured={settings?.orderPaymentMpesaConfigured}
            save={save("mpesaConfig")} onSave={saveMpesa} onClear={clearMpesa} refresh={refresh}
          />
        ) : (
          <p className="flex items-center gap-2 text-[13px] text-muted-foreground">
            <Smartphone className="h-4 w-4" /> Turn on M-Pesa to accept mobile money on every order.
          </p>
        )}
      </SettingSection>

      {/* Other gateways: picker rows, one open at a time */}
      <SettingSection title="More ways to get paid" description="Cards and alternative checkouts. Expand one to configure.">
        <div className="divide-y divide-border rounded-xl border border-border">
          {GATEWAYS.map((g) => {
            const on = gatewayOn(g.id)
            const open = expandedGateway === g.id
            return (
              <div key={g.id}>
                <button
                  type="button"
                  onClick={() => setExpandedGateway(open ? null : g.id)}
                  className="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-muted/40"
                >
                  <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted text-foreground">
                    <g.icon className="h-4 w-4" />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="flex flex-wrap items-center gap-2">
                      <span className="text-sm font-semibold text-foreground">{g.name}</span>
                      {gatewayConfigured(g.id) ? (
                        <Badge variant="default" className="text-[10px] font-normal">Configured</Badge>
                      ) : on ? (
                        <Badge variant="secondary" className="text-[10px] font-normal">On · platform default</Badge>
                      ) : null}
                    </span>
                    <span className="block truncate text-xs text-muted-foreground">{g.tagline}</span>
                  </span>
                  <span onClick={(e) => e.stopPropagation()} className="flex items-center gap-1">
                    {saving[`${g.id}Toggle`] && <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />}
                    <Switch
                      checked={on}
                      onCheckedChange={(v) => toggleGateway(g.id, v)}
                      disabled={!collectEnabled || saving[`${g.id}Toggle`]}
                    />
                    <ChevronDown className={cn("h-4 w-4 text-muted-foreground transition-transform", open && "rotate-180")} />
                  </span>
                </button>
                {open && (
                  <div className="border-t border-border/60 px-4 py-4">
                    {g.id === "stripe" && (
                      <StripeConfigForm
                        secret={stripeSecret} setSecret={setStripeSecret}
                        currency={stripeCurrency} setCurrency={setStripeCurrency}
                        env={stripeEnv} setEnv={setStripeEnv}
                        replacing={replacingStripe} setReplacing={setReplacingStripe}
                        configured={settings?.orderPaymentStripeConfigured}
                        save={save("stripeConfig")}
                        onSave={() => saveWith("stripeConfig", {
                          orderPaymentStripeConfig: { secret: stripeSecret.trim(), currency: stripeCurrency.trim() || "kes", env: stripeEnv },
                        }, () => setReplacingStripe(false))}
                        onClear={clearStripe} refresh={refresh}
                      />
                    )}
                    {g.id === "paystack" && (
                      <PaystackConfigForm
                        secretKey={paystackSecret} setSecretKey={setPaystackSecret}
                        publicKey={paystackPublic} setPublicKey={setPaystackPublic}
                        currency={paystackCurrency} setCurrency={setPaystackCurrency}
                        env={paystackEnv} setEnv={setPaystackEnv}
                        replacing={replacingPaystack} setReplacing={setReplacingPaystack}
                        configured={settings?.orderPaymentPaystackConfigured}
                        save={save("paystackConfig")}
                        onSave={() => saveWith("paystackConfig", {
                          ordersAcceptPaystack: true,
                          orderPaymentPaystackConfig: { secret_key: paystackSecret, public_key: paystackPublic, currency: paystackCurrency.trim() || "kes", env: paystackEnv },
                        }, () => setReplacingPaystack(false))}
                        onClear={() => saveWith("paystackConfig", { orderPaymentPaystackConfig: null }, () => {
                          setPaystackSecret(""); setPaystackPublic(""); setReplacingPaystack(false)
                        })}
                        refresh={refresh}
                      />
                    )}
                    {g.id === "pesapal" && (
                      <PesapalConfigForm
                        consumerKey={pesapalKey} setConsumerKey={setPesapalKey}
                        consumerSecret={pesapalSecret} setConsumerSecret={setPesapalSecret}
                        currency={pesapalCurrency} setCurrency={setPesapalCurrency}
                        env={pesapalEnv} setEnv={setPesapalEnv}
                        replacing={replacingPesapal} setReplacing={setReplacingPesapal}
                        configured={settings?.orderPaymentPesapalConfigured}
                        save={save("pesapalConfig")}
                        onSave={() => saveWith("pesapalConfig", {
                          ordersAcceptPesapal: true,
                          orderPaymentPesapalConfig: { consumer_key: pesapalKey.trim(), consumer_secret: pesapalSecret.trim(), currency: pesapalCurrency.trim() || "kes", env: pesapalEnv },
                        }, () => setReplacingPesapal(false))}
                        onClear={() => saveWith("pesapalConfig", { orderPaymentPesapalConfig: null }, () => {
                          setPesapalKey(""); setPesapalSecret(""); setReplacingPesapal(false)
                        })}
                        refresh={refresh}
                      />
                    )}
                    {g.id === "flutterwave" && (
                      <FlutterwaveConfigForm
                        publicKey={flwPublic} setPublicKey={setFlwPublic}
                        secretKey={flwSecret} setSecretKey={setFlwSecret}
                        secretHash={flwHash} setSecretHash={setFlwHash}
                        currency={flwCurrency} setCurrency={setFlwCurrency}
                        env={flwEnv} setEnv={setFlwEnv}
                        replacing={replacingFlw} setReplacing={setReplacingFlw}
                        configured={settings?.orderPaymentFlutterwaveConfigured}
                        save={save("flutterwaveConfig")}
                        onSave={() => saveWith("flutterwaveConfig", {
                          ordersAcceptFlutterwave: true,
                          orderPaymentFlutterwaveConfig: { public_key: flwPublic.trim(), secret_key: flwSecret.trim(), secret_hash: flwHash.trim(), currency: flwCurrency.trim() || "kes", env: flwEnv },
                        }, () => setReplacingFlw(false))}
                        onClear={() => saveWith("flutterwaveConfig", { orderPaymentFlutterwaveConfig: null }, () => {
                          setFlwPublic(""); setFlwSecret(""); setFlwHash(""); setReplacingFlw(false)
                        })}
                        refresh={refresh}
                      />
                    )}
                    {g.id === "paypal" && (
                      <PayPalConfigForm
                        clientId={paypalId} setClientId={setPaypalId}
                        clientSecret={paypalSecret} setClientSecret={setPaypalSecret}
                        currency={paypalCurrency} setCurrency={setPaypalCurrency}
                        env={paypalEnv} setEnv={setPaypalEnv}
                        replacing={replacingPaypal} setReplacing={setReplacingPaypal}
                        configured={settings?.orderPaymentPayPalConfigured}
                        save={save("paypalConfig")}
                        onSave={() => saveWith("paypalConfig", {
                          ordersAcceptPayPal: true,
                          orderPaymentPayPalConfig: { client_id: paypalId.trim(), client_secret: paypalSecret.trim(), currency: paypalCurrency.trim() || "usd", env: paypalEnv },
                        }, () => setReplacingPaypal(false))}
                        onClear={() => saveWith("paypalConfig", { orderPaymentPayPalConfig: null }, () => {
                          setPaypalId(""); setPaypalSecret(""); setReplacingPaypal(false)
                        })}
                        refresh={refresh}
                      />
                    )}
                  </div>
                )}
              </div>
            )
          })}
        </div>
      </SettingSection>

      <SettingSection title="Cash & manual" description="For customers who pay outside checkout.">
        <SettingRow
          label="Cash on delivery"
          hint="Order is confirmed immediately, paid on arrival"
          control={
            <span className="flex items-center gap-2">
              {saving["codToggle"] && <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />}
              <Switch
                checked={acceptCod}
                onCheckedChange={(v) => toggleOption("codToggle", setAcceptCod, v, "ordersAcceptCod")}
                disabled={!collectEnabled || saving["codToggle"]}
              />
            </span>
          }
        />
        <div className="rounded-xl border border-border">
          <button
            type="button"
            onClick={() => setManualOpen((v) => !v)}
            className="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-muted/40"
          >
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted text-foreground">
              <FileText className="h-4 w-4" />
            </span>
            <span className="flex-1">
              <span className="block text-sm font-semibold text-foreground">Manual payment notes</span>
              <span className="block text-xs text-muted-foreground">Till numbers, bank details, custom instructions</span>
            </span>
            <ChevronDown className={cn("h-4 w-4 text-muted-foreground transition-transform", manualOpen && "rotate-180")} />
          </button>
          {manualOpen && (
            <div className="space-y-3 border-t border-border/60 p-4">
              <Textarea
                placeholder="e.g. Pay via M-Pesa to Till 123456 (MyShop). Include the order number as reference."
                value={manualInstructions}
                onChange={(e) => setManualInstructions(e.target.value)}
                rows={3}
                className="text-sm"
                disabled={!collectEnabled}
              />
              <ConfigSaveFooter
                state={save("manualInstructions")}
                onSave={() => saveWith("manualInstructions", { orderPaymentManualInstructions: manualInstructions.trim() || null })}
                label="Save instructions"
              />
            </div>
          )}
        </div>
      </SettingSection>

      <SettingSection title="Delivery & recovery" description="Fulfillment fees and unpaid-order follow-ups.">
        <SettingRow
          label="Delivery fees"
          hint="Add shipping costs to orders automatically"
          control={
            <span className="flex items-center gap-2">
              {saving["deliveryFeesToggle"] && <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />}
              <Switch
                checked={deliveryEnabled}
                onCheckedChange={(v) => toggleOption("deliveryFeesToggle", setDeliveryEnabled, v, "deliveryFeesEnabled")}
                disabled={saving["deliveryFeesToggle"]}
              />
            </span>
          }
        />
        {deliveryEnabled && (
          <div className="grid gap-3 rounded-xl bg-muted/40 p-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <p className="text-xs font-medium text-foreground">Default fee</p>
              <Input type="number" placeholder="0" value={defaultFee} onChange={(e) => setDefaultFee(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <p className="text-xs font-medium text-foreground">Free above (optional)</p>
              <Input type="number" placeholder="e.g. 5000" value={freeAbove} onChange={(e) => setFreeAbove(e.target.value)} />
            </div>
            <div className="sm:col-span-2">
              <ConfigSaveFooter
                state={save("deliveryFeesConfig")}
                onSave={() => saveWith("deliveryFeesConfig", {
                  defaultDeliveryFee: defaultFee.trim() ? parseFloat(defaultFee) : undefined,
                  freeDeliveryAbove: freeAbove.trim() ? parseFloat(freeAbove) : null,
                })}
                label="Save delivery fees"
              />
            </div>
          </div>
        )}
        <SettingRow
          label="Payment recovery"
          hint="WhatsApp reminders with a payment link for unpaid orders"
          control={
            <span className="flex items-center gap-2">
              {saving["paymentRecoveryToggle"] && <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />}
              <Switch
                checked={recoveryEnabled}
                onCheckedChange={(v) => toggleOption("paymentRecoveryToggle", setRecoveryEnabled, v, "paymentRecoveryEnabled")}
                disabled={saving["paymentRecoveryToggle"]}
              />
            </span>
          }
        />
        <p className="flex items-start gap-2 rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
          <Clock className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
          Unpaid order follow-ups go out automatically to recover abandoned checkouts.
        </p>
      </SettingSection>

      <p className="flex items-center gap-2 text-xs text-muted-foreground">
        <Truck className="h-3.5 w-3.5" />
        Delivery zones and taxes live under
        <Link href="/dashboard/delivery" className="font-medium text-primary hover:underline">Delivery</Link>
        and
        <Link href="/dashboard/taxes" className="font-medium text-primary hover:underline">Taxes</Link>
        .
      </p>
    </div>
  )
}
