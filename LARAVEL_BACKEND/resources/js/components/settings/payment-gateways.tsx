"use client"

import { Check, Loader2 } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Field, FieldLabel } from "@/components/ui/field"
import { isMasked } from "./shared"

export type Env = "sandbox" | "production"

export interface ConfigSaveState {
  saving: boolean
  saved: boolean
}

/** Masked secret with Replace/Cancel flow (GET returns ••••…, PUT replaces). */
export function SecretField({
  label,
  value,
  onChange,
  placeholder,
  replacing,
  onReplace,
  onCancel,
  replaceLabel = "Replace",
}: {
  label: string
  value: string
  onChange: (v: string) => void
  placeholder: string
  replacing: boolean
  onReplace: () => void
  onCancel: () => void
  replaceLabel?: string
}) {
  return (
    <Field>
      <FieldLabel>{label}</FieldLabel>
      {isMasked(value) && !replacing ? (
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
          <Input type="text" readOnly className="font-mono text-sm" value={value} />
          <Button type="button" variant="outline" size="sm" className="shrink-0" onClick={onReplace}>
            {replaceLabel}
          </Button>
        </div>
      ) : (
        <div className="space-y-1">
          <Input type="password" placeholder={placeholder} value={value} onChange={(e) => onChange(e.target.value)} />
          {replacing && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-8 text-xs text-muted-foreground"
              onClick={onCancel}
            >
              Cancel replace
            </Button>
          )}
        </div>
      )}
    </Field>
  )
}

export function EnvSelect({
  label = "Environment",
  value,
  onChange,
  sandboxLabel = "Sandbox (testing)",
  productionLabel = "Production (live)",
}: {
  label?: string
  value: Env
  onChange: (v: Env) => void
  sandboxLabel?: string
  productionLabel?: string
}) {
  return (
    <Field>
      <FieldLabel>{label}</FieldLabel>
      <Select value={value} onValueChange={(v) => onChange(v as Env)}>
        <SelectTrigger><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value="sandbox">{sandboxLabel}</SelectItem>
          <SelectItem value="production">{productionLabel}</SelectItem>
        </SelectContent>
      </Select>
    </Field>
  )
}

export function ConfigStatusRow({
  title,
  configured,
  configuredLabel,
  onClear,
}: {
  title: string
  configured?: boolean
  configuredLabel: string
  onClear: () => void
}) {
  return (
    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <p className="text-sm font-semibold text-foreground">{title}</p>
      {configured ? (
        <div className="flex items-center gap-2">
          <Badge variant="default" className="gap-1 text-xs font-normal">
            <Check className="h-3 w-3" /> {configuredLabel}
          </Badge>
          <Button type="button" variant="outline" size="sm" className="h-7 text-xs" onClick={onClear}>
            Clear
          </Button>
        </div>
      ) : (
        <Badge variant="secondary" className="w-fit text-xs font-normal">
          Using platform default
        </Badge>
      )}
    </div>
  )
}

export function ConfigSaveFooter({
  state,
  onSave,
  label,
}: {
  state: ConfigSaveState
  onSave: () => void
  label: string
}) {
  return (
    <div className="flex items-center justify-end gap-2 border-t border-border/40 pt-3">
      <Button type="button" size="sm" disabled={state.saving} onClick={onSave} className="gap-1.5">
        {state.saving ? (
          <>
            <Loader2 className="h-3.5 w-3.5 animate-spin" /> Saving…
          </>
        ) : state.saved ? (
          <>
            <Check className="h-3.5 w-3.5" /> Saved!
          </>
        ) : (
          <>
            <Check className="h-3.5 w-3.5" /> {label}
          </>
        )}
      </Button>
    </div>
  )
}

/* ------------------------------- M-Pesa ------------------------------- */

