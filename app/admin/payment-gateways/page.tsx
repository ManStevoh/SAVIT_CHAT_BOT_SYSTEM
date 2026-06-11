"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { CreditCard, ChevronDown, ChevronUp, Loader2 } from "lucide-react"
import { useAdminPaymentGateways } from "@/lib/api-hooks"
import { updatePaymentGateway } from "@/lib/api-actions"
import type { PaymentGateway } from "@/lib/mock-data"

const STRIPE_FIELDS = [
  { key: "key", label: "Publishable Key", type: "text", placeholder: "pk_test_..." },
  { key: "secret", label: "Secret Key", type: "password", placeholder: "sk_test_... (leave blank to keep)" },
  { key: "webhook_secret", label: "Webhook Secret", type: "password", placeholder: "whsec_... (leave blank to keep)" },
  { key: "trial_days", label: "Trial Days", type: "number", placeholder: "14" },
  { key: "currency", label: "Currency", type: "text", placeholder: "usd" },
] as const

const MPESA_FIELDS: { key: string; label: string; type: string; placeholder?: string; options?: string[] }[] = [
  { key: "consumer_key", label: "Consumer Key", type: "text", placeholder: "" },
  { key: "consumer_secret", label: "Consumer Secret", type: "password", placeholder: "Leave blank to keep" },
  { key: "shortcode", label: "Shortcode", type: "text", placeholder: "174379" },
  { key: "passkey", label: "Passkey", type: "password", placeholder: "Leave blank to keep" },
  { key: "env", label: "Environment", type: "select", options: ["sandbox", "production"] },
  { key: "callback_url", label: "Callback URL", type: "text", placeholder: "https://..." },
]

const PAYSTACK_FIELDS: { key: string; label: string; type: string; placeholder?: string; options?: string[] }[] = [
  { key: "public_key", label: "Public Key", type: "text", placeholder: "pk_test_..." },
  { key: "secret_key", label: "Secret Key", type: "password", placeholder: "sk_test_... (leave blank to keep)" },
  { key: "currency", label: "Currency", type: "text", placeholder: "ngn" },
  { key: "callback_url", label: "Callback URL (optional)", type: "text", placeholder: "https://yourapp.com/dashboard/subscription?checkout=success" },
]

function isMasked(val: unknown): boolean {
  return typeof val === "string" && val.startsWith("••••")
}

function secretReplaceKey(slug: string, fieldKey: string) {
  return `${slug}:${fieldKey}`
}

