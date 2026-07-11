"use client"

import { useState } from "react"
import { ChevronDown, ChevronUp, ShieldCheck } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible"
import type { AgentTrustLogItem } from "@/lib/api-hooks"

function formatList(value: unknown): string[] {
  if (Array.isArray(value)) {
    return value.map((v) => (typeof v === "string" ? v : JSON.stringify(v)))
  }
  if (value && typeof value === "object") {
    return Object.entries(value as Record<string, unknown>).map(([k, v]) => `${k}: ${String(v)}`)
  }
  return []
}

export function AiExplainabilityCard({
  log,
  defaultOpen = false,
}: {
  log: AgentTrustLogItem
  defaultOpen?: boolean
}) {
  const [open, setOpen] = useState(defaultOpen)
  const tools = formatList(log.toolsUsed)
  const data = formatList(log.dataConsulted)
  const explain = log.explainability as Record<string, unknown> | null | undefined

  return (
    <Collapsible open={open} onOpenChange={setOpen}>
      <Card className="overflow-hidden">
        <CardHeader className="pb-2">
          <div className="flex items-start justify-between gap-2">
            <div className="space-y-1 min-w-0">
              <CardTitle className="text-sm flex items-center gap-2">
                <ShieldCheck className="h-4 w-4 shrink-0 text-primary" />
                <span className="truncate">{log.actionType ?? "Agent decision"}</span>
              </CardTitle>
              <CardDescription className="text-xs">
                {log.createdAt ? new Date(log.createdAt).toLocaleString() : "Recent"}
                {log.confidence != null && ` · ${Math.round(log.confidence * 100)}% confidence`}
              </CardDescription>
            </div>
            <CollapsibleTrigger asChild>
              <Button variant="ghost" size="sm" className="shrink-0 h-8 px-2">
                {open ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                <span className="sr-only">Toggle explainability</span>
              </Button>
            </CollapsibleTrigger>
          </div>
        </CardHeader>
        <CardContent className="space-y-2 text-sm">
          {log.reasoningSummary && (
            <p className="text-muted-foreground">{log.reasoningSummary}</p>
          )}
          {log.goal && (
            <p>
              <span className="font-medium">Goal:</span> {log.goal}
            </p>
          )}
          <CollapsibleContent className="space-y-3 pt-2 border-t">
            {tools.length > 0 && (
              <div>
                <p className="text-xs font-medium mb-1">Tools used</p>
                <div className="flex flex-wrap gap-1">
                  {tools.map((t) => (
                    <Badge key={t} variant="secondary" className="text-[10px]">
                      {t}
                    </Badge>
                  ))}
                </div>
              </div>
            )}
            {data.length > 0 && (
              <div>
                <p className="text-xs font-medium mb-1">Data consulted</p>
                <ul className="text-xs text-muted-foreground list-disc pl-4 space-y-0.5">
                  {data.slice(0, 8).map((d) => (
                    <li key={d}>{d}</li>
                  ))}
                </ul>
              </div>
            )}
            {explain && Object.keys(explain).length > 0 && (
              <div>
                <p className="text-xs font-medium mb-1">Explainability</p>
                <pre className="text-[11px] bg-muted/50 rounded p-2 overflow-x-auto whitespace-pre-wrap">
                  {JSON.stringify(explain, null, 2)}
                </pre>
              </div>
            )}
            {log.outcome && (
              <p className="text-xs">
                <span className="font-medium">Outcome:</span> {log.outcome}
              </p>
            )}
          </CollapsibleContent>
        </CardContent>
      </Card>
    </Collapsible>
  )
}

export function AiExplainabilityList({
  logs,
  loading,
  emptyMessage = "No AI decisions logged yet.",
}: {
  logs: AgentTrustLogItem[]
  loading?: boolean
  emptyMessage?: string
}) {
  if (loading) {
    return <p className="text-sm text-muted-foreground">Loading AI observability…</p>
  }
  if (logs.length === 0) {
    return <p className="text-sm text-muted-foreground">{emptyMessage}</p>
  }
  return (
    <div className="space-y-3">
      {logs.map((log) => (
        <AiExplainabilityCard key={log.id} log={log} />
      ))}
    </div>
  )
}
