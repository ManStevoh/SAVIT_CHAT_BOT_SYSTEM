"use client"

import { useCallback, useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Switch } from "@/components/ui/switch"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Badge } from "@/components/ui/badge"
import { apiRequest } from "@/lib/api-client"
import { useToast } from "@/hooks/use-toast"

type AiModelRow = {
  id: string
  modelKey: string
  displayName: string
  capability: string
  inputCostPerMillion: number
  outputCostPerMillion: number
  maxOutputTokens: number
  isEnabled: boolean
  isPlatformDefault: boolean
}

type AiProviderRow = {
  id: string
  slug: string
  name: string
  apiBaseUrl: string | null
  apiKeyConfigured: boolean
  isEnabled: boolean
  models: AiModelRow[]
}

export default function AdminAiModelsPage() {
  const [providers, setProviders] = useState<AiProviderRow[]>([])
  const [loading, setLoading] = useState(true)
  const [savingId, setSavingId] = useState<string | null>(null)
  const { toast } = useToast()

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const data = await apiRequest<{ providers: AiProviderRow[] }>("/api/admin/ai-config")
      setProviders(data.providers ?? [])
    } catch {
      toast({ title: "Failed to load AI configuration", variant: "destructive" })
    } finally {
      setLoading(false)
    }
  }, [toast])

  useEffect(() => {
    load()
  }, [load])

  async function saveProvider(provider: AiProviderRow, patch: Record<string, unknown>) {
    setSavingId(provider.id)
    try {
      await apiRequest(`/api/admin/ai-config/providers/${provider.id}`, {
        method: "PUT",
        body: patch,
      })
      toast({ title: `${provider.name} updated` })
      await load()
    } catch {
      toast({ title: "Save failed", variant: "destructive" })
    } finally {
      setSavingId(null)
    }
  }

  async function toggleModelDefault(model: AiModelRow) {
    try {
      await apiRequest(`/api/admin/ai-config/models/${model.id}`, {
        method: "PUT",
        body: { isPlatformDefault: !model.isPlatformDefault },
      })
      toast({ title: model.isPlatformDefault ? "Default cleared" : "Set as platform default" })
      await load()
    } catch {
      toast({ title: "Update failed", variant: "destructive" })
    }
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold">AI Models & Providers</h1>
        <p className="text-muted-foreground">Loading…</p>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">AI Models & Providers</h1>
        <p className="text-muted-foreground">
          Configure providers and assign platform defaults per capability: reasoning, fast chat, vision, embeddings, speech, and images.
        </p>
      </div>

      {providers.map((provider) => (
        <Card key={provider.id}>
          <CardHeader>
            <div className="flex items-center justify-between gap-4">
              <div>
                <CardTitle className="flex items-center gap-2">
                  {provider.name}
                  <Badge variant={provider.isEnabled ? "default" : "secondary"}>
                    {provider.isEnabled ? "Enabled" : "Disabled"}
                  </Badge>
                  {provider.apiKeyConfigured ? (
                    <Badge variant="outline">API key set</Badge>
                  ) : (
                    <Badge variant="destructive">No API key</Badge>
                  )}
                </CardTitle>
                <CardDescription>Slug: {provider.slug}</CardDescription>
              </div>
              <Switch
                checked={provider.isEnabled}
                onCheckedChange={(v) => saveProvider(provider, { isEnabled: v })}
              />
            </div>
          </CardHeader>
          <CardContent className="space-y-6">
            <FieldGroup className="grid gap-4 md:grid-cols-2">
              <Field>
                <FieldLabel>API base URL</FieldLabel>
                <Input
                  defaultValue={provider.apiBaseUrl ?? ""}
                  placeholder="https://api.openai.com/v1"
                  onBlur={(e) => {
                    if (e.target.value !== (provider.apiBaseUrl ?? "")) {
                      saveProvider(provider, { apiBaseUrl: e.target.value || null })
                    }
                  }}
                />
              </Field>
              <Field>
                <FieldLabel>API key</FieldLabel>
                <Input
                  type="password"
                  placeholder={provider.apiKeyConfigured ? "******** (leave blank to keep)" : "Paste API key"}
                  onBlur={(e) => {
                    if (e.target.value.trim()) {
                      saveProvider(provider, { apiKey: e.target.value.trim() })
                      e.target.value = ""
                    }
                  }}
                />
              </Field>
            </FieldGroup>

            <div className="space-y-2">
              <p className="text-sm font-medium text-foreground">Models</p>
              {provider.models.length === 0 ? (
                <p className="text-sm text-muted-foreground">No models configured.</p>
              ) : (
                <div className="overflow-x-auto rounded-lg border border-border">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-border bg-muted/40 text-left">
                        <th className="p-3">Model</th>
                        <th className="p-3">Capability</th>
                        <th className="p-3">Input $/1M</th>
                        <th className="p-3">Output $/1M</th>
                        <th className="p-3">Default</th>
                        <th className="p-3">On</th>
                      </tr>
                    </thead>
                    <tbody>
                      {provider.models.map((m) => (
                        <tr key={m.id} className="border-b border-border last:border-0">
                          <td className="p-3">
                            <div className="font-medium">{m.displayName}</div>
                            <div className="text-xs text-muted-foreground">{m.modelKey}</div>
                          </td>
                          <td className="p-3 capitalize">{m.capability}</td>
                          <td className="p-3">${m.inputCostPerMillion.toFixed(2)}</td>
                          <td className="p-3">${m.outputCostPerMillion.toFixed(2)}</td>
                          <td className="p-3">
                            <Button
                              type="button"
                              size="sm"
                              variant={m.isPlatformDefault ? "default" : "outline"}
                              onClick={() => toggleModelDefault(m)}
                            >
                              {m.isPlatformDefault ? "Default" : "Set default"}
                            </Button>
                          </td>
                          <td className="p-3">
                            <Switch
                              checked={m.isEnabled}
                              onCheckedChange={async (v) => {
                                await apiRequest(`/api/admin/ai-config/models/${m.id}`, {
                                  method: "PUT",
                                  body: { isEnabled: v },
                                })
                                await load()
                              }}
                            />
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </CardContent>
        </Card>
      ))}

      <Card>
        <CardHeader>
          <CardTitle>How companies choose models</CardTitle>
          <CardDescription>
            Companies can pick Auto (cheapest enabled model), Platform default, or a specific model in Dashboard → Settings → AI.
          </CardDescription>
        </CardHeader>
      </Card>
    </div>
  )
}
