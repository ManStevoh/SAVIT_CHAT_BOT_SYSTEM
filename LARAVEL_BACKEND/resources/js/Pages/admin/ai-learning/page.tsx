"use client"

import { useEffect, useState } from "react"
import Link from "next/link"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Progress } from "@/components/ui/progress"
import { Brain, Database, RefreshCw, Shield, Trash2, Check, X } from "lucide-react"
import { useAdminAiLearning } from "@/lib/api-hooks"
import {
  purgeAiLearningSamples,
  pruneExpiredAiLearningSamples,
  syncFaqEmbeddingsAdmin,
  syncLearningEmbeddingsAdmin,
  syncProductEmbeddingsAdmin,
  listAiLearningSamples,
  reviewAiLearningSample,
  type AiLearningSampleRow,
} from "@/lib/api-actions"
import { useToast } from "@/hooks/use-toast"

export default function AdminAiLearningPage() {
  const { data, error, isLoading, mutate } = useAdminAiLearning()
  const { toast } = useToast()
  const [busy, setBusy] = useState<string | null>(null)
  const [purgeConfirm, setPurgeConfirm] = useState("")
  const [pendingSamples, setPendingSamples] = useState<AiLearningSampleRow[]>([])

  const stats = data?.stats
  const config = data?.config

  useEffect(() => {
    if ((stats?.pendingReviewSamples ?? 0) > 0 || config?.requireLearningReview) {
      listAiLearningSamples({ status: "pending", perPage: 20 })
        .then((res) => setPendingSamples(res.samples))
        .catch(() => setPendingSamples([]))
    } else {
      setPendingSamples([])
    }
  }, [stats?.pendingReviewSamples, config?.requireLearningReview, data])

  const runAction = async (
    key: string,
    fn: () => Promise<{ success: boolean; message?: string }>,
  ) => {
    setBusy(key)
    try {
      const res = await fn()
      if (res.success) {
        toast({ title: res.message ?? "Done" })
        mutate()
      } else {
        toast({ title: res.message ?? "Action failed", variant: "destructive" })
      }
    } catch {
      toast({ title: "Action failed", variant: "destructive" })
    } finally {
      setBusy(null)
    }
  }

  if (isLoading && !data) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold">AI Learning</h1>
        <div className="grid gap-4 md:grid-cols-3">
          {[1, 2, 3].map((i) => (
            <Card key={i}><CardContent className="p-6 h-24 animate-pulse bg-muted rounded-lg m-4" /></Card>
          ))}
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="space-y-6">
        <h1 className="text-2xl font-bold">AI Learning</h1>
        <Card className="border-destructive/50">
          <CardContent className="p-6 text-destructive">Failed to load AI learning stats.</CardContent>
        </Card>
      </div>
    )
  }

  const faqCoverage = stats?.embeddingCoveragePercent ?? 0
  const learningCoverage = stats?.learningEmbeddingCoveragePercent ?? 0
  const samplesBySource = stats?.samplesBySource ?? {}

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-foreground">AI Learning &amp; Knowledge</h1>
          <p className="text-muted-foreground">
            RAG-style memory and FAQ embeddings — not model fine-tuning. Configure policy in{" "}
            <Link href="/admin/settings?tab=integrations" className="text-primary underline">Platform Settings → Integrations</Link>.
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={() => mutate()} disabled={!!busy}>
          <RefreshCw className="h-4 w-4 mr-2" />
          Refresh
        </Button>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Learning samples</CardDescription>
            <CardTitle className="text-3xl">{stats?.totalLearningSamples ?? 0}</CardTitle>
          </CardHeader>
          <CardContent className="text-sm text-muted-foreground">
            {stats?.companiesWithSamples ?? 0} companies with memory
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>FAQ embedding coverage</CardDescription>
            <CardTitle className="text-3xl">{faqCoverage}%</CardTitle>
          </CardHeader>
          <CardContent>
            <Progress value={faqCoverage} className="h-2" />
            <p className="text-xs text-muted-foreground mt-2">
              {stats?.faqsWithEmbeddings ?? 0} / {stats?.activeFaqs ?? 0} active FAQs
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Learning embedding coverage</CardDescription>
            <CardTitle className="text-3xl">{learningCoverage}%</CardTitle>
          </CardHeader>
          <CardContent>
            <Progress value={learningCoverage} className="h-2" />
            <p className="text-xs text-muted-foreground mt-2">
              {stats?.learningSamplesWithEmbeddings ?? 0} / {stats?.approvedLearningSamples ?? 0} approved samples · {config?.embeddingModelKey ?? "text-embedding-3-small"}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Retention policy</CardDescription>
            <CardTitle className="text-3xl">{config?.retentionDays ?? 365}d</CardTitle>
          </CardHeader>
          <CardContent className="text-sm text-muted-foreground">
            Max {config?.maxSamplesPerCompany ?? 200} samples / company · {config?.maxPromptTokens ?? 12000} prompt tokens
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>PII redaction</CardDescription>
            <CardTitle className="text-3xl flex items-center gap-2">
              <Shield className="h-6 w-6" />
              {config?.piiRedactionEnabled ? "On" : "Off"}
            </CardTitle>
          </CardHeader>
          <CardContent className="text-sm text-muted-foreground">
            Learning {config?.learningEnabled ? "enabled" : "disabled"} platform-wide
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Pending review</CardDescription>
            <CardTitle className="text-3xl">{stats?.pendingReviewSamples ?? 0}</CardTitle>
          </CardHeader>
          <CardContent className="text-sm text-muted-foreground">
            {config?.requireLearningReview ? "Human review required" : "Auto-approved"}
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>Low-quality samples</CardDescription>
            <CardTitle className="text-3xl">{stats?.learningQuality?.lowQualitySamples ?? 0}</CardTitle>
          </CardHeader>
          <CardContent className="text-sm text-muted-foreground">
            {stats?.learningQuality?.deadSamples ?? 0} approved samples never retrieved
          </CardContent>
        </Card>
      </div>

      {((stats?.pendingReviewSamples ?? 0) > 0 || config?.requireLearningReview) && (
        <Card>
          <CardHeader>
            <CardTitle>Review queue ({stats?.pendingReviewSamples ?? 0} pending)</CardTitle>
            <CardDescription>Approve samples before they are used in AI prompts</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
          {(stats?.pendingReviewSamples ?? 0) === 0 && config?.requireLearningReview ? (
              <p className="text-sm text-muted-foreground">No samples awaiting review.</p>
            ) : pendingSamples.length === 0 ? (
              <p className="text-sm text-muted-foreground">Loading pending samples…</p>
            ) : (
              pendingSamples.map((sample) => (
                <div key={sample.id} className="rounded-lg border p-4 space-y-2 text-sm">
                  <div className="flex justify-between gap-2">
                    <span className="font-medium">{sample.companyName}</span>
                    <span className="text-muted-foreground capitalize">{sample.source}{sample.language ? ` · ${sample.language}` : ""}</span>
                  </div>
                  <p><span className="text-muted-foreground">Q:</span> {sample.customerMessage}</p>
                  <p><span className="text-muted-foreground">A:</span> {sample.assistantReply}</p>
                  <div className="flex gap-2 pt-2">
                    <Button
                      size="sm"
                      disabled={!!busy}
                      onClick={async () => {
                        setBusy(`approve-${sample.id}`)
                        const res = await reviewAiLearningSample(sample.id, { action: "approve" })
                        if (res.success) {
                          setPendingSamples((prev) => prev.filter((s) => s.id !== sample.id))
                          mutate()
                        }
                        setBusy(null)
                      }}
                    >
                      <Check className="h-4 w-4 mr-1" /> Approve
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={!!busy}
                      onClick={async () => {
                        setBusy(`reject-${sample.id}`)
                        const res = await reviewAiLearningSample(sample.id, { action: "reject" })
                        if (res.success) {
                          setPendingSamples((prev) => prev.filter((s) => s.id !== sample.id))
                          mutate()
                        }
                        setBusy(null)
                      }}
                    >
                      <X className="h-4 w-4 mr-1" /> Reject
                    </Button>
                  </div>
                </div>
              ))
            )}
          </CardContent>
        </Card>
      )}

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Brain className="h-5 w-5" />
              Memory by source
            </CardTitle>
            <CardDescription>WhatsApp AI, FAQ fallbacks, and human agent pairs</CardDescription>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            {Object.keys(samplesBySource).length === 0 ? (
              <p className="text-muted-foreground">No learning samples stored yet.</p>
            ) : (
              Object.entries(samplesBySource).map(([source, count]) => (
                <div key={source} className="flex justify-between border-b border-border py-2">
                  <span className="capitalize">{source}</span>
                  <span className="font-medium">{count}</span>
                </div>
              ))
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Database className="h-5 w-5" />
              Top companies by samples
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            {(stats?.topCompaniesBySamples ?? []).length === 0 ? (
              <p className="text-muted-foreground">No data yet.</p>
            ) : (
              stats?.topCompaniesBySamples?.map((row) => (
                <div key={row.companyId} className="flex justify-between border-b border-border py-2">
                  <span>{row.companyName}</span>
                  <span className="font-medium">{row.samples}</span>
                </div>
              ))
            )}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Maintenance actions</CardTitle>
          <CardDescription>GDPR erasure, retention enforcement, and vector sync</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-wrap gap-3">
            <Button
              variant="outline"
              disabled={!!busy}
              onClick={() => runAction("sync-faq", () => syncFaqEmbeddingsAdmin())}
            >
              {busy === "sync-faq" ? "Syncing…" : "Sync FAQ embeddings"}
            </Button>
            <Button
              variant="outline"
              disabled={!!busy}
              onClick={() => runAction("sync-learning", () => syncLearningEmbeddingsAdmin())}
            >
              {busy === "sync-learning" ? "Syncing…" : "Sync learning embeddings"}
            </Button>
            <Button
              variant="outline"
              disabled={!!busy}
              onClick={() => runAction("sync-products", () => syncProductEmbeddingsAdmin())}
            >
              {busy === "sync-products" ? "Syncing…" : "Sync product embeddings"}
            </Button>
            <Button
              variant="outline"
              disabled={!!busy}
              onClick={() => runAction("prune", () => pruneExpiredAiLearningSamples())}
            >
              {busy === "prune" ? "Pruning…" : "Prune expired samples"}
            </Button>
          </div>

          <div className="rounded-lg border border-destructive/30 p-4 space-y-3">
            <p className="text-sm font-medium text-destructive">GDPR: erase all learning memory</p>
            <p className="text-xs text-muted-foreground">
              Type <code className="bg-muted px-1 rounded">DELETE_ALL_LEARNING_DATA</code> to confirm permanent deletion of all conversation learning samples.
            </p>
            <input
              className="flex h-9 w-full max-w-md rounded-md border border-input bg-background px-3 text-sm"
              value={purgeConfirm}
              onChange={(e) => setPurgeConfirm(e.target.value)}
              placeholder="DELETE_ALL_LEARNING_DATA"
            />
            <Button
              variant="destructive"
              disabled={!!busy || purgeConfirm !== "DELETE_ALL_LEARNING_DATA"}
              onClick={() =>
                runAction("purge", () =>
                  purgeAiLearningSamples({ confirm: "DELETE_ALL_LEARNING_DATA" }).then((r) => {
                    if (r.success) setPurgeConfirm("")
                    return r
                  }),
                )
              }
            >
              <Trash2 className="h-4 w-4 mr-2" />
              {busy === "purge" ? "Purging…" : "Purge all learning data"}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
