"use client"

import { useState } from "react"
import { Activity, AlertTriangle, Bot, Play, RefreshCw } from "lucide-react"
import { PageHeader } from "@/components/shared/page-header"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  useCommerceEvents,
  useCommerceIntegrations,
  useCommerceOwnerAlerts,
  useCommerceSpecialistRuns,
} from "@/lib/api-hooks"
import {
  acknowledgeCommerceEvent,
  connectCommerceIntegration,
  detectCommerceEvents,
  disconnectCommerceIntegration,
  processCommerceAlerts,
  runCommerceSpecialistPipeline,
  syncCommerceIntegration,
} from "@/lib/api-actions"

export default function AgentOpsPage() {
  const { data: runsData, isLoading: runsLoading, mutate: refreshRuns } = useCommerceSpecialistRuns()
  const { data: alertsData, isLoading: alertsLoading, mutate: refreshAlerts } = useCommerceOwnerAlerts()
  const { data: eventsData, isLoading: eventsLoading, mutate: refreshEvents } = useCommerceEvents()
  const { data: integrationsData, isLoading: intLoading, mutate: refreshIntegrations } = useCommerceIntegrations()
  const [busy, setBusy] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  const runAction = async (key: string, fn: () => Promise<{ success: boolean; message?: string }>) => {
    setBusy(key)
    setMessage(null)
    const result = await fn()
    setBusy(null)
    setMessage(result.success ? "Done." : (result.message ?? "Action failed"))
    refreshRuns()
    refreshAlerts()
    refreshEvents()
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Agent Ops"
        description="Specialist runs, commerce events, and owner alerts (low stock, sales drop)."
        icon={Activity}
      />

      {message && <p className="text-sm text-muted-foreground">{message}</p>}

      <div className="flex flex-wrap gap-2">
        <Button
          variant="outline"
          size="sm"
          disabled={busy !== null}
          onClick={() => runAction("detect", detectCommerceEvents)}
        >
          <RefreshCw className="h-4 w-4 mr-1" />
          Detect events
        </Button>
        <Button
          variant="outline"
          size="sm"
          disabled={busy !== null}
          onClick={() => runAction("alerts", processCommerceAlerts)}
        >
          <AlertTriangle className="h-4 w-4 mr-1" />
          Process owner alerts
        </Button>
        <Button
          size="sm"
          disabled={busy !== null}
          onClick={() => runAction("pipeline", runCommerceSpecialistPipeline)}
        >
          <Play className="h-4 w-4 mr-1" />
          Run specialist pipeline
        </Button>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <AlertTriangle className="h-4 w-4" />
              Owner alerts
            </CardTitle>
            <CardDescription>Low stock and sales drop — detected by the event brain.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {alertsLoading && <p className="text-sm text-muted-foreground">Loading…</p>}
            {(alertsData?.alerts?.length ?? 0) === 0 && !alertsLoading && (
              <p className="text-sm text-muted-foreground">No owner alerts yet.</p>
            )}
            {alertsData?.alerts?.map((a) => (
              <div key={a.id} className="rounded-lg border p-3 text-sm">
                <div className="flex items-center justify-between gap-2">
                  <span className="font-medium">{a.eventType.replace(/_/g, " ")}</span>
                  <Badge variant={a.status === "open" ? "destructive" : "secondary"}>{a.status}</Badge>
                </div>
                <p className="text-muted-foreground mt-1 text-xs">
                  {(a.payload?.summary as string) ?? JSON.stringify(a.payload ?? {})}
                </p>
                {a.status === "open" && (
                  <Button
                    className="mt-2"
                    size="sm"
                    variant="ghost"
                    onClick={() => acknowledgeCommerceEvent(a.id).then(() => refreshAlerts())}
                  >
                    Acknowledge
                  </Button>
                )}
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Bot className="h-4 w-4" />
              Specialist runs
            </CardTitle>
            <CardDescription>Sales, support, and inventory background agents.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {runsLoading && <p className="text-sm text-muted-foreground">Loading…</p>}
            {(runsData?.runs?.length ?? 0) === 0 && !runsLoading && (
              <p className="text-sm text-muted-foreground">No runs yet. Trigger the pipeline above.</p>
            )}
            {runsData?.runs?.slice(0, 12).map((run) => (
              <div key={run.id} className="rounded-lg border p-3 text-sm">
                <div className="flex items-center justify-between">
                  <span className="font-medium capitalize">{run.agentType}</span>
                  <Badge variant="outline">{run.status}</Badge>
                </div>
                {run.output && (
                  <p className="text-xs text-muted-foreground mt-1 line-clamp-2">
                    {JSON.stringify(run.output).slice(0, 120)}…
                  </p>
                )}
              </div>
            ))}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Commerce integrations</CardTitle>
          <CardDescription>DHL, Sendy, CRM webhook, ERP inventory, and shipping APIs.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {intLoading && <p className="text-sm text-muted-foreground">Loading…</p>}
          {integrationsData?.connectors?.map((c) => (
            <div key={c.type} className="rounded-lg border p-3 text-sm flex flex-wrap items-center justify-between gap-2">
              <div>
                <span className="font-medium">{c.name}</span>
                <p className="text-xs text-muted-foreground">{c.status_label}</p>
              </div>
              <div className="flex gap-2">
                {c.connected ? (
                  <>
                    <Badge variant="default">Connected</Badge>
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() =>
                        syncCommerceIntegration(c.type).then(() => refreshIntegrations())
                      }
                    >
                      Sync
                    </Button>
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() =>
                        disconnectCommerceIntegration(c.type).then(() => refreshIntegrations())
                      }
                    >
                      Disconnect
                    </Button>
                  </>
                ) : (
                  (c.type === "weather" || c.type === "delivery_status") && (
                    <Badge variant="secondary">Built-in</Badge>
                  )
                )}
              </div>
            </div>
          ))}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>All commerce events</CardTitle>
          <CardDescription>Delivery delays, birthdays, stock, and sales signals.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-2">
          {eventsLoading && <p className="text-sm text-muted-foreground">Loading…</p>}
          {eventsData?.events?.slice(0, 20).map((e) => (
            <div key={e.id} className="flex items-center justify-between text-sm border-b py-2">
              <span>{e.eventType}</span>
              <Badge variant="secondary">{e.status}</Badge>
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  )
}
