"use client"

import { useState } from "react"
import { Brain, LineChart, RefreshCw, Search, Database } from "lucide-react"
import { PageHeader } from "@/components/shared/page-header"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import {
  useCompanyBrain,
  useKnowledgeVectorStatus,
  useOwnerAnalyticsInvestigations,
  type MemorySearchResult,
} from "@/lib/api-hooks"
import { investigateOwnerAnalytics, refreshCompanyBrain, searchBusinessMemory } from "@/lib/api-actions"

const EXAMPLE_QUESTIONS = [
  "Why did sales drop this week?",
  "Which products drive the most repeat orders?",
  "Should we restock low-inventory SKUs before the weekend?",
]

export default function BusinessIntelligencePage() {
  const { data: brainData, isLoading: brainLoading, mutate: refreshBrain } = useCompanyBrain()
  const { data: investigationsData, isLoading: invLoading, mutate: refreshInv } =
    useOwnerAnalyticsInvestigations()
  const { data: vectorData } = useKnowledgeVectorStatus()

  const [question, setQuestion] = useState("")
  const [period, setPeriod] = useState<"7d" | "30d" | "90d">("30d")
  const [busy, setBusy] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [latestInvestigation, setLatestInvestigation] = useState<Record<string, unknown> | null>(null)
  const [memoryQuery, setMemoryQuery] = useState("")
  const [memoryResults, setMemoryResults] = useState<MemorySearchResult[]>([])
  const [memoryCounts, setMemoryCounts] = useState<Record<string, number> | null>(null)

  const snapshot = brainData?.snapshot
  const digest = snapshot?.digest as Record<string, unknown> | undefined
  const commerce = snapshot?.commerceData as Record<string, unknown> | undefined

  const runInvestigate = async () => {
    if (!question.trim()) return
    setBusy("investigate")
    setMessage(null)
    const result = await investigateOwnerAnalytics({ question: question.trim(), period })
    setBusy(null)
    if (!result.success) {
      setMessage(result.message ?? "Investigation failed")
      return
    }
    setLatestInvestigation(result.investigation ?? null)
    setMessage("Investigation complete.")
    refreshInv()
  }

  const runBrainRefresh = async () => {
    setBusy("brain")
    const result = await refreshCompanyBrain()
    setBusy(null)
    setMessage(result.success ? "Brain snapshot refreshed." : (result.message ?? "Refresh failed"))
    refreshBrain()
  }

  const runMemorySearch = async () => {
    if (!memoryQuery.trim()) return
    setBusy("memory")
    setMessage(null)
    const result = await searchBusinessMemory({ query: memoryQuery.trim(), limit: 12 })
    setBusy(null)
    if (!result.success || !result.data) {
      setMessage(result.message ?? "Memory search failed")
      return
    }
    setMemoryResults(result.data.results)
    setMemoryCounts(result.data.counts)
    setMessage(`Found ${result.data.counts.total} result(s) across company memory.`)
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Business Intelligence"
        description="Unified company brain and owner analytics investigations with evidence-backed findings."
        icon={LineChart}
      />

      {message && <p className="text-sm text-muted-foreground">{message}</p>}

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Database className="h-5 w-5" />
            Memory search
          </CardTitle>
          <CardDescription>
            Search products, FAQs, investigations, timeline, and briefs in one query
            {vectorData?.vectorSearch?.pgvector ? " (pgvector enabled)" : ""}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="flex gap-2">
            <Input
              value={memoryQuery}
              onChange={(e) => setMemoryQuery(e.target.value)}
              placeholder="e.g. low stock, sales drop, repeat customers"
              onKeyDown={(e) => e.key === "Enter" && runMemorySearch()}
            />
            <Button disabled={busy !== null || !memoryQuery.trim()} onClick={runMemorySearch}>
              <Search className="h-4 w-4 mr-1" />
              Search
            </Button>
          </div>
          {memoryCounts && (
            <p className="text-xs text-muted-foreground">
              {memoryCounts.knowledge} knowledge · {memoryCounts.investigations} investigations ·{" "}
              {memoryCounts.timeline} timeline · {memoryCounts.briefs} briefs
            </p>
          )}
          {memoryResults.length > 0 && (
            <ul className="space-y-2">
              {memoryResults.map((r) => (
                <li key={`${r.source}-${r.sourceId}`} className="border rounded-md p-3 text-sm">
                  <div className="flex justify-between gap-2">
                    <span className="font-medium">{r.title}</span>
                    <Badge variant="outline">{r.source}</Badge>
                  </div>
                  {r.snippet && <p className="text-muted-foreground mt-1">{r.snippet}</p>}
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between gap-2">
              <div>
                <CardTitle className="flex items-center gap-2">
                  <Brain className="h-5 w-5" />
                  Company Brain
                </CardTitle>
                <CardDescription>Commerce + growth digest shared by agents</CardDescription>
              </div>
              <Button variant="outline" size="sm" disabled={busy !== null} onClick={runBrainRefresh}>
                <RefreshCw className="h-4 w-4 mr-1" />
                Refresh
              </Button>
            </div>
          </CardHeader>
          <CardContent className="space-y-3 text-sm">
            {brainLoading ? (
              <p className="text-muted-foreground">Loading brain snapshot…</p>
            ) : (
              <>
                {snapshot?.snapshotAt && (
                  <p className="text-xs text-muted-foreground">
                    Snapshot: {new Date(snapshot.snapshotAt).toLocaleString()}
                  </p>
                )}
                <p>{snapshot?.summaryText ?? "No brain snapshot yet — click Refresh."}</p>
                {commerce && (
                  <div className="flex flex-wrap gap-2">
                    {Object.entries(commerce).slice(0, 4).map(([k, v]) => (
                      <Badge key={k} variant="secondary">
                        {k}: {String(v)}
                      </Badge>
                    ))}
                  </div>
                )}
                {digest && Object.keys(digest).length > 0 && (
                  <pre className="text-xs bg-muted p-2 rounded overflow-auto max-h-40">
                    {JSON.stringify(digest, null, 2)}
                  </pre>
                )}
              </>
            )}
            {vectorData && (
              <p className="text-xs text-muted-foreground border-t pt-2">
                Vector search: {vectorData.vectorSearch?.message} ({vectorData.chunkCount} chunks)
              </p>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Search className="h-5 w-5" />
              Owner Analytics
            </CardTitle>
            <CardDescription>Ask business questions — evidence, findings, recommendations</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <FieldGroup>
              <Field>
                <FieldLabel>Question</FieldLabel>
                <Textarea
                  value={question}
                  onChange={(e) => setQuestion(e.target.value)}
                  placeholder="Why are sales down this month?"
                  rows={3}
                />
              </Field>
              <Field>
                <FieldLabel>Period</FieldLabel>
                <select
                  className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                  value={period}
                  onChange={(e) => setPeriod(e.target.value as "7d" | "30d" | "90d")}
                >
                  <option value="7d">Last 7 days</option>
                  <option value="30d">Last 30 days</option>
                  <option value="90d">Last 90 days</option>
                </select>
              </Field>
            </FieldGroup>
            <div className="flex flex-wrap gap-2">
              {EXAMPLE_QUESTIONS.map((q) => (
                <Button key={q} variant="ghost" size="sm" className="text-xs h-auto py-1" onClick={() => setQuestion(q)}>
                  {q}
                </Button>
              ))}
            </div>
            <Button disabled={busy !== null || !question.trim()} onClick={runInvestigate}>
              Investigate
            </Button>

            {latestInvestigation && (
              <div className="border rounded-md p-3 space-y-2 text-sm">
                <p className="font-medium">{String(latestInvestigation.question ?? "")}</p>
                {Array.isArray(latestInvestigation.findings) && latestInvestigation.findings.length > 0 && (
                  <ul className="list-disc pl-4 space-y-1">
                    {(latestInvestigation.findings as string[]).map((f, i) => (
                      <li key={i}>{f}</li>
                    ))}
                  </ul>
                )}
                {Array.isArray(latestInvestigation.recommendations) &&
                  latestInvestigation.recommendations.length > 0 && (
                    <div>
                      <p className="text-xs font-medium text-muted-foreground mb-1">Recommendations</p>
                      <ul className="list-disc pl-4 space-y-1">
                        {(latestInvestigation.recommendations as string[]).map((r, i) => (
                          <li key={i}>{r}</li>
                        ))}
                      </ul>
                    </div>
                  )}
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Recent investigations</CardTitle>
        </CardHeader>
        <CardContent>
          {invLoading ? (
            <p className="text-muted-foreground text-sm">Loading…</p>
          ) : (investigationsData?.investigations?.length ?? 0) === 0 ? (
            <p className="text-muted-foreground text-sm">No investigations yet.</p>
          ) : (
            <ul className="space-y-3">
              {investigationsData?.investigations?.map((inv) => (
                <li key={inv.id} className="border rounded-md p-3 text-sm">
                  <div className="flex justify-between gap-2">
                    <span className="font-medium">{inv.question}</span>
                    <Badge variant="outline">{inv.period}</Badge>
                  </div>
                  {inv.createdAt && (
                    <p className="text-xs text-muted-foreground mt-1">
                      {new Date(inv.createdAt).toLocaleString()}
                    </p>
                  )}
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
