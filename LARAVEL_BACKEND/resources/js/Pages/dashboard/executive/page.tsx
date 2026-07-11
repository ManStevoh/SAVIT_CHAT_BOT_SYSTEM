"use client"

import { useState } from "react"
import { mutate } from "swr"
import {
  Brain,
  CheckCircle2,
  XCircle,
  Sparkles,
  ShieldAlert,
  TrendingUp,
  FlaskConical,
  Sun,
} from "lucide-react"
import { PageHeader } from "@/components/shared/page-header"
import { StatsCard, StatsGrid } from "@/components/shared/stats-card"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import {
  useCommerceBrief,
  useCommerceExperiments,
  useExecutiveApprovals,
  useExecutiveDashboard,
  useExecutiveOpportunities,
} from "@/lib/api-hooks"
import {
  approveExecutiveAction,
  createCommerceExperiment,
  evaluateCommerceExperiment,
  rejectExecutiveAction,
} from "@/lib/api-actions"

function priorityVariant(priority: string) {
  if (priority === "high") return "destructive" as const
  if (priority === "medium") return "default" as const
  return "secondary" as const
}

export default function ExecutivePage() {
  const { data: dashboard, isLoading: dashLoading } = useExecutiveDashboard()
  const { data: briefData, isLoading: briefLoading } = useCommerceBrief()
  const { data: approvalsData, isLoading: approvalsLoading } = useExecutiveApprovals()
  const { data: opportunitiesData, isLoading: oppLoading } = useExecutiveOpportunities()
  const { data: experimentsData, isLoading: expLoading } = useCommerceExperiments()

  const [actingId, setActingId] = useState<number | null>(null)
  const [expName, setExpName] = useState("")
  const [variantA, setVariantA] = useState("")
  const [variantB, setVariantB] = useState("")
  const [expSaving, setExpSaving] = useState(false)

  const refreshExecutive = () => {
    mutate("executive-dashboard")
    mutate("executive-approvals")
    mutate("executive-opportunities")
    mutate("commerce-brief")
    mutate("commerce-experiments")
  }

  const handleApprove = async (id: number) => {
    setActingId(id)
    await approveExecutiveAction(id)
    refreshExecutive()
    setActingId(null)
  }

  const handleReject = async (id: number) => {
    setActingId(id)
    await rejectExecutiveAction(id)
    refreshExecutive()
    setActingId(null)
  }

  const handleCreateExperiment = async () => {
    if (!expName.trim() || !variantA.trim() || !variantB.trim()) return
    setExpSaving(true)
    await createCommerceExperiment({
      name: expName.trim(),
      variant_a_message: variantA.trim(),
      variant_b_message: variantB.trim(),
    })
    setExpName("")
    setVariantA("")
    setVariantB("")
    refreshExecutive()
    setExpSaving(false)
  }

  const brief = briefData?.brief
  const approvals = approvalsData?.approvals ?? []
  const opportunities = opportunitiesData?.opportunities ?? []
  const experiments = experimentsData?.experiments ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Executive AI"
        description="Morning decisions, owner approvals, opportunities, and promotion experiments."
        icon={Brain}
      />

      <StatsGrid>
        <StatsCard
          title="Health score"
          value={dashLoading ? "…" : `${dashboard?.healthScore?.overall ?? "—"}`}
          description={dashboard?.healthScore?.summary ?? "Business health snapshot"}
          icon={TrendingUp}
        />
        <StatsCard
          title="Pending approvals"
          value={dashLoading ? "…" : String(dashboard?.pendingApprovals ?? 0)}
          description="High-risk actions awaiting owner sign-off"
          icon={ShieldAlert}
        />
        <StatsCard
          title="Open opportunities"
          value={dashLoading ? "…" : String(dashboard?.openOpportunities ?? 0)}
          description="Detected revenue and retention plays"
          icon={Sparkles}
        />
        <StatsCard
          title="Top decisions"
          value={dashLoading ? "…" : String(dashboard?.topDecisions?.length ?? 0)}
          description="CEO AI recommendations for today"
          icon={Sun}
        />
      </StatsGrid>

      <Tabs defaultValue="brief" className="space-y-4">
        <TabsList>
          <TabsTrigger value="brief">Morning brief</TabsTrigger>
          <TabsTrigger value="approvals">
            Approvals
            {approvals.length > 0 && (
              <Badge variant="secondary" className="ml-2">
                {approvals.length}
              </Badge>
            )}
          </TabsTrigger>
          <TabsTrigger value="opportunities">Opportunities</TabsTrigger>
          <TabsTrigger value="experiments">A/B experiments</TabsTrigger>
        </TabsList>

        <TabsContent value="brief">
          <Card>
            <CardHeader>
              <CardTitle>Morning commerce brief</CardTitle>
              <CardDescription>
                {brief?.date ? `Generated for ${brief.date}` : "Daily executive summary"}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {briefLoading && <p className="text-sm text-muted-foreground">Loading brief…</p>}
              {!briefLoading && !brief && (
                <p className="text-sm text-muted-foreground">
                  No brief yet today. The daily job runs at 07:00 in your company timezone.
                </p>
              )}
              {brief && (
                <>
                  <p className="text-sm leading-relaxed">{brief.summary}</p>
                  {brief.recommendations?.length > 0 && (
                    <div>
                      <p className="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Recommendations
                      </p>
                      <ul className="list-disc space-y-1 pl-5 text-sm">
                        {brief.recommendations.map((rec, i) => (
                          <li key={i}>{rec}</li>
                        ))}
                      </ul>
                    </div>
                  )}
                  {brief.executiveDecisions?.length > 0 && (
                    <div>
                      <p className="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Executive decisions
                      </p>
                      <ul className="space-y-2">
                        {brief.executiveDecisions.map((d, i) => (
                          <li
                            key={i}
                            className="rounded-lg border bg-muted/30 px-3 py-2 text-sm"
                          >
                            {d.decision}
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}
                </>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="approvals">
          <Card>
            <CardHeader>
              <CardTitle>Owner approvals</CardTitle>
              <CardDescription>
                Campaign sends and refunds execute only after you approve here or via WhatsApp voice command.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {approvalsLoading && <p className="text-sm text-muted-foreground">Loading…</p>}
              {!approvalsLoading && approvals.length === 0 && (
                <p className="text-sm text-muted-foreground">No pending approvals.</p>
              )}
              {approvals.map((item) => (
                <div
                  key={item.id}
                  className="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-medium">{item.action_type}</span>
                      <Badge variant="outline">{item.risk_level}</Badge>
                      <span className="text-xs text-muted-foreground">#{item.id}</span>
                    </div>
                    {item.reasoning && (
                      <p className="text-sm text-muted-foreground">{item.reasoning}</p>
                    )}
                  </div>
                  <div className="flex shrink-0 gap-2">
                    <Button
                      size="sm"
                      onClick={() => handleApprove(item.id)}
                      disabled={actingId === item.id}
                    >
                      <CheckCircle2 className="mr-1 h-4 w-4" />
                      Approve
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => handleReject(item.id)}
                      disabled={actingId === item.id}
                    >
                      <XCircle className="mr-1 h-4 w-4" />
                      Reject
                    </Button>
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="opportunities">
          <Card>
            <CardHeader>
              <CardTitle>Business opportunities</CardTitle>
              <CardDescription>AI-detected plays from your commerce world model.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {oppLoading && <p className="text-sm text-muted-foreground">Loading…</p>}
              {!oppLoading && opportunities.length === 0 && (
                <p className="text-sm text-muted-foreground">No open opportunities right now.</p>
              )}
              {opportunities.map((opp) => (
                <div key={opp.id} className="rounded-lg border p-4">
                  <div className="mb-1 flex flex-wrap items-center gap-2">
                    <span className="font-medium">{opp.title}</span>
                    <Badge variant={priorityVariant(opp.priority)}>{opp.priority}</Badge>
                    <Badge variant="outline">{opp.opportunity_type}</Badge>
                  </div>
                  <p className="text-sm text-muted-foreground">{opp.description}</p>
                </div>
              ))}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="experiments">
          <div className="grid gap-4 lg:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <FlaskConical className="h-4 w-4" />
                  New promotion A/B test
                </CardTitle>
                <CardDescription>
                  Variants are used on abandoned-cart proactive messages; winner is picked by conversion rate.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                <Input
                  placeholder="Experiment name"
                  value={expName}
                  onChange={(e) => setExpName(e.target.value)}
                />
                <Textarea
                  placeholder="Variant A message"
                  value={variantA}
                  onChange={(e) => setVariantA(e.target.value)}
                  rows={3}
                />
                <Textarea
                  placeholder="Variant B message"
                  value={variantB}
                  onChange={(e) => setVariantB(e.target.value)}
                  rows={3}
                />
                <Button onClick={handleCreateExperiment} disabled={expSaving}>
                  Start experiment
                </Button>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Running experiments</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                {expLoading && <p className="text-sm text-muted-foreground">Loading…</p>}
                {!expLoading && experiments.length === 0 && (
                  <p className="text-sm text-muted-foreground">No experiments yet.</p>
                )}
                {experiments.map((exp) => (
                  <div key={exp.id} className="rounded-lg border p-4">
                    <div className="mb-2 flex flex-wrap items-center gap-2">
                      <span className="font-medium">{exp.name}</span>
                      <Badge variant={exp.status === "running" ? "default" : "secondary"}>
                        {exp.status}
                      </Badge>
                    </div>
                    <div className="space-y-2 text-sm">
                      {exp.variants?.map((v) => {
                        const rate =
                          v.assignments_count > 0
                            ? ((v.conversions_count / v.assignments_count) * 100).toFixed(1)
                            : "0.0"
                        return (
                          <div key={v.id} className="rounded bg-muted/40 px-2 py-1">
                            <span className="font-medium">{v.label}</span>
                            {" — "}
                            {v.assignments_count} sent, {v.conversions_count} converted ({rate}%)
                          </div>
                        )
                      })}
                    </div>
                    {exp.status === "running" && (
                      <Button
                        size="sm"
                        variant="outline"
                        className="mt-3"
                        onClick={async () => {
                          await evaluateCommerceExperiment(exp.id)
                          refreshExecutive()
                        }}
                      >
                        Evaluate winner
                      </Button>
                    )}
                  </div>
                ))}
              </CardContent>
            </Card>
          </div>
        </TabsContent>
      </Tabs>
    </div>
  )
}