export function MpesaConfigForm(props: {
  type: "paybill" | "till"
  setType: (v: "paybill" | "till") => void
  shortcode: string
  setShortcode: (v: string) => void
  passkey: string
  setPasskey: (v: string) => void
  consumerKey: string
  setConsumerKey: (v: string) => void
  consumerSecret: string
  setConsumerSecret: (v: string) => void
  env: Env
  setEnv: (v: Env) => void
  replacing: Record<string, boolean>
  setReplacing: (fn: (p: Record<string, boolean>) => Record<string, boolean>) => void
  configured?: boolean
  save: ConfigSaveState
  onSave: () => void
  onClear: () => void
  refresh: () => void
}) {
  const {
    type, setType, shortcode, setShortcode, passkey, setPasskey,
    consumerKey, setConsumerKey, consumerSecret, setConsumerSecret,
    env, setEnv, replacing, setReplacing, configured, save, onSave, onClear, refresh,
  } = props
  const cancelReplace = (key: string) => {
    setReplacing((p) => {
      const n = { ...p }
      delete n[key]
      return n
    })
    refresh()
  }
  return (
    <div className="space-y-4 rounded-lg bg-muted/40 p-4">
      <ConfigStatusRow title="M-Pesa account credentials (optional)" configured={props.configured} configuredLabel="Custom credentials active" onClear={onClear} />
      <p className="text-xs text-muted-foreground">
        Add your Lipa Na M-Pesa PayBill or Till number so payments go directly to your business.
      </p>
      <div className="grid gap-3 sm:grid-cols-2">
        <Field>
          <FieldLabel>Account type</FieldLabel>
          <Select value={type} onValueChange={(v) => setType(v as "paybill" | "till")}>
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="paybill">PayBill (business number)</SelectItem>
              <SelectItem value="till">Till (buy goods)</SelectItem>
            </SelectContent>
          </Select>
        </Field>
        <Field>
          <FieldLabel>{type === "till" ? "Till number" : "PayBill shortcode"}</FieldLabel>
          <Input
            placeholder={type === "till" ? "e.g. 123456" : "e.g. 174379"}
            value={shortcode}
            onChange={(e) => setShortcode(e.target.value)}
          />
        </Field>
      </div>
      <SecretField
        label="Lipa Na M-Pesa passkey"
        value={passkey}
        onChange={setPasskey}
        placeholder="Enter Lipa Na M-Pesa passkey"
        replacing={!!replacing["mpesa:passkey"]}
        onReplace={() => { setReplacing((p) => ({ ...p, ["mpesa:passkey"]: true })); setPasskey("") }}
        onCancel={() => cancelReplace("mpesa:passkey")}
        replaceLabel="Replace passkey"
      />
      <div className="grid gap-3 sm:grid-cols-2">
        <Field>
          <FieldLabel>Consumer key (optional)</FieldLabel>
          <Input placeholder="Daraja consumer key" value={consumerKey} onChange={(e) => setConsumerKey(e.target.value)} />
        </Field>
        <SecretField
          label="Consumer secret (optional)"
          value={consumerSecret}
          onChange={setConsumerSecret}
          placeholder="Daraja consumer secret"
          replacing={!!replacing["mpesa:consumer_secret"]}
          onReplace={() => { setReplacing((p) => ({ ...p, ["mpesa:consumer_secret"]: true })); setConsumerSecret("") }}
          onCancel={() => cancelReplace("mpesa:consumer_secret")}
        />
      </div>
      <EnvSelect
        value={env}
        onChange={setEnv}
        sandboxLabel="Sandbox (testing environment)"
        productionLabel="Production (live environment)"
      />
      <ConfigSaveFooter state={save} onSave={onSave} label="Save M-Pesa credentials" />
    </div>
  )
}

/* ------------------------------- Stripe ------------------------------- */

export function StripeConfigForm(props: {
  secret: string
  setSecret: (v: string) => void
  currency: string
  setCurrency: (v: string) => void
  env: Env
  setEnv: (v: Env) => void
  replacing: boolean
  setReplacing: (v: boolean) => void
  configured?: boolean
  save: ConfigSaveState
  onSave: () => void
  onClear: () => void
  refresh: () => void
}) {
  return (
    <div className="space-y-4 rounded-lg bg-muted/40 p-4">
      <ConfigStatusRow title="Stripe account credentials" configured={props.configured} configuredLabel="Custom Stripe configured" onClear={props.onClear} />
      <p className="text-xs text-muted-foreground">
        Add your Stripe secret key so chatbot payments settle directly to your Stripe account.
      </p>
      <div className="grid gap-3 sm:grid-cols-2">
        <SecretField
          label="Stripe secret key"
          value={props.secret}
          onChange={props.setSecret}
          placeholder="sk_live_... or sk_test_..."
          replacing={props.replacing}
          onReplace={() => { props.setReplacing(true); props.setSecret("") }}
          onCancel={() => { props.setReplacing(false); props.refresh() }}
          replaceLabel="Replace key"
        />
        <Field>
          <FieldLabel>Settlement currency</FieldLabel>
          <Input placeholder="kes, usd, eur, etc." value={props.currency} onChange={(e) => props.setCurrency(e.target.value)} />
        </Field>
      </div>
      <EnvSelect value={props.env} onChange={props.setEnv} />
      <ConfigSaveFooter state={props.save} onSave={props.onSave} label="Save Stripe credentials" />
    </div>
  )
}

