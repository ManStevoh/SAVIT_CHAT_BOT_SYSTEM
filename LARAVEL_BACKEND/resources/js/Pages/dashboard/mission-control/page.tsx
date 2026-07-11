"use client"

import Link from "next/link"
import { useState } from "react"
import { AlertTriangle, Brain, Radar, RefreshCw, TrendingUp } from "lucide-react"
import { PageHeader } from "@/components/shared/page-header"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { useMissionControl, useAgentTrustLogs } from "@/lib/api-hooks"
import { syncBusinessGraph, syncBusinessTimeline } from "@/lib/api-actions"
import { AiExplainabilityList } from "@/components/agent/AiExplainabilityCard"
import { BusinessGraphExplorer } from "@/components/agent/BusinessGraphExplorer"

const TYPE_LABELS: Record<string, string> = {
  approval: "Approval",
  commerce_event: "Commerce event",
  opportunity: "Opportunity",
  health: "Health",
}

export default function MissionControlPage() {
  const { data, isLoading, mutate } = useMissionControl()
  const { data: trustData, isLoading: trustLoading } = useAgentTrustLogs(8)
  const [busy, setBusy] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  const runSync = async (key: "timeline" | "graph", fn: () => Promise<{ success: boolean; message?: string }>) => {
    setBusy(key)
    setMessage(null)
    const result = await fn()
    setBusy(null)
    setMessage(result.success ? "Sync complete." : (result.message ?? "Sync failed"))
    mutate()
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Mission Control"
        description="Single attention inbox — brain digest, open events, approvals, opportunities, and business timeline."
        icon={Radar}
      />

      {message && <p className="text-sm text-muted-foreground">{message}</p>}

      <div className="flex flex-wrap gap-2">
        <Button
          variant="outline"
          size="sm"
          disabled={busy !== null}
          onClick={() => runSync("timeline", syncBusinessTimeline)}
        >
          <RefreshCw className="h-4 w-4 mr-1" />
          Sync timeline
        </Button>
        <Button
          variant="outline"
          size="sm"
          disabled={busy !== null}
          onClick={() => runSync("graph", syncBusinessGraph)}
        >
          <RefreshCw className="h-4 w-4 mr-1" />
          Sync business graph
        </Button>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Open events</CardDescription>
            <CardTitle className="text-2xl">{isLoading ? "…" : data?.counts.openEvents ?? 0}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Pending approvals</CardDescription>
            <CardTitle className="text-2xl">{isLoading ? "…" : data?.counts.pendingApprovals ?? 0}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Open opportunities</CardDescription>
            <CardTitle className="text-2xl">{isLoading ? "…" : data?.counts.openOpportunities ?? 0}</CardTitle>
          </CardHeader>
        </Card>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <AlertTriangle className="h-5 w-5" />
              Attention queue
            </CardTitle>
            <CardDescription>Prioritized items requiring owner awareness or action</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {isLoading ? (
              <p className="text-sm text-muted-foreground">Loading…</p>
            ) : (data?.attentionQueue?.length ?? 0) === 0 ? (
              <p className="text-sm text-muted-foreground">All clear — no urgent items right now.</p>
            ) : (
              data?.attentionQueue.map((item, i) => (
                <div key={`${item.type}-${item.id ?? i}`} className="rounded-lg border p-3 space-y-1">
                  <div className="flex items-center justify-between gap-2">
                    <p className="font-medium text-sm">{item.title}</p>
                    <Badge variant="secondary">{TYPE_LABELS[item.type] ?? item.type}</Badge>
                  </div>
                  {item.summary && <p className="text-sm text-muted-foreground">{item.summary}</p>}
                  {item.href && (
                    <Link href={item.href} className="text-xs text-primary underline">
                      Open →
                    </Link>
                  )}
                </div>
              ))
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Brain className="h-5 w-5" />
              Company brain
            </CardTitle>
            <CardDescription>
              {data?.healthScore
                ? `Health ${data.healthScore.overall}/100 · ${data.healthScore.date}`
                : "Unified digest from commerce + growth signals"}
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm">
            {isLoading ? (
              <p className="text-muted-foreground">Loading brain snapshot…</p>
            ) : (
              <>
                <p>{data?.brainSummary ?? "No brain summary yet."}</p>
                {data?.healthScore?.summary && (
                  <p className="text-muted-foreground">{data.healthScore.summary}</p>
                )}
                {(data?.topDecisions?.length ?? 0) > 0 && (
                  <div>
                    <p className="font-medium mb-1">Top decisions</p>
                    <ul className="list-disc pl-4 space-y-1 text-muted-foreground">
                      {data?.topDecisions.slice(0, 3).map((d, i) => (
                        <li key={i}>{d.decision}</li>
                      ))}
                    </ul>
                  </div>
                )}
              </>
            )}
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <TrendingUp className="h-5 w-5" />
              Business timeline
            </CardTitle>
            <CardDescription>Chronological narrative of company milestones</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {isLoading ? (
              <p className="text-sm text-muted-foreground">Loading timeline…</p>
            ) : (data?.recentTimeline?.length ?? 0) === 0 ? (
              <p className="text-sm text-muted-foreground">No timeline events yet — run Sync timeline.</p>
            ) : (
              data?.recentTimeline.map((event) => (
                <div key={event.id} className="border-l-2 border-primary/30 pl-3 py-1">
                  <p className="text-sm font-medium">{event.title}</p>
                  {event.summary && <p className="text-xs text-muted-foreground">{event.summary}</p>}
                  <p className="text-xs text-muted-foreground">
                    {event.occurredAt ? new Date(event.occurredAt).toLocaleString() : ""}
                    {event.category ? ` · ${event.category}` : ""}
                  </p>
                </div>
              ))
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Business graph</CardTitle>
            <CardDescription>Traversable nodes — products, orders, customers, campaigns</CardDescription>
          </CardHeader>
          <CardContent className="text-sm space-y-2">
            {isLoading ? (
              <p className="text-muted-foreground">Loading graph stats…</p>
            ) : (
              <>
                <p>
                  <span className="font-medium">{data?.graphStats.nodes ?? 0}</span> nodes ·{" "}
                  <span className="font-medium">{data?.graphStats.edges ?? 0}</span> edges
                </p>
                <Link href="/dashboard/business-intelligence" className="text-primary underline text-xs">
                  Explore investigations & brain →
                </Link>
              </>
            )}
          </CardContent>
        </Card>
      </div>

      <BusinessGraphExplorer />

      <Card>
        <CardHeader>
          <CardTitle>AI observability</CardTitle>
          <CardDescription>Why the agent made recent decisions — tools, data, and confidence</CardDescription>
        </CardHeader>
        <CardContent>
          <AiExplainabilityList logs={trustData?.logs ?? []} loading={trustLoading} />
        </CardContent>
      </Card>
    </div>
  )
}
