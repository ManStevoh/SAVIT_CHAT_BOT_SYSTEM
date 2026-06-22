"use client"

import { Suspense, useEffect, useState } from "react"
import { useSearchParams } from "next/navigation"
import { GrowthPilotChecklist } from "@/components/growth/GrowthPilotChecklist"
import { GrowthCelebrationBanner } from "@/components/growth/GrowthCelebrationBanner"
import { MetaOAuthChecklist } from "@/components/growth/MetaOAuthChecklist"
import Link from "next/link"
import { mutate } from "swr"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Badge } from "@/components/ui/badge"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  FunnelChart,
  Funnel,
  LabelList,
  Cell,
} from "recharts"
import {
  useGrowthAnalytics,
  useGrowthPosts,
  useGrowthInsights,
  useGrowthSocialAccounts,
  useGrowthCompetitors,
  useGrowthAgents,
  useGrowthOAuthConfig,
  useGrowthAdSpend,
  useGrowthPilotStatus,
  useGrowthMetaPages,
  useGrowthAdAccounts,
  useGrowthPatterns,
  useGrowthContentMix,
  useGrowthDraftScores,
  useGrowthPredictionAccuracy,
  useGrowthPortfolioInsights,
  useGrowthIntegrations,
  useGrowthCrmStatus,
} from "@/lib/api-hooks"
import {
  generateGrowthContent,
  generateSmartGrowthContent,
  generateGrowthVariants,
  getGrowthPostSharePackage,
  uploadGrowthPostImage,
  extractGrowthPatterns,
  applyGrowthPattern,
  executeGrowthMixPlan,
  approveGrowthPost,
  publishGrowthPost,
  getGrowthOAuthAuthorizeUrl,
  selectGrowthMetaPage,
  selectGrowthAdAccount,
  syncGrowthMetaMetrics,
  syncGrowthMetaAds,
  runGrowthCrmAgent,
  addGrowthAdSpend,
  importGrowthAdSpend,
  generateGrowthInsights,
  runGrowthAgentPipeline,
  addGrowthCompetitor,
  exportGrowthAttribution,
  connectGrowthIntegration,
  syncGrowthIntegrations,
} from "@/lib/api-actions"
import { useCompanySettings } from "@/lib/api-hooks"
import { formatCurrencyAmount, normalizeCurrencyCode } from "@/lib/format-currency"
import { toast } from "sonner"
import { Loader2, Sparkles, Link2, Bot, TrendingUp, Target, Copy, ImagePlus, Download, Plug, Users } from "lucide-react"
const FUNNEL_COLORS = ["hsl(var(--chart-1))", "hsl(var(--chart-2))", "hsl(var(--chart-3))", "hsl(var(--chart-4))", "hsl(var(--chart-5))", "hsl(var(--primary))"]

const OAUTH_PLATFORMS = [
  { id: "facebook", label: "Facebook" },
  { id: "instagram", label: "Instagram" },
  { id: "linkedin", label: "LinkedIn" },
  { id: "tiktok", label: "TikTok" },
  { id: "twitter", label: "X (Twitter)" },
] as const

const GROWTH_TABS = new Set(["overview", "content", "platforms", "adspend", "insights", "integrations", "agents"])

const INTEGRATION_LABELS: Record<string, string> = {
  ga4: "Google Analytics 4",
  email: "Email marketing",
  website: "Website",
}