/* ------------------------------ Paystack ------------------------------ */

export function PaystackConfigForm(props: {
  secretKey: string
  setSecretKey: (v: string) => void
  publicKey: string
  setPublicKey: (v: string) => void
  currency: string
  setCurrency: (v: string) => void
  env: Env
  setEnv: (v: Env) => void
  replacing: boolean
  setReplacing: (v: boolean) => void
  configured?: boolean
  save: ConfigSaveState
  onSave: () => void
  onClear: () => void
  refresh: () => void
}) {
  return (
    <div className="space-y-4 rounded-lg bg-muted/40 p-4">
      <ConfigStatusRow title="Paystack account credentials" configured={props.configured} configuredLabel="Custom Paystack configured" onClear={props.onClear} />
      <div className="grid gap-3 sm:grid-cols-2">
        <SecretField
          label="Secret key"
          value={props.secretKey}
          onChange={props.setSecretKey}
          placeholder="sk_live_... or sk_test_..."
          replacing={props.replacing}
          onReplace={() => { props.setReplacing(true); props.setSecretKey("") }}
          onCancel={() => { props.setReplacing(false); props.refresh() }}
          replaceLabel="Replace key"
        />
        <Field>
          <FieldLabel>Public key</FieldLabel>
          <Input placeholder="pk_live_... or pk_test_..." value={props.publicKey} onChange={(e) => props.setPublicKey(e.target.value)} />
        </Field>
        <Field>
          <FieldLabel>Currency</FieldLabel>
          <Input placeholder="kes, ngn, ghs, etc." value={props.currency} onChange={(e) => props.setCurrency(e.target.value)} />
        </Field>
        <div>
          <EnvSelect value={props.env} onChange={props.setEnv} />
        </div>
      </div>
      <ConfigSaveFooter state={props.save} onSave={props.onSave} label="Save Paystack credentials" />
    </div>
  )
}

/* ------------------------------ Pesapal ------------------------------- */

export function PesapalConfigForm(props: {
  consumerKey: string
  setConsumerKey: (v: string) => void
  consumerSecret: string
  setConsumerSecret: (v: string) => void
  currency: string
  setCurrency: (v: string) => void
  env: Env
  setEnv: (v: Env) => void
  replacing: boolean
  setReplacing: (v: boolean) => void
  configured?: boolean
  save: ConfigSaveState
  onSave: () => void
  onClear: () => void
  refresh: () => void
}) {
  return (
    <div className="space-y-4 rounded-lg bg-muted/40 p-4">
      <ConfigStatusRow title="Pesapal account credentials" configured={props.configured} configuredLabel="Custom Pesapal configured" onClear={props.onClear} />
      <div className="grid gap-3 sm:grid-cols-2">
        <Field>
          <FieldLabel>Consumer key</FieldLabel>
          <Input placeholder="Pesapal consumer key" value={props.consumerKey} onChange={(e) => props.setConsumerKey(e.target.value)} />
        </Field>
        <SecretField
          label="Consumer secret"
          value={props.consumerSecret}
          onChange={props.setConsumerSecret}
          placeholder="Pesapal consumer secret"
          replacing={props.replacing}
          onReplace={() => { props.setReplacing(true); props.setConsumerSecret("") }}
          onCancel={() => { props.setReplacing(false); props.refresh() }}
        />
        <Field>
          <FieldLabel>Currency</FieldLabel>
          <Input placeholder="kes, ugx, tzs, etc." value={props.currency} onChange={(e) => props.setCurrency(e.target.value)} />
        </Field>
        <div>
          <EnvSelect
            value={props.env}
            onChange={props.setEnv}
            sandboxLabel="Sandbox (cybqa.pesapal.com)"
            productionLabel="Production (pay.pesapal.com)"
          />
        </div>
      </div>
      <ConfigSaveFooter state={props.save} onSave={props.onSave} label="Save Pesapal credentials" />
    </div>
  )
}

