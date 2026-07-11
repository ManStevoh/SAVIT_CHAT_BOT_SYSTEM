"use client"

import { useState } from "react"
import { Package, Puzzle, Trash2 } from "lucide-react"
import { PageHeader } from "@/components/shared/page-header"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { useMarketplaceModules } from "@/lib/api-hooks"
import { installMarketplaceModule, uninstallMarketplaceModule } from "@/lib/api-actions"

export default function MarketplacePage() {
  const { data, isLoading, mutate } = useMarketplaceModules()
  const [busy, setBusy] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [webhookUrl, setWebhookUrl] = useState("")

  const run = async (key: string, fn: () => Promise<{ success: boolean; message?: string }>) => {
    setBusy(key)
    setMessage(null)
    const result = await fn()
    setBusy(null)
    setMessage(result.success ? "Done." : (result.message ?? "Action failed"))
    mutate()
  }

  const modules = data?.modules ?? []
  const installed = data?.installed ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="AI Marketplace"
        description="Install industry skill modules and third-party agent capabilities."
        icon={Puzzle}
      />

      {message && <p className="text-sm text-muted-foreground">{message}</p>}

      {installed.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Installed modules</CardTitle>
            <CardDescription>Active prompt addons and tool packs for your agent.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {installed.map((mod) => (
              <div
                key={mod.moduleKey}
                className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3"
              >
                <div>
                  <p className="font-medium">{mod.name}</p>
                  <p className="text-sm text-muted-foreground">{mod.description}</p>
                  {mod.tools.length > 0 && (
                    <p className="text-xs text-muted-foreground mt-1">
                      Tools: {mod.tools.join(", ")}
                    </p>
                  )}
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={busy !== null}
                  onClick={() => run(`un-${mod.moduleKey}`, () => uninstallMarketplaceModule(mod.moduleKey))}
                >
                  <Trash2 className="h-4 w-4 mr-1" />
                  Uninstall
                </Button>
              </div>
            ))}
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Available modules</CardTitle>
          <CardDescription>
            SDK manifest: <code className="text-xs">GET /api/agent-sdk/v1/manifest</code>
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {isLoading && <p className="text-sm text-muted-foreground">Loading catalog…</p>}
          {!isLoading && modules.length === 0 && (
            <p className="text-sm text-muted-foreground">No modules in catalog yet.</p>
          )}
          {modules.map((mod) => (
            <div
              key={mod.moduleKey}
              className="flex flex-wrap items-start justify-between gap-3 rounded-md border p-3"
            >
              <div className="space-y-1 min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <Package className="h-4 w-4 text-muted-foreground shrink-0" />
                  <p className="font-medium">{mod.name}</p>
                  <Badge variant="secondary">{mod.category}</Badge>
                  {mod.isThirdParty && <Badge variant="outline">Third-party SDK</Badge>}
                  {mod.requiredPlan && <Badge variant="outline">{mod.requiredPlan}+</Badge>}
                </div>
                <p className="text-sm text-muted-foreground">{mod.description}</p>
                {mod.tools.length > 0 && (
                  <p className="text-xs text-muted-foreground">Tools: {mod.tools.join(", ")}</p>
                )}
              </div>
              <div className="flex flex-col gap-2 shrink-0">
                {mod.isInstalled ? (
                  <Badge>Installed</Badge>
                ) : (
                  <Button
                    size="sm"
                    disabled={!mod.canInstall || busy !== null}
                    onClick={() =>
                      run(`in-${mod.moduleKey}`, () =>
                        installMarketplaceModule(
                          mod.moduleKey,
                          mod.isThirdParty && webhookUrl.trim()
                            ? { webhook_base_url: webhookUrl.trim() }
                            : undefined
                        )
                      )
                    }
                  >
                    Install
                  </Button>
                )}
              </div>
            </div>
          ))}

          {modules.some((m) => m.isThirdParty && !m.isInstalled) && (
            <div className="rounded-md border bg-muted/30 p-3 space-y-2 mt-4">
              <Label htmlFor="webhookBase">Third-party webhook base URL</Label>
              <Input
                id="webhookBase"
                value={webhookUrl}
                onChange={(e) => setWebhookUrl(e.target.value)}
                placeholder="https://your-agent.example.com/api/savit"
              />
              <p className="text-xs text-muted-foreground">
                Required when installing SDK demo or external agent modules.
              </p>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