function GrowthPageContent() {
  const searchParams = useSearchParams()
  const tabFromUrl = searchParams.get("tab")
  const [activeTab, setActiveTab] = useState(() =>
    tabFromUrl && GROWTH_TABS.has(tabFromUrl) ? tabFromUrl : "overview"
  )
  const [period, setPeriod] = useState("30d")
  const [generating, setGenerating] = useState(false)
  const [executingMix, setExecutingMix] = useState(false)
  const [connecting, setConnecting] = useState<string | null>(null)
  const [topic, setTopic] = useState("")
  const [audience, setAudience] = useState("")
  const [platform, setPlatform] = useState("facebook")
  const [competitorName, setCompetitorName] = useState("")
  const [competitorPlatform, setCompetitorPlatform] = useState("facebook")
  const [adAmount, setAdAmount] = useState("")
  const [adPlatform, setAdPlatform] = useState("facebook")
  const [adDate, setAdDate] = useState(new Date().toISOString().slice(0, 10))
  const [variants, setVariants] = useState<Array<{ variantIndex: number; angle: string; content: string; predictedScore: number; explanations?: string[] }>>([])
  const [generatingVariants, setGeneratingVariants] = useState(false)
  const [integrationSiteUrl, setIntegrationSiteUrl] = useState("")
  const [integrationMeasurementId, setIntegrationMeasurementId] = useState("")
  const [connectingIntegration, setConnectingIntegration] = useState<string | null>(null)
  const [syncingIntegrations, setSyncingIntegrations] = useState(false)

  const { data: analytics, isLoading } = useGrowthAnalytics(period)
  const { data: posts, mutate: mutatePosts } = useGrowthPosts()
  const { data: insights, mutate: mutateInsights } = useGrowthInsights()
  const { data: accounts, mutate: mutateAccounts } = useGrowthSocialAccounts()
  const { data: oauthConfig } = useGrowthOAuthConfig()
  const { data: pilotStatus } = useGrowthPilotStatus()
  const { data: metaPages, mutate: mutateMetaPages } = useGrowthMetaPages("facebook")
  const { data: adAccounts, mutate: mutateAdAccounts } = useGrowthAdAccounts()
  const { data: adSpend, mutate: mutateAdSpend } = useGrowthAdSpend(period)
  const { data: competitors, mutate: mutateCompetitors } = useGrowthCompetitors()
  const { data: agents, mutate: mutateAgents } = useGrowthAgents()
  const { data: integrationsData, mutate: mutateIntegrations } = useGrowthIntegrations()
  const { data: crmStatus, mutate: mutateCrmStatus } = useGrowthCrmStatus()
  const { data: patternsData, mutate: mutatePatterns } = useGrowthPatterns()
  const { data: contentMixData } = useGrowthContentMix()
  const { data: draftScores, mutate: mutateDraftScores } = useGrowthDraftScores()
  const { data: predictionAccuracy } = useGrowthPredictionAccuracy()
  const { data: portfolioInsights } = useGrowthPortfolioInsights()
  const { data: companySettings } = useCompanySettings()
  const currency = normalizeCurrencyCode(companySettings?.displayCurrency)

  const summary = analytics?.summary
  const intelligence = analytics?.intelligence
  const funnelData = analytics?.funnel?.map((f) => ({ name: f.stage, value: f.value })) ?? []

  useEffect(() => {
    mutateDraftScores()
  }, [posts?.length, mutateDraftScores])

  useEffect(() => {
    if (tabFromUrl && GROWTH_TABS.has(tabFromUrl)) {
      setActiveTab(tabFromUrl)
    }
  }, [tabFromUrl])

  useEffect(() => {
    const oauthStatus = searchParams.get("growth_oauth")
    if (!oauthStatus) return
    const plat = searchParams.get("platform") ?? "platform"
    if (oauthStatus === "success") {
      toast.success(`${plat} connected successfully`)
      mutateAccounts()
      mutateMetaPages()
      mutateAdAccounts()
    } else if (oauthStatus === "pending_pages") {
      toast.info("Select your Facebook Page below")
      mutateAccounts()
      mutateMetaPages()
    } else if (oauthStatus === "error") {
      toast.error(searchParams.get("message") ?? `Failed to connect ${plat}`)
    }
  }, [searchParams, mutateAccounts])

  async function handleGenerate(smart = false) {
    setGenerating(true)
    const payload = {
      count: smart ? 3 : 5,
      platform,
      topic: topic || "our products and services",
      audience: audience || "potential customers",
    }
    const result = smart
      ? await generateSmartGrowthContent(payload)
      : await generateGrowthContent(payload)
    setGenerating(false)
    if (result.success) {
      toast.success(smart ? "Smart posts generated from your winners" : "AI generated draft posts with tracking links")
      mutatePosts()
      mutateDraftScores()
      mutate(["growth-analytics", period])
    } else {
      toast.error(result.message ?? "Generation failed")
    }
  }

  async function handleExtractPatterns() {
    const result = await extractGrowthPatterns(30)
    if (result.success) {
      toast.success("Learning patterns updated from your performance data")
      mutatePatterns()
      mutateInsights()
    } else {
      toast.error(result.message ?? "Extraction failed")
    }
  }

  async function handleExecuteMix() {
    setExecutingMix(true)
    const result = await executeGrowthMixPlan()
    setExecutingMix(false)
    if (result.success) {
      toast.success(`Generated ${result.posts?.length ?? 0} posts from your weekly mix plan`)
      mutatePosts()
      mutateDraftScores()
      mutate(["growth-analytics", period])
    } else {
      toast.error(result.message ?? "Mix plan execution failed")
    }
  }

  async function handleApplyPattern(patternId: string) {
    const result = await applyGrowthPattern(patternId)
    if (result.success) {
      toast.success("Pattern applied — use Smart Generate to create similar content")
      mutatePatterns()
    } else {
      toast.error(result.message ?? "Failed")
    }
  }

  async function handleApprove(postId: string) {
    const result = await approveGrowthPost(postId)
    if (result.success) {
      toast.success("Post approved")
      mutatePosts()
    } else {
      toast.error(result.message ?? "Failed")
    }
  }

  async function handlePublish(postId: string) {
    const result = await publishGrowthPost(postId)
    if (result.success) {
      toast.success("Post published")
      mutatePosts()
      mutate(["growth-analytics", period])
    } else {
      toast.error(result.metaError ?? result.message ?? "Publish failed")
    }
  }

  async function handleCopySharePackage(postId: string) {
    const result = await getGrowthPostSharePackage(postId)
    if (result.success && result.clipboardPackage) {
      await navigator.clipboard.writeText(result.clipboardPackage)
      toast.success("Caption + tracking link copied to clipboard")
    } else {
      toast.error(result.message ?? "Could not build share package")
    }
  }

  async function handleGenerateVariants() {
    setGeneratingVariants(true)
    const result = await generateGrowthVariants({ count: 3, platform, topic: topic || "our products" })
    setGeneratingVariants(false)
    if (result.success && result.variants) {
      setVariants(result.variants as typeof variants)
      toast.success("3 variants ranked — pick your favorites and save")
    } else {
      toast.error(result.message ?? "Variant generation failed")
    }
  }

  async function handleSaveVariants(indexes: number[]) {
    const result = await generateGrowthVariants({
      count: 3,
      platform,
      topic: topic || "our products",
      saveIndexes: indexes,
    })
    if (result.success) {
      toast.success(`Saved ${result.savedPosts?.length ?? 0} variant(s) as drafts`)
      setVariants([])
      mutatePosts()
      mutateDraftScores()
    } else {
      toast.error(result.message ?? "Save failed")
    }
  }

  function goToTab(tab: string) {
    setActiveTab(tab)
    if (typeof window !== "undefined") {
      const url = new URL(window.location.href)
      url.searchParams.set("tab", tab)
      window.history.replaceState({}, "", url.pathname + url.search)
    }
  }

  async function handleOAuthConnect(platformId: string) {
    setConnecting(platformId)
    const result = await getGrowthOAuthAuthorizeUrl(platformId)
    setConnecting(null)
    if (result.success && result.authorizeUrl) {
      window.location.href = result.authorizeUrl
    } else {
      toast.error(result.message ?? "OAuth not configured. Set API keys in backend .env.")
    }
  }

  async function handleAddAdSpend() {
    const amount = parseFloat(adAmount)
    if (!amount || amount <= 0) return
    const result = await addGrowthAdSpend({
      platform: adPlatform,
      amount,
      spentAt: adDate,
    })
    if (result.success) {
      toast.success("Ad spend recorded")
      setAdAmount("")
      mutateAdSpend()
      mutate(["growth-analytics", period])
    } else {
      toast.error(result.message ?? "Failed")
    }
  }

  async function handleImportAdSpend(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (!file) return
    const result = await importGrowthAdSpend(file)
    if (result.success) {
      toast.success(`Imported ${result.created ?? 0} ad spend entries`)
      mutateAdSpend()
      mutate(["growth-analytics", period])
    } else {
      toast.error(result.message ?? "Import failed")
    }
    e.target.value = ""
  }

  async function handleGenerateInsights() {
    const result = await generateGrowthInsights()
    if (result.success) {
      toast.success("New insights generated")
      mutateInsights()
    } else {
      toast.error(result.message ?? "Failed")
    }
  }

  async function handleRunAgents() {
    const result = await runGrowthAgentPipeline({ topic, platform, audience })
    if (result.success) {
      toast.success("Agent pipeline started")
      mutateAgents()
    } else {
      toast.error(result.message ?? "Failed")
    }
  }

  async function handleAddCompetitor() {
    if (!competitorName.trim()) return
    const result = await addGrowthCompetitor({
      platform: competitorPlatform,
      accountName: competitorName.trim(),
    })
    if (result.success) {
      toast.success("Competitor added")
      setCompetitorName("")
      mutateCompetitors()
    } else {
      toast.error(result.message ?? "Failed")
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
            Growth Engine
            {pilotStatus?.isPilot && <Badge variant="secondary">Pilot</Badge>}
            {analytics?.isDemo && <Badge variant="outline">Sample data</Badge>}
          </h1>
          <p className="text-muted-foreground">
            Closed-loop attribution: post → click → WhatsApp → lead → revenue
          </p>
        </div>
        <Select value={period} onValueChange={setPeriod}>
          <SelectTrigger className="w-[140px]">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="7d">Last 7 days</SelectItem>
            <SelectItem value="30d">Last 30 days</SelectItem>
            <SelectItem value="90d">Last 90 days</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center py-20">
          <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        </div>
      ) : (
        <>
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
            <Card>
              <CardHeader className="pb-2">
                <CardDescription>Leads</CardDescription>
                <CardTitle className="text-3xl">{summary?.leads ?? 0}</CardTitle>
              </CardHeader>
            </Card>
            <Card>
              <CardHeader className="pb-2">
                <CardDescription>Attributed revenue</CardDescription>
                <CardTitle className="text-3xl">
                  {formatCurrencyAmount(summary?.revenue ?? 0, currency)}
                </CardTitle>
              </CardHeader>
            </Card>
            <Card>
              <CardHeader className="pb-2">
                <CardDescription>Ad spend</CardDescription>
                <CardTitle className="text-3xl">
                  {formatCurrencyAmount(summary?.adSpend ?? adSpend?.totalSpend ?? 0, currency)}
                </CardTitle>
              </CardHeader>
            </Card>
            <Card>
              <CardHeader className="pb-2">
                <CardDescription>Cost per lead</CardDescription>
                <CardTitle className="text-3xl">
                  {summary?.costPerLead != null
                    ? formatCurrencyAmount(summary.costPerLead, currency)
                    : "—"}
                </CardTitle>
              </CardHeader>
            </Card>
            <Card>
              <CardHeader className="pb-2">
                <CardDescription>CAC</CardDescription>
                <CardTitle className="text-3xl">
                  {summary?.customerAcquisitionCost != null
                    ? formatCurrencyAmount(summary.customerAcquisitionCost, currency)
                    : "—"}
                </CardTitle>
              </CardHeader>
            </Card>
            <Card>
              <CardHeader className="pb-2">
                <CardDescription>ROI</CardDescription>
                <CardTitle className="text-3xl">
                  {summary?.roi != null ? `${summary.roi}%` : "—"}
                </CardTitle>
              </CardHeader>
            </Card>
            <Card>
              <CardHeader className="pb-2">
                <CardDescription>Lead → order</CardDescription>
                <CardTitle className="text-3xl">{summary?.leadToOrderRate ?? 0}%</CardTitle>
              </CardHeader>
            </Card>
          </div>

          <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-4">
            <TabsList>
              <TabsTrigger value="overview">Executive</TabsTrigger>
              <TabsTrigger value="content">Content</TabsTrigger>
              <TabsTrigger value="platforms">Platforms</TabsTrigger>
              <TabsTrigger value="adspend">Ad Spend</TabsTrigger>
              <TabsTrigger value="insights">Intelligence</TabsTrigger>
              <TabsTrigger value="integrations">Integrations</TabsTrigger>
              <TabsTrigger value="agents">AI Agents</TabsTrigger>
            </TabsList>

            <TabsContent value="overview" className="space-y-4">
              {pilotStatus?.onboarding && !pilotStatus.onboarding.isComplete && (
                <GrowthPilotChecklist
                  steps={pilotStatus.onboarding.steps}
                  percentComplete={pilotStatus.onboarding.percentComplete}
                  isComplete={pilotStatus.onboarding.isComplete}
                  onGoToTab={goToTab}
                  demoMode={pilotStatus.demoMode || analytics?.isDemo}
                />
              )}

              <GrowthCelebrationBanner
                show={!!analytics?.celebration?.showHighlight}
                message={analytics?.celebration?.message ?? "Your Growth loop is proven!"}
              />

              {intelligence?.weeklyBrief && (
                <Card className="border-primary/30 bg-primary/5">
                  <CardHeader className="pb-2">
                    <CardTitle className="text-base">{intelligence.weeklyBrief.title}</CardTitle>
                    <CardDescription>Weekly AI brief</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <p className="text-sm whitespace-pre-wrap">{intelligence.weeklyBrief.body}</p>
                  </CardContent>
                </Card>
              )}

              {intelligence && (intelligence.winningTags.length > 0 || intelligence.pendingPatterns > 0) && (
                <div className="flex flex-wrap gap-2 items-center text-sm text-muted-foreground">
                  {intelligence.winningTags.length > 0 && (
                    <span>Winning tags: {intelligence.winningTags.join(", ")}</span>
                  )}
                  {intelligence.pendingPatterns > 0 && (
                    <Badge variant="secondary">{intelligence.pendingPatterns} patterns to apply</Badge>
                  )}
                </div>
              )}

              <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <Target className="h-5 w-5" />
                      Conversion funnel
                    </CardTitle>
                    <CardDescription>Post to revenue attribution chain</CardDescription>
                  </CardHeader>
                  <CardContent className="h-[300px]">
                    <ResponsiveContainer width="100%" height="100%">
                      <FunnelChart>
                        <Tooltip />
                        <Funnel dataKey="value" data={funnelData} isAnimationActive>
                          <LabelList position="right" fill="hsl(var(--foreground))" stroke="none" dataKey="name" />
                          {funnelData.map((_, i) => (
                            <Cell key={i} fill={FUNNEL_COLORS[i % FUNNEL_COLORS.length]} />
                          ))}
                        </Funnel>
                      </FunnelChart>
                    </ResponsiveContainer>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <TrendingUp className="h-5 w-5" />
                      Revenue by platform
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="h-[300px]">
                    <ResponsiveContainer width="100%" height="100%">
                      <BarChart data={analytics?.platformBreakdown ?? []}>
                        <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                        <XAxis dataKey="platform" />
                        <YAxis />
                        <Tooltip formatter={(v: number) => formatCurrencyAmount(v, currency)} />
                        <Bar dataKey="revenue" fill="hsl(var(--primary))" radius={[4, 4, 0, 0]} />
                      </BarChart>
                    </ResponsiveContainer>
                  </CardContent>
                </Card>
              </div>

              <Card>
                <CardHeader>
                  <CardTitle>Top performing content</CardTitle>
                  <CardDescription>Ranked by attributed revenue</CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Post</TableHead>
                        <TableHead>Platform</TableHead>
                        <TableHead className="text-right">Reach</TableHead>
                        <TableHead className="text-right">Clicks</TableHead>
                        <TableHead className="text-right">Leads</TableHead>
                        <TableHead className="text-right">Orders</TableHead>
                        <TableHead className="text-right">Score</TableHead>
                        <TableHead className="text-right">Revenue</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {(analytics?.topPosts ?? []).map((post) => (
                        <TableRow key={post.id}>
                          <TableCell className="font-medium">{post.title}</TableCell>
                          <TableCell>{post.platform}</TableCell>
                          <TableCell className="text-right">{post.reach}</TableCell>
                          <TableCell className="text-right">{post.clicks}</TableCell>
                          <TableCell className="text-right">{post.leads}</TableCell>
                          <TableCell className="text-right">{post.orders}</TableCell>
                          <TableCell className="text-right">
                            {post.performanceScore != null ? Math.round(post.performanceScore) : "—"}
                          </TableCell>
                          <TableCell className="text-right">
                            {formatCurrencyAmount(post.revenue, currency)}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="content" className="space-y-4">
              {analytics?.limits && analytics.limits.aiPostsLimit > 0 && (
                (() => {
                  const used = analytics.limits.aiPostsUsed ?? 0
                  const limit = analytics.limits.aiPostsLimit
                  const pct = (used / limit) * 100
                  if (pct < 80) return null
                  return (
                    <div className={`rounded-lg border px-4 py-3 text-sm ${used >= limit ? 'border-destructive/50 bg-destructive/10' : 'border-amber-500/50 bg-amber-500/10'}`}>
                      {used >= limit
                        ? `AI post limit reached (${used}/${limit}). `
                        : `AI posts at ${used}/${limit} — approaching monthly limit. `}
                      <Link href="/dashboard/subscription#plans" className="underline font-medium">Upgrade</Link>
                    </div>
                  )
                })()
              )}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Sparkles className="h-5 w-5" />
                    AI content generator
                  </CardTitle>
                  <CardDescription>
                    Generates posts with UTM tracking links to WhatsApp ({analytics?.limits.aiPostsUsed ?? 0}/
                    {analytics?.limits.aiPostsLimit ?? 0} AI posts this month)
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                      <label className="text-sm font-medium">Platform</label>
                      <Select value={platform} onValueChange={setPlatform}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="facebook">Facebook</SelectItem>
                          <SelectItem value="instagram">Instagram</SelectItem>
                          <SelectItem value="linkedin">LinkedIn</SelectItem>
                          <SelectItem value="tiktok">TikTok</SelectItem>
                          <SelectItem value="twitter">X (Twitter)</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div>
                      <label className="text-sm font-medium">Topic</label>
                      <Input
                        placeholder="e.g. KES 500 Scratch classes for ages 8-15"
                        value={topic}
                        onChange={(e) => setTopic(e.target.value)}
                      />
                    </div>
                  </div>
                  <div>
                    <label className="text-sm font-medium">Target audience</label>
                    <Textarea
                      placeholder="e.g. Parents with children aged 8-15"
                      value={audience}
                      onChange={(e) => setAudience(e.target.value)}
                      rows={2}
                    />
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Button onClick={() => handleGenerate(false)} disabled={generating}>
                      {generating ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Sparkles className="mr-2 h-4 w-4" />}
                      Generate 5 posts
                    </Button>
                    <Button variant="secondary" onClick={() => handleGenerate(true)} disabled={generating}>
                      <Target className="mr-2 h-4 w-4" />
                      Smart generate (from winners)
                    </Button>
                    <Button variant="outline" onClick={handleExecuteMix} disabled={executingMix}>
                      {executingMix ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <TrendingUp className="mr-2 h-4 w-4" />}
                      Execute mix plan
                    </Button>
                    <Button variant="outline" onClick={handleGenerateVariants} disabled={generatingVariants}>
                      {generatingVariants ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                      A/B variants (ranked)
                    </Button>
                  </div>
                  {variants.length > 0 && (
                    <div className="space-y-3 rounded-lg border p-4">
                      <p className="text-sm font-medium">Pick top variants to save as drafts</p>
                      {variants.map((v, i) => (
                        <div key={v.variantIndex} className="rounded border p-3 text-sm space-y-1">
                          <div className="flex justify-between">
                            <Badge variant="secondary">{v.angle}</Badge>
                            <span className="text-muted-foreground">Score {Math.round(v.predictedScore)}</span>
                          </div>
                          <p className="whitespace-pre-wrap">{v.content}</p>
                          {(v.explanations ?? []).slice(0, 2).map((line, j) => (
                            <p key={j} className="text-xs text-muted-foreground">{line}</p>
                          ))}
                          <Button size="sm" variant="outline" onClick={() => handleSaveVariants([i])}>
                            Save this variant
                          </Button>
                        </div>
                      ))}
                    </div>
                  )}
                  {contentMixData?.plan && (
                    <div className="rounded-lg border bg-muted/40 p-3 text-sm">
                      <p className="font-medium">This week&apos;s recommended mix ({contentMixData.plan.totalPosts} posts on {contentMixData.plan.platform})</p>
                      <p className="text-muted-foreground mt-1">
                        {contentMixData.plan.mix.map((m) => `${m.count}× ${m.tag.replace(/_/g, " ")}`).join(" · ")}
                      </p>
                    </div>
                  )}
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Content queue</CardTitle>
                  <CardDescription>Approve before publishing — human-in-the-loop for compliance</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  {(posts ?? []).length === 0 ? (
                    <p className="text-sm text-muted-foreground">No posts yet. Generate content above.</p>
                  ) : (
                    (posts ?? []).map((post) => (
                      <div key={post.id} className="rounded-lg border p-4 space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                          <Badge variant="outline">{post.platform}</Badge>
                          <Badge variant={post.status === "published" ? "default" : "secondary"}>{post.status}</Badge>
                          {post.aiGenerated && <Badge variant="outline">AI</Badge>}
                          {post.predictedRevenueScore != null && (
                            <Badge variant="secondary">Predicted {Math.round(post.predictedRevenueScore)}/100</Badge>
                          )}
                          {post.performanceScore != null && post.status === "published" && (
                            <Badge>Score {Math.round(post.performanceScore)}</Badge>
                          )}
                          {(post.contentTags ?? []).map((tag) => (
                            <Badge key={tag} variant="outline" className="text-xs">{tag.replace(/_/g, " ")}</Badge>
                          ))}
                        </div>
                        {post.title && <p className="font-medium">{post.title}</p>}
                        <p className="text-sm text-muted-foreground whitespace-pre-wrap">{post.content}</p>
                        {post.publishError && (
                          <p className="text-xs text-destructive">Publish error: {post.publishError}</p>
                        )}
                        {post.trackingUrl && (
                          <p className="text-xs flex items-center gap-1 text-primary">
                            <Link2 className="h-3 w-3" />
                            {post.trackingUrl}
                          </p>
                        )}
                        <div className="flex flex-wrap gap-2 pt-2">
                          {post.trackingUrl && (
                            <Button size="sm" variant="outline" onClick={() => handleCopySharePackage(post.id)}>
                              <Copy className="h-3 w-3 mr-1" />
                              Copy post + link
                            </Button>
                          )}
                          {post.platform === "instagram" && post.status !== "published" && (
                            <label className="inline-flex">
                              <input
                                type="file"
                                accept="image/*"
                                className="hidden"
                                onChange={async (e) => {
                                  const file = e.target.files?.[0]
                                  if (!file) return
                                  const res = await uploadGrowthPostImage(post.id, file)
                                  if (res.success) {
                                    toast.success("Image added")
                                    mutatePosts()
                                  } else toast.error(res.message ?? "Upload failed")
                                }}
                              />
                              <Button size="sm" variant="outline" asChild>
                                <span><ImagePlus className="h-3 w-3 mr-1" />Add image</span>
                              </Button>
                            </label>
                          )}
                          {post.status === "draft" && (
                            <Button size="sm" variant="outline" onClick={() => handleApprove(post.id)}>
                              Approve
                            </Button>
                          )}
                          {(post.status === "draft" || post.status === "scheduled") && post.approvedAt && (
                            <Button size="sm" onClick={() => handlePublish(post.id)}>
                              Publish now
                            </Button>
                          )}
                        </div>
                      </div>
                    ))
                  )}
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="platforms" className="space-y-4">
              <MetaOAuthChecklist />
              {(pilotStatus?.tokenExpiryWarnings ?? []).length > 0 && (
                <div className="rounded-lg border border-amber-500/50 bg-amber-500/10 px-4 py-3 text-sm">
                  {(pilotStatus?.tokenExpiryWarnings ?? []).map((w) => (
                    <p key={w.platform}>
                      {w.accountName} ({w.platform}) token expires soon — reconnect to avoid publish failures.
                    </p>
                  ))}
                </div>
              )}
              {(metaPages?.pages?.length ?? 0) > 0 && (
                <Card>
                  <CardHeader>
                    <CardTitle>Select Facebook Page</CardTitle>
                    <CardDescription>Complete OAuth by choosing which Page to publish from</CardDescription>
                  </CardHeader>
                  <CardContent className="flex flex-wrap gap-2">
                    {metaPages?.pages.map((p) => (
                      <Button
                        key={p.id}
                        variant="outline"
                        onClick={async () => {
                          const r = await selectGrowthMetaPage("facebook", p.id)
                          if (r.success) {
                            toast.success(`Connected to ${p.name}`)
                            mutateAccounts()
                            mutateMetaPages()
                          } else toast.error(r.message ?? "Failed")
                        }}
                      >
                        {p.name}
                      </Button>
                    ))}
                  </CardContent>
                </Card>
              )}
              {(adAccounts?.adAccounts?.length ?? 0) > 0 && (
                <Card>
                  <CardHeader>
                    <CardTitle>Meta Ad Account</CardTitle>
                    <CardDescription>For automatic ad spend sync (replaces most CSV imports)</CardDescription>
                  </CardHeader>
                  <CardContent className="flex flex-wrap gap-2">
                    {adAccounts?.adAccounts.map((a) => (
                      <Button
                        key={a.id}
                        variant={adAccounts.selectedAdAccountId === a.id ? "default" : "outline"}
                        onClick={async () => {
                          const r = await selectGrowthAdAccount("facebook", a.id)
                          if (r.success) {
                            toast.success(`Ad account: ${a.name}`)
                            mutateAdAccounts()
                          } else toast.error(r.message ?? "Failed")
                        }}
                      >
                        {a.name}
                      </Button>
                    ))}
                  </CardContent>
                </Card>
              )}
              <Card>
                <CardHeader>
                  <CardTitle>Sync from Meta</CardTitle>
                  <CardDescription>Pull real reach/engagement and ad spend</CardDescription>
                </CardHeader>
                <CardContent className="flex flex-wrap gap-2">
                  <Button variant="outline" onClick={async () => {
                    const r = await syncGrowthMetaMetrics()
                    toast[r.success ? "success" : "error"](r.message ?? (r.success ? "Metrics sync queued" : "Failed"))
                  }}>Sync post metrics</Button>
                  <Button variant="outline" onClick={async () => {
                    const r = await syncGrowthMetaAds()
                    toast[r.success ? "success" : "error"](r.message ?? (r.success ? "Ad spend sync queued" : "Failed"))
                    mutateAdSpend()
                  }}>Sync Meta ad spend</Button>
                  <Button variant="outline" onClick={async () => {
                    const r = await runGrowthCrmAgent()
                    toast[r.success ? "success" : "error"](r.message ?? (r.success ? "CRM agent queued" : "Failed"))
                  }}>Run CRM follow-ups</Button>
                </CardContent>
              </Card>
              <Card>
                <CardHeader>
                  <CardTitle>Connect via OAuth</CardTitle>
                  <CardDescription>
                    Official platform login — callback: {oauthConfig?.callbackUrl ?? "…"}
                  </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-wrap gap-2">
                  {OAUTH_PLATFORMS.map((p) => {
                    const cfg = oauthConfig?.platforms.find((x) => x.platform === p.id)
                    const connected = (accounts ?? []).some(
                      (a) => a.platform === p.id && a.status === "connected"
                    )
                    return (
                      <Button
                        key={p.id}
                        variant={connected ? "secondary" : "outline"}
                        disabled={connecting === p.id || connected}
                        onClick={() => handleOAuthConnect(p.id)}
                      >
                        {connecting === p.id && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                        {connected ? `${p.label} connected` : `Connect ${p.label}`}
                        {cfg && !cfg.configured && !connected && " (setup required)"}
                      </Button>
                    )
                  })}
                </CardContent>
              </Card>
              <Card>
                <CardHeader>
                  <CardTitle>Connected accounts</CardTitle>
                  <CardDescription>
                    {accounts?.filter((a) => a.status === "connected").length ?? 0} /{" "}
                    {analytics?.limits.platformLimit ?? 1} on your plan
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  {(accounts ?? []).length === 0 ? (
                    <p className="text-sm text-muted-foreground">No platforms connected yet.</p>
                  ) : (
                    (accounts ?? []).map((acc) => (
                      <div key={acc.id} className="flex items-center justify-between rounded-lg border p-3">
                        <div>
                          <p className="font-medium capitalize">{acc.platform}</p>
                          <p className="text-sm text-muted-foreground">{acc.accountName ?? "—"}</p>
                        </div>
                        <Badge variant={acc.status === "connected" ? "default" : "secondary"}>{acc.status}</Badge>
                      </div>
                    ))
                  )}
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="adspend" className="space-y-4">
              <Card>
                <CardHeader>
                  <CardTitle>Record ad spend</CardTitle>
                  <CardDescription>Powers CPL, CAC, and ROI on the executive dashboard</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex flex-wrap gap-2 items-end">
                    <div>
                      <label className="text-sm font-medium">Platform</label>
                      <Select value={adPlatform} onValueChange={setAdPlatform}>
                        <SelectTrigger className="w-[140px]"><SelectValue /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="facebook">Facebook</SelectItem>
                          <SelectItem value="instagram">Instagram</SelectItem>
                          <SelectItem value="linkedin">LinkedIn</SelectItem>
                          <SelectItem value="google">Google</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    <div>
                      <label className="text-sm font-medium">Amount</label>
                      <Input type="number" min="0" step="0.01" value={adAmount} onChange={(e) => setAdAmount(e.target.value)} className="w-32" />
                    </div>
                    <div>
                      <label className="text-sm font-medium">Date</label>
                      <Input type="date" value={adDate} onChange={(e) => setAdDate(e.target.value)} />
                    </div>
                    <Button onClick={handleAddAdSpend}>Add spend</Button>
                  </div>
                  <div>
                    <label className="text-sm font-medium">Import CSV</label>
                    <p className="text-xs text-muted-foreground mb-2">Columns: spent_at, amount, platform, campaign_name, currency</p>
                    <Input type="file" accept=".csv" onChange={handleImportAdSpend} />
                  </div>
                </CardContent>
              </Card>
              <Card>
                <CardHeader>
                  <CardTitle>Spend history</CardTitle>
                  <CardDescription>Total: {formatCurrencyAmount(adSpend?.totalSpend ?? 0, currency)}</CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead>Platform</TableHead>
                        <TableHead>Campaign</TableHead>
                        <TableHead className="text-right">Amount</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {(adSpend?.entries ?? []).map((e) => (
                        <TableRow key={e.id}>
                          <TableCell>{e.spentAt}</TableCell>
                          <TableCell>{e.platform ?? "—"}</TableCell>
                          <TableCell>{e.campaignName ?? "—"}</TableCell>
                          <TableCell className="text-right">
                            {formatCurrencyAmount(e.amount, e.currency || currency)}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="insights" className="space-y-4">
              <div className="flex flex-wrap justify-between items-center gap-2">
                <p className="text-sm text-muted-foreground">
                  {analytics?.contentIntelligence.bestPlatform
                    ? `Best platform: ${analytics.contentIntelligence.bestPlatform}`
                    : "Publish attributed content to unlock intelligence"}
                </p>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={async () => {
                      const res = await exportGrowthAttribution(period)
                      if (res.success) toast.success("Attribution report downloaded")
                      else toast.error(res.message ?? "Export failed")
                    }}
                  >
                    <Download className="h-3 w-3 mr-1" />
                    Export CSV
                  </Button>
                  <Button variant="outline" size="sm" onClick={handleExtractPatterns}>
                    Learn from performance
                  </Button>
                  <Button variant="outline" size="sm" onClick={handleGenerateInsights}>
                    Refresh insights
                  </Button>
                </div>
              </div>

              {predictionAccuracy && (
                <Card>
                  <CardHeader className="pb-2">
                    <CardTitle className="text-base">Prediction accuracy</CardTitle>
                    <CardDescription>{predictionAccuracy.message as string}</CardDescription>
                  </CardHeader>
                  {predictionAccuracy.hasEnoughData && (predictionAccuracy.items as unknown[])?.length > 0 && (
                    <CardContent>
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead>Post</TableHead>
                            <TableHead className="text-right">Predicted</TableHead>
                            <TableHead className="text-right">Actual</TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          {(predictionAccuracy.items as { title: string; predictedRevenue: number; actualRevenue: number }[]).slice(0, 5).map((row, i) => (
                            <TableRow key={i}>
                              <TableCell className="text-sm">{row.title}</TableCell>
                              <TableCell className="text-right">{formatCurrencyAmount(row.predictedRevenue, currency)}</TableCell>
                              <TableCell className="text-right">{formatCurrencyAmount(row.actualRevenue, currency)}</TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </CardContent>
                  )}
                </Card>
              )}

              {portfolioInsights?.benchmark?.message && (
                <Card className="border-primary/20">
                  <CardContent className="py-4 text-sm">
                    <p className="font-medium">Portfolio benchmark</p>
                    <p className="text-muted-foreground">{portfolioInsights.benchmark.message}</p>
                  </CardContent>
                </Card>
              )}

              {(patternsData?.patterns ?? []).length > 0 && (
                <Card>
                  <CardHeader>
                    <CardTitle>Learning patterns</CardTitle>
                    <CardDescription>Auto-detected from your conversion data — apply to steer Smart Generate</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-3">
                    {(patternsData?.patterns ?? []).slice(0, 6).map((p) => (
                      <div key={p.id} className="rounded-lg border p-3 space-y-2">
                        <div className="flex items-center justify-between gap-2">
                          <p className="font-medium text-sm">{p.title}</p>
                          <Badge variant="outline">{p.source}</Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">{p.body}</p>
                        {!p.isApplied && (
                          <Button size="sm" variant="outline" onClick={() => handleApplyPattern(p.id)}>
                            Apply pattern
                          </Button>
                        )}
                      </div>
                    ))}
                  </CardContent>
                </Card>
              )}

              {(draftScores?.drafts ?? []).length > 0 && (
                <Card>
                  <CardHeader>
                    <CardTitle>Pre-publish predictions</CardTitle>
                    <CardDescription>Ranked by predicted revenue impact — publish highest scores first</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Draft</TableHead>
                          <TableHead>Tags</TableHead>
                          <TableHead className="text-right">Score</TableHead>
                          <TableHead className="text-right">Est. revenue</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {(draftScores?.drafts ?? []).map((d) => (
                          <TableRow key={d.postId}>
                            <TableCell className="font-medium">{d.title}</TableCell>
                            <TableCell className="text-xs">{d.tags.join(", ")}</TableCell>
                            <TableCell className="text-right">{Math.round(d.predictedScore)}</TableCell>
                            <TableCell className="text-right">{formatCurrencyAmount(d.estimatedRevenue, currency)}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              )}
              <div className="grid gap-4 md:grid-cols-2">
                {(insights ?? []).map((insight) => (
                  <Card key={insight.id}>
                    <CardHeader className="pb-2">
                      <div className="flex items-center justify-between">
                        <Badge variant="outline">{insight.insightType}</Badge>
                        <span className="text-xs text-muted-foreground">{insight.confidenceScore}% confidence</span>
                      </div>
                      <CardTitle className="text-base">{insight.title}</CardTitle>
                    </CardHeader>
                    <CardContent>
                      <p className="text-sm text-muted-foreground">{insight.body}</p>
                    </CardContent>
                  </Card>
                ))}
              </div>

              <Card>
                <CardHeader>
                  <CardTitle>Competitor intelligence</CardTitle>
                  <CardDescription>Public account monitoring (official APIs only)</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex flex-wrap gap-2">
                    <Select value={competitorPlatform} onValueChange={setCompetitorPlatform}>
                      <SelectTrigger className="w-[140px]"><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="facebook">Facebook</SelectItem>
                        <SelectItem value="instagram">Instagram</SelectItem>
                        <SelectItem value="linkedin">LinkedIn</SelectItem>
                      </SelectContent>
                    </Select>
                    <Input
                      placeholder="Competitor account name"
                      value={competitorName}
                      onChange={(e) => setCompetitorName(e.target.value)}
                      className="max-w-xs"
                    />
                    <Button onClick={handleAddCompetitor}>Add competitor</Button>
                  </div>
                  {(competitors ?? []).map((c) => (
                    <div key={c.id} className="rounded-lg border p-3">
                      <p className="font-medium">{c.accountName}</p>
                      <p className="text-sm text-muted-foreground capitalize">{c.platform}</p>
                    </div>
                  ))}
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="integrations" className="space-y-4">
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Plug className="h-5 w-5" />
                    Growth integrations
                  </CardTitle>
                  <CardDescription>
                    Connect GA4, email, and your website for attribution sync and reach checks
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  {(integrationsData?.integrations ?? []).map((item) => (
                    <div key={item.provider} className="rounded-lg border p-4 space-y-3">
                      <div className="flex items-center justify-between gap-2">
                        <p className="font-medium">{INTEGRATION_LABELS[item.provider] ?? item.provider}</p>
                        <Badge variant={item.configured ? "default" : "secondary"}>
                          {item.status.replace(/_/g, " ")}
                        </Badge>
                      </div>
                      {item.lastSyncedAt && (
                        <p className="text-xs text-muted-foreground">
                          Last synced: {new Date(item.lastSyncedAt).toLocaleString()}
                        </p>
                      )}
                      {item.message && (
                        <p className="text-sm text-destructive">{item.message}</p>
                      )}
                      {item.provider === "website" && (
                        <div className="flex flex-wrap gap-2">
                          <Input
                            placeholder="https://yourstore.com"
                            value={integrationSiteUrl}
                            onChange={(e) => setIntegrationSiteUrl(e.target.value)}
                            className="max-w-sm"
                          />
                          <Button
                            variant="outline"
                            size="sm"
                            disabled={connectingIntegration === item.provider || !integrationSiteUrl.trim()}
                            onClick={async () => {
                              setConnectingIntegration(item.provider)
                              const r = await connectGrowthIntegration({
                                provider: "website",
                                siteUrl: integrationSiteUrl.trim(),
                              })
                              setConnectingIntegration(null)
                              toast[r.success ? "success" : "error"](r.message ?? (r.success ? "Website connected" : "Failed"))
                              mutateIntegrations()
                            }}
                          >
                            {connectingIntegration === item.provider ? (
                              <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                              "Connect website"
                            )}
                          </Button>
                        </div>
                      )}
                      {item.provider === "ga4" && (
                        <div className="flex flex-wrap gap-2">
                          <Input
                            placeholder="G-XXXXXXXXXX"
                            value={integrationMeasurementId}
                            onChange={(e) => setIntegrationMeasurementId(e.target.value)}
                            className="max-w-xs"
                          />
                          <Button
                            variant="outline"
                            size="sm"
                            disabled={connectingIntegration === item.provider}
                            onClick={async () => {
                              setConnectingIntegration(item.provider)
                              const r = await connectGrowthIntegration({
                                provider: "ga4",
                                measurementId: integrationMeasurementId.trim() || undefined,
                              })
                              setConnectingIntegration(null)
                              toast[r.success ? "success" : "error"](r.message ?? (r.success ? "GA4 connected" : "Failed"))
                              mutateIntegrations()
                            }}
                          >
                            {connectingIntegration === item.provider ? (
                              <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                              "Connect GA4"
                            )}
                          </Button>
                        </div>
                      )}
                      {item.provider === "email" && (
                        <Button
                          variant="outline"
                          size="sm"
                          disabled={connectingIntegration === item.provider}
                          onClick={async () => {
                            setConnectingIntegration(item.provider)
                            const r = await connectGrowthIntegration({ provider: "email" })
                            setConnectingIntegration(null)
                            toast[r.success ? "success" : "error"](r.message ?? (r.success ? "Email connected" : "Failed"))
                            mutateIntegrations()
                          }}
                        >
                          {connectingIntegration === item.provider ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                          ) : (
                            "Connect email"
                          )}
                        </Button>
                      )}
                    </div>
                  ))}
                  <Button
                    variant="secondary"
                    disabled={syncingIntegrations}
                    onClick={async () => {
                      setSyncingIntegrations(true)
                      const r = await syncGrowthIntegrations()
                      setSyncingIntegrations(false)
                      toast[r.success ? "success" : "error"](r.message ?? (r.success ? "Sync complete" : "Sync failed"))
                      mutateIntegrations()
                    }}
                  >
                    {syncingIntegrations ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                    Sync all integrations
                  </Button>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="agents" className="space-y-4">
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Users className="h-5 w-5" />
                    CRM eligible leads
                  </CardTitle>
                  <CardDescription>
                    Attributed chats quiet for {crmStatus?.hoursQuiet ?? 24}h+ or unpaid orders after{" "}
                    {crmStatus?.paymentRecoveryHours ?? 48}h (max {crmStatus?.maxFollowUps ?? 2} follow-ups per chat)
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  {!crmStatus?.whatsAppActive && (
                    <p className="text-sm text-muted-foreground">
                      Connect an active WhatsApp account to send CRM follow-ups.
                    </p>
                  )}
                  <div className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-lg border p-3">
                      <p className="text-sm text-muted-foreground">Cold leads</p>
                      <p className="text-2xl font-bold">{crmStatus?.coldLeads ?? 0}</p>
                    </div>
                    <div className="rounded-lg border p-3">
                      <p className="text-sm text-muted-foreground">Payment recovery</p>
                      <p className="text-2xl font-bold">{crmStatus?.paymentRecovery ?? 0}</p>
                    </div>
                    <div className="rounded-lg border p-3">
                      <p className="text-sm text-muted-foreground">Total eligible</p>
                      <p className="text-2xl font-bold">{crmStatus?.totalEligible ?? 0}</p>
                    </div>
                  </div>
                  <Button
                    variant="outline"
                    onClick={async () => {
                      const r = await runGrowthCrmAgent()
                      toast[r.success ? "success" : "error"](r.message ?? (r.success ? "CRM agent queued" : "Failed"))
                      mutateCrmStatus()
                      mutateAgents()
                    }}
                  >
                    Run CRM follow-ups now
                  </Button>
                </CardContent>
              </Card>
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Bot className="h-5 w-5" />
                    Agent pipeline
                  </CardTitle>
                  <CardDescription>
                    Research → Strategy → Smart content → Analytics (queued jobs)
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <Button onClick={handleRunAgents}>Run full agent pipeline</Button>
                  <div className="mt-4 space-y-2">
                    {(agents ?? []).slice(0, 10).map((run) => (
                      <div key={run.id} className="flex items-center justify-between rounded border p-2 text-sm">
                        <span className="capitalize">{run.agentType}</span>
                        <Badge variant={run.status === "completed" ? "default" : "secondary"}>{run.status}</Badge>
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </>
      )}
    </div>
  )
}

export default function GrowthPage() {
  return (
    <Suspense fallback={
      <div className="flex items-center justify-center py-20">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    }>
      <GrowthPageContent />
    </Suspense>
  )
}