/* ---------------------------- Flutterwave ----------------------------- */

export function FlutterwaveConfigForm(props: {
  publicKey: string
  setPublicKey: (v: string) => void
  secretKey: string
  setSecretKey: (v: string) => void
  secretHash: string
  setSecretHash: (v: string) => void
  currency: string
  setCurrency: (v: string) => void
  env: Env
  setEnv: (v: Env) => void
  replacing: boolean
  setReplacing: (v: boolean) => void
  configured?: boolean
  save: ConfigSaveState
  onSave: () => void
  onClear: () => void
  refresh: () => void
}) {
  return (
    <div className="space-y-4 rounded-lg bg-muted/40 p-4">
      <ConfigStatusRow title="Flutterwave account credentials" configured={props.configured} configuredLabel="Custom Flutterwave configured" onClear={props.onClear} />
      <div className="grid gap-3 sm:grid-cols-2">
        <Field>
          <FieldLabel>Public key</FieldLabel>
          <Input placeholder="FLWPUBK_..." value={props.publicKey} onChange={(e) => props.setPublicKey(e.target.value)} />
        </Field>
        <SecretField
          label="Secret key"
          value={props.secretKey}
          onChange={props.setSecretKey}
          placeholder="FLWSECK_..."
          replacing={props.replacing}
          onReplace={() => { props.setReplacing(true); props.setSecretKey("") }}
          onCancel={() => { props.setReplacing(false); props.refresh() }}
          replaceLabel="Replace key"
        />
        <Field>
          <FieldLabel>Webhook secret hash (optional)</FieldLabel>
          <Input placeholder="Secret hash for webhooks" value={props.secretHash} onChange={(e) => props.setSecretHash(e.target.value)} />
        </Field>
        <Field>
          <FieldLabel>Currency</FieldLabel>
          <Input placeholder="kes, ngn, ghs, etc." value={props.currency} onChange={(e) => props.setCurrency(e.target.value)} />
        </Field>
      </div>
      <EnvSelect value={props.env} onChange={props.setEnv} />
      <ConfigSaveFooter state={props.save} onSave={props.onSave} label="Save Flutterwave credentials" />
    </div>
  )
}

/* ------------------------------- PayPal ------------------------------- */

export function PayPalConfigForm(props: {
  clientId: string
  setClientId: (v: string) => void
  clientSecret: string
  setClientSecret: (v: string) => void
  currency: string
  setCurrency: (v: string) => void
  env: Env
  setEnv: (v: Env) => void
  replacing: boolean
  setReplacing: (v: boolean) => void
  configured?: boolean
  save: ConfigSaveState
  onSave: () => void
  onClear: () => void
  refresh: () => void
}) {
  return (
    <div className="space-y-4 rounded-lg bg-muted/40 p-4">
      <ConfigStatusRow title="PayPal account credentials" configured={props.configured} configuredLabel="Custom PayPal configured" onClear={props.onClear} />
      <div className="grid gap-3 sm:grid-cols-2">
        <Field>
          <FieldLabel>Client ID</FieldLabel>
          <Input placeholder="PayPal client ID" value={props.clientId} onChange={(e) => props.setClientId(e.target.value)} />
        </Field>
        <SecretField
          label="Client secret"
          value={props.clientSecret}
          onChange={props.setClientSecret}
          placeholder="PayPal client secret"
          replacing={props.replacing}
          onReplace={() => { props.setReplacing(true); props.setClientSecret("") }}
          onCancel={() => { props.setReplacing(false); props.refresh() }}
        />
        <Field>
          <FieldLabel>Currency</FieldLabel>
          <Input placeholder="usd, kes, eur, etc." value={props.currency} onChange={(e) => props.setCurrency(e.target.value)} />
        </Field>
        <div>
          <EnvSelect value={props.env} onChange={props.setEnv} />
        </div>
      </div>
      <ConfigSaveFooter state={props.save} onSave={props.onSave} label="Save PayPal credentials" />
    </div>
  )
}
