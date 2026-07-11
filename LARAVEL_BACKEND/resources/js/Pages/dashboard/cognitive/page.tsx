"use client"

import { useState } from "react"
import Link from "next/link"
import {
  BrainCircuit,
  Lightbulb,
  Network,
  Sparkles,
  Target,
  Users,
} from "lucide-react"
import { PageHeader } from "@/components/shared/page-header"
import { StatsCard, StatsGrid } from "@/components/shared/stats-card"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Textarea } from "@/components/ui/textarea"
import { useCognitiveDashboard, useInvestigationCases } from "@/lib/api-hooks"
import { reasonIntelligence, type IntelligenceReasoningResult } from "@/lib/api-actions"

const EXAMPLE_GOALS = [
  "Why are sales down this month?",
  "Should I spend KSh 300,000 on Facebook ads or hire another salesperson?",
  "Should we run a 10% discount this week?",
]

export default function CognitivePage() {
  const { data: cognitive, isLoading } = useCognitiveDashboard()
  const { data: caseData, isLoading: casesLoading } = useInvestigationCases()
  const [goal, setGoal] = useState("")
  const [reasoning, setReasoning] = useState<IntelligenceReasoningResult | null>(null)
  const [reasoningBusy, setReasoningBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleReason = async () => {
    if (!goal.trim()) return
    setReasoningBusy(true)
    setError(null)
    const result = await reasonIntelligence({
      goal: goal.trim(),
      period: "30d",
      simulate: true,
      include_plan: true,
    })
    setReasoningBusy(false)
    if (!result.success || !result.reasoning) {
      setError(result.message ?? "Reasoning failed")
      return
    }
    setReasoning(result.reasoning)
  }

  const causal = cognitive?.causalAnalysis
  const counts = cognitive?.counts

  return (
    <div className="space-y-6">
      <PageHeader
        title="Cognitive AI"
        description="Decision intelligence — perceive, debate, reason, simulate, and recommend. Pair with Executive AI for approvals."
        icon={BrainCircuit}
      />

      <StatsGrid>
        <StatsCard
          title="Revenue trend (14d)"
          value={isLoading ? "…" : (causal?.change ?? "—")}
          description={causal?.metric ?? "causal analysis"}
          icon={Target}
        />
        <StatsCard
          title="Strategic memories"
          value={isLoading ? "…" : String(counts?.strategic_memories ?? 0)}
          description="Learned tactics with outcomes"
          icon={Sparkles}
        />
        <StatsCard
          title="Executive plans"
          value={isLoading ? "…" : String(counts?.executive_plans ?? 0)}
          description="Active goal breakdowns"
          icon={Network}
        />
        <StatsCard
          title="Digital workforce"
          value={isLoading ? "…" : String(cognitive?.workforce?.length ?? 0)}
          description="Director AIs advising the Chief Agent"
          icon={Users}
        />
      </StatsGrid>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Lightbulb className="h-4 w-4" />
              Ask the business (Intelligence API)
            </CardTitle>
            <CardDescription>
              POST /intelligence/reason — goal, evidence, hypotheses, scenarios, and actions in one response.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <Textarea
              rows={4}
              placeholder="e.g. Should I hire another salesperson or spend on ads?"
              value={goal}
              onChange={(e) => setGoal(e.target.value)}
            />
            <div className="flex flex-wrap gap-2">
              {EXAMPLE_GOALS.map((g) => (
                <Button key={g} type="button" variant="outline" size="sm" onClick={() => setGoal(g)}>
                  {g.length > 42 ? g.slice(0, 42) + "…" : g}
                </Button>
              ))}
            </div>
            <Button onClick={handleReason} disabled={reasoningBusy || !goal.trim()}>
              {reasoningBusy ? "Reasoning…" : "Reason"}
            </Button>
            {error && <p className="text-sm text-destructive">{error}</p>}
            {reasoning && (
              <div className="space-y-3 rounded-lg border bg-muted/30 p-4 text-sm">
                <p className="font-medium">{reasoning.executive_summary}</p>
                <p className="text-muted-foreground">
                  Confidence: {Math.round(reasoning.confidence * 100)}%
                </p>
                {reasoning.hypotheses?.length > 0 && (
                  <div>
                    <p className="text-xs font-medium uppercase text-muted-foreground mb-1">Hypotheses</p>
                    <ul className="list-disc pl-5 space-y-1">
                      {reasoning.hypotheses.slice(0, 5).map((h, i) => (
                        <li key={i}>{h.hypothesis}</li>
                      ))}
                    </ul>
                  </div>
                )}
                {reasoning.recommended_actions?.length > 0 && (
                  <div>
                    <p className="text-xs font-medium uppercase text-muted-foreground mb-1">Recommended actions</p>
                    <ul className="space-y-1">
                      {reasoning.recommended_actions.slice(0, 5).map((a, i) => (
                        <li key={i} className="flex items-start gap-2">
                          <span>{a.action}</span>
                          {a.requires_approval && (
                            <Badge variant="outline" className="shrink-0 text-[10px]">
                              approval
                            </Badge>
                          )}
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
                {reasoning.probability_scores && (
                  <p className="text-xs text-muted-foreground">
                    Probabilities — buy {(reasoning.probability_scores.buy * 100).toFixed(0)}%,
                    churn {(reasoning.probability_scores.churn * 100).toFixed(0)}%,
                    refund {(reasoning.probability_scores.refund * 100).toFixed(0)}%
                  </p>
                )}
                {reasoning.case_id && (
                  <p className="text-xs text-muted-foreground">
                    Case #{reasoning.case_id} opened for outcome tracking.
                  </p>
                )}
                {reasoning.simulation?.recommendation && (
                  <p className="text-xs text-muted-foreground">
                    Simulation: {reasoning.simulation.recommendation}
                  </p>
                )}
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Architecture & workforce</CardTitle>
            <CardDescription>{cognitive?.architecture ?? "Loading…"}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {isLoading && <p className="text-sm text-muted-foreground">Loading…</p>}
            {cognitive?.workforce?.map((w) => (
              <div key={w.id} className="rounded-lg border p-3 text-sm">
                <p className="font-medium">{w.title}</p>
                <p className="text-muted-foreground">{w.objective}</p>
                <p className="text-xs text-muted-foreground mt-1">Reports: {w.reports}</p>
              </div>
            ))}
            {causal?.likely_causes && causal.likely_causes.length > 0 && (
              <div className="pt-2">
                <p className="text-xs font-medium uppercase text-muted-foreground mb-2">Likely causes (sales)</p>
                {causal.likely_causes.map((c, i) => (
                  <div key={i} className="flex justify-between text-sm py-1">
                    <span>{c.cause}</span>
                    <Badge variant="secondary">{c.likelihood}</Badge>
                  </div>
                ))}
              </div>
            )}
            <p className="text-xs text-muted-foreground pt-2">
              Morning decisions and approvals live in{" "}
              <Link href="/dashboard/executive" className="text-primary underline">
                Executive AI
              </Link>
              .
            </p>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Investigation cases</CardTitle>
          <CardDescription>
            Multi-step case files from Intelligence API — track evidence, actions, and outcomes.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {casesLoading && <p className="text-sm text-muted-foreground">Loading cases…</p>}
          {!casesLoading && (caseData?.cases?.length ?? 0) === 0 && (
            <p className="text-sm text-muted-foreground">No cases yet. Ask the business above to open one.</p>
          )}
          {caseData?.cases?.slice(0, 8).map((c) => (
            <div key={c.id} className="rounded-lg border p-3 text-sm">
              <div className="flex items-center justify-between gap-2">
                <p className="font-medium line-clamp-2">{c.goal}</p>
                <Badge variant={c.status === "closed" ? "secondary" : "default"}>{c.status}</Badge>
              </div>
              <p className="text-xs text-muted-foreground mt-1">
                Step {c.current_step}/4 · {new Date(c.created_at).toLocaleDateString()}
              </p>
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  )
}
