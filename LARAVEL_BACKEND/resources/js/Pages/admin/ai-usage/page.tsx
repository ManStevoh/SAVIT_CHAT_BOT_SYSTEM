"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Progress } from "@/components/ui/progress"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Bot, MessageSquare, Zap, DollarSign } from "lucide-react"
import {
  LineChart,
  Line,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts"
// API: GET /api/admin/ai-usage?period=7d — stats and charts (useAdminAIUsage)
import { useAdminAIUsage } from "@/lib/api-hooks"

export default function AdminAIUsagePage() {
  const [period, setPeriod] = useState("7d")
  const { data: aiUsage, error, isLoading } = useAdminAIUsage(period)

  if (isLoading && !aiUsage) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">AI Usage</h1>
          <p className="text-muted-foreground">Monitor AI token usage and costs</p>
        </div>
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          {[1, 2, 3, 4].map((i) => (
            <Card key={i}>
              <CardContent className="p-6">
                <div className="h-20 animate-pulse rounded bg-muted" />
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">AI Usage</h1>
          <p className="text-muted-foreground">Monitor AI token usage and costs</p>
        </div>
        <Card className="border-destructive/50">
          <CardContent className="p-6">
            <p className="text-destructive">Failed to load AI usage. Please try again.</p>
          </CardContent>
        </Card>
      </div>
    )
  }

  const formatTokens = (n: number) => {
    if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
    if (n >= 1_000) return `${(n / 1_000).toFixed(1)}K`
    return String(n)
  }

  const tokenUsageData = aiUsage?.usageByDay?.map((d) => ({ name: d.date, tokens: d.value })) ?? []
  const modelUsageData = aiUsage?.modelUsage?.map((m) => ({
    name: m.model,
    usage: aiUsage.totalTokens ? Math.round((m.tokens / aiUsage.totalTokens) * 100) : 0,
  })) ?? []
  const topConsumers = aiUsage?.usageByCompany?.slice(0, 5).map((c) => ({
    company: c.companyName,
    tokens: formatTokens(c.tokens),
    percentage: aiUsage.totalTokens ? Math.round((c.tokens / aiUsage.totalTokens) * 1000) / 10 : 0,
  })) ?? []

  const stats = [
    { name: "API Requests", value: aiUsage?.totalRequests != null ? formatTokens(aiUsage.totalRequests) : "—", icon: Bot },
    { name: "Tokens Used", value: aiUsage?.totalTokens != null ? formatTokens(aiUsage.totalTokens) : "—", icon: Zap },
    { name: "Est. API Cost", value: aiUsage?.totalCostUsd != null ? `$${aiUsage.totalCostUsd.toFixed(2)}` : "—", icon: DollarSign },
    { name: "Success Rate", value: aiUsage?.successRate != null ? `${aiUsage.successRate}%` : "—", icon: MessageSquare },
  ]

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground">AI Usage</h1>
          <p className="text-muted-foreground">Monitor AI token usage and costs</p>
        </div>
        <Select value={period} onValueChange={setPeriod}>
          <SelectTrigger className="w-40">
            <SelectValue placeholder="Select period" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="24h">Last 24 hours</SelectItem>
            <SelectItem value="7d">Last 7 days</SelectItem>
            <SelectItem value="30d">Last 30 days</SelectItem>
            <SelectItem value="90d">Last 90 days</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat) => (
          <Card key={stat.name}>
            <CardContent className="p-6">
              <div className="flex items-center justify-between mb-4">
                <div>
                  <p className="text-sm text-muted-foreground">{stat.name}</p>
                  <p className="mt-1 text-2xl font-bold text-foreground">{stat.value}</p>
                </div>
                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                  <stat.icon className="h-6 w-6 text-primary" />
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Token Usage</CardTitle>
            <CardDescription>Daily token consumption (API: usageByDay)</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-[300px]">
              {tokenUsageData.length === 0 ? (
                <div className="flex h-full items-center justify-center text-muted-foreground">No data</div>
              ) : (
                <ResponsiveContainer width="100%" height="100%">
                  <LineChart data={tokenUsageData}>
                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                    <XAxis dataKey="name" stroke="hsl(var(--muted-foreground))" fontSize={12} />
                    <YAxis stroke="hsl(var(--muted-foreground))" fontSize={12} tickFormatter={(value) => formatTokens(value)} />
                    <Tooltip contentStyle={{ backgroundColor: "hsl(var(--card))", border: "1px solid hsl(var(--border))", borderRadius: "8px" }} formatter={(value: number) => [`${formatTokens(value)} tokens`, "Tokens"]} />
                    <Line type="monotone" dataKey="tokens" stroke="hsl(var(--primary))" strokeWidth={2} />
                  </LineChart>
                </ResponsiveContainer>
              )}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Model Distribution</CardTitle>
            <CardDescription>Usage by AI model (API: modelUsage)</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-[300px]">
              {modelUsageData.length === 0 ? (
                <div className="flex h-full items-center justify-center text-muted-foreground">No data</div>
              ) : (
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={modelUsageData} layout="vertical">
                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                    <XAxis type="number" stroke="hsl(var(--muted-foreground))" fontSize={12} tickFormatter={(value) => `${value}%`} />
                    <YAxis dataKey="name" type="category" stroke="hsl(var(--muted-foreground))" fontSize={12} width={80} />
                    <Tooltip contentStyle={{ backgroundColor: "hsl(var(--card))", border: "1px solid hsl(var(--border))", borderRadius: "8px" }} formatter={(value: number) => [`${value}%`, "Usage"]} />
                    <Bar dataKey="usage" fill="hsl(var(--primary))" radius={[0, 4, 4, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              )}
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Top Token Consumers</CardTitle>
          <CardDescription>Companies using the most AI tokens (API: usageByCompany)</CardDescription>
        </CardHeader>
        <CardContent>
          {topConsumers.length === 0 ? (
            <div className="py-8 text-center text-muted-foreground">No data</div>
          ) : (
            <div className="space-y-4">
              {topConsumers.map((consumer, i) => (
                <div key={i} className="flex items-center gap-4">
                  <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-sm font-medium text-primary">
                    {i + 1}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between">
                      <span className="font-medium text-foreground">{consumer.company}</span>
                      <span className="text-sm text-muted-foreground">{consumer.tokens} tokens</span>
                    </div>
                    <Progress value={consumer.percentage * 10} className="h-2 mt-2" />
                  </div>
                  <span className="text-sm text-muted-foreground w-12 text-right">{consumer.percentage}%</span>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>API billing split</CardTitle>
          <CardDescription>Platform-billed vs bring-your-own-key (BYOK) usage</CardDescription>
        </CardHeader>
        <CardContent>
          {(aiUsage?.usageByCredentialSource?.length ?? 0) === 0 ? (
            <div className="py-8 text-center text-muted-foreground">No credential-tagged requests in this period</div>
          ) : (
            <div className="space-y-3">
              {aiUsage?.usageByCredentialSource?.map((row) => (
                <div key={row.credentialSource} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border px-4 py-3">
                  <span className="font-medium capitalize text-foreground">{row.credentialSource} keys</span>
                  <span className="text-sm text-muted-foreground">
                    {row.requests.toLocaleString()} req · {formatTokens(row.tokens)} tokens
                    {row.credentialSource === 'platform'
                      ? ` · billed $${row.billedCostUsd.toFixed(2)}`
                      : ` · est. $${row.estimatedCostUsd.toFixed(2)}`}
                  </span>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Bot Reply Routing</CardTitle>
          <CardDescription>How WhatsApp bot replies were produced (no OpenAI cost for FAQ/keyword paths)</CardDescription>
        </CardHeader>
        <CardContent>
          {(aiUsage?.botRepliesBySource?.length ?? 0) === 0 ? (
            <div className="py-8 text-center text-muted-foreground">No tagged bot replies in this period</div>
          ) : (
            <div className="space-y-3">
              {aiUsage?.botRepliesBySource?.map((row) => (
                <div key={row.source} className="flex items-center justify-between rounded-lg border border-border px-4 py-3">
                  <span className="font-medium capitalize text-foreground">{row.source.replace(/_/g, " ")}</span>
                  <span className="text-sm text-muted-foreground">{row.count.toLocaleString()} replies</span>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