export default function AdminPaymentGatewaysPage() {
  const { data: gateways, error, isLoading, mutate } = useAdminPaymentGateways()
  const [expandedSlug, setExpandedSlug] = useState<string | null>(null)
  const [savingSlug, setSavingSlug] = useState<string | null>(null)
  const [form, setForm] = useState<Record<string, Record<string, string | number>>>({})
  /** User clicked "Replace" on a masked secret — show empty password field for a new value. */
  const [replacingSecret, setReplacingSecret] = useState<Record<string, boolean>>({})

  const updateForm = (slug: string, key: string, value: string | number) => {
    setForm((prev) => ({
      ...prev,
      [slug]: { ...(prev[slug] ?? {}), [key]: value },
    }))
  }

  const getDisplayConfig = (g: PaymentGateway) => {
    return { ...(g.config ?? {}), ...(form[g.slug] ?? {}) }
  }

  const handleToggle = async (g: PaymentGateway, enabled: boolean) => {
    setSavingSlug(g.slug)
    const res = await updatePaymentGateway(g.slug, { isEnabled: enabled })
    setSavingSlug(null)
    if (res.success) mutate()
  }

  const handleSaveConfig = async (g: PaymentGateway) => {
    const displayConfig = getDisplayConfig(g)
    const config: Record<string, string | number> = {}
    Object.entries(displayConfig).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== "" && !isMasked(v)) config[k] = v
    })
    setSavingSlug(g.slug)
    const res = await updatePaymentGateway(g.slug, { config })
    setSavingSlug(null)
    if (res.success) {
      setForm((prev) => {
        const next = { ...prev }
        delete next[g.slug]
        return next
      })
      setReplacingSecret({})
      mutate()
    }
  }

  if (isLoading && !gateways) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Payment Gateways</h1>
          <p className="text-muted-foreground">Configure and enable payment providers. Keys are stored in the database.</p>
        </div>
        <Card>
          <CardContent className="p-8">
            <div className="flex items-center justify-center gap-2 text-muted-foreground">
              <Loader2 className="h-5 w-5 animate-spin" />
              Loading gateways...
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Payment Gateways</h1>
          <p className="text-muted-foreground">Configure and enable payment providers.</p>
        </div>
        <Card className="border-destructive/50">
          <CardContent className="p-6">
            <p className="text-destructive">Failed to load gateways. Please try again.</p>
            <Button variant="outline" className="mt-2" onClick={() => mutate()}>Retry</Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  const list = gateways ?? []

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Payment Gateways</h1>
        <p className="text-muted-foreground">
          Configure and enable payment providers. Keys are stored in the database. Toggle each gateway on to activate it.
        </p>
      </div>

      <div className="space-y-4">
        {list.map((gateway) => {
          const expanded = expandedSlug === gateway.slug
          const saving = savingSlug === gateway.slug
          const displayConfig = getDisplayConfig(gateway)
          const fields =
            gateway.slug === "stripe"
              ? STRIPE_FIELDS
              : gateway.slug === "mpesa"
                ? MPESA_FIELDS
                : gateway.slug === "paystack"
                  ? PAYSTACK_FIELDS
                  : []

          return (
            <Card key={gateway.id}>
              <CardHeader className="flex flex-row items-center justify-between space-y-0">
                <div className="flex items-center gap-3">
                  <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                    <CreditCard className="h-5 w-5 text-primary" />
                  </div>
                  <div>
                    <CardTitle className="text-lg">{gateway.name}</CardTitle>
                    <CardDescription>{gateway.slug}</CardDescription>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <span className="text-sm text-muted-foreground">{gateway.isEnabled ? "Active" : "Inactive"}</span>
                  <Switch
                    checked={gateway.isEnabled}
                    onCheckedChange={(checked) => handleToggle(gateway, checked)}
                    disabled={saving}
                  />
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => setExpandedSlug(expanded ? null : gateway.slug)}
                  >
                    {expanded ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                  </Button>
                </div>
              </CardHeader>
              {expanded && (
                <CardContent className="border-t border-border pt-6">
                  <div className="grid gap-4 sm:grid-cols-2">
                    {fields.map((field) => {
                      const rk = secretReplaceKey(gateway.slug, field.key)
                      const raw = displayConfig[field.key]
                      const masked = isMasked(raw)
                      const showReplaceSecret =
                        field.type === "password" && masked && !replacingSecret[rk]

                      return (
                        <div key={field.key} className="space-y-2">
                          <Label htmlFor={`${gateway.slug}-${field.key}`}>{field.label}</Label>
                          {field.type === "select" ? (
                            <Select
                              value={String(displayConfig[field.key] ?? field.options?.[0] ?? "")}
                              onValueChange={(v) => updateForm(gateway.slug, field.key, v)}
                            >
                              <SelectTrigger id={`${gateway.slug}-${field.key}`}>
                                <SelectValue />
                              </SelectTrigger>
                              <SelectContent>
                                {(field.options ?? []).map((opt) => (
                                  <SelectItem key={opt} value={opt}>
                                    {opt}
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          ) : showReplaceSecret ? (
                            <div className="space-y-1.5">
                              <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <Input
                                  id={`${gateway.slug}-${field.key}`}
                                  type="text"
                                  readOnly
                                  className="font-mono text-sm"
                                  value={String(raw ?? "")}
                                />
                                <Button
                                  type="button"
                                  variant="outline"
                                  size="sm"
                                  className="shrink-0"
                                  onClick={() => {
                                    setReplacingSecret((p) => ({ ...p, [rk]: true }))
                                    updateForm(gateway.slug, field.key, "")
                                  }}
                                >
                                  Replace
                                </Button>
                              </div>
                              <p className="text-xs text-muted-foreground">
                                Stored secret (masked). Only the last 4 characters are shown after the dots. Use Replace to
                                enter a new value.
                              </p>
                            </div>
                          ) : (
                            <div className="space-y-1.5">
                              <Input
                                id={`${gateway.slug}-${field.key}`}
                                type={field.type}
                                placeholder={field.placeholder}
                                value={String(displayConfig[field.key] ?? "")}
                                onChange={(e) =>
                                  updateForm(
                                    gateway.slug,
                                    field.key,
                                    field.type === "number" ? parseInt(e.target.value, 10) || 0 : e.target.value
                                  )
                                }
                              />
                              {field.type === "password" && replacingSecret[rk] && (
                                <div className="flex items-center gap-2">
                                  <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 text-xs"
                                    onClick={() => {
                                      setReplacingSecret((p) => {
                                        const next = { ...p }
                                        delete next[rk]
                                        return next
                                      })
                                      setForm((prev) => {
                                        const g = prev[gateway.slug]
                                        if (!g) return prev
                                        const { [field.key]: _, ...rest } = g
                                        if (Object.keys(rest).length === 0) {
                                          const { [gateway.slug]: __, ...r } = prev
                                          return r
                                        }
                                        return { ...prev, [gateway.slug]: rest }
                                      })
                                    }}
                                  >
                                    Cancel replace
                                  </Button>
                                </div>
                              )}
                            </div>
                          )}
                        </div>
                      )
                    })}
                  </div>
                  <Button
                    className="mt-4"
                    onClick={() => handleSaveConfig(gateway)}
                    disabled={saving}
                  >
                    {saving ? (
                      <>
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        Saving...
                      </>
                    ) : (
                      "Save keys"
                    )}
                  </Button>
                </CardContent>
              )}
            </Card>
          )
        })}
      </div>
    </div>
  )
}
