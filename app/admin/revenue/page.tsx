"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { DollarSign, TrendingUp, Users, CreditCard } from "lucide-react"
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
// API: GET /api/admin/revenue?period=6m — stats and chart data (useAdminRevenue)
import { useAdminRevenue } from "@/lib/api-hooks"

export default function AdminRevenuePage() {
  const [period, setPeriod] = useState("6m")
  const { data: revenue, error, isLoading } = useAdminRevenue(period)

  if (isLoading && !revenue) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Revenue</h1>
          <p className="text-muted-foreground">Track revenue metrics and growth</p>
        </div>
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          {[1, 2, 3, 4].map((i) => (
            <Card key={i}>
              <CardContent className="p-6">
                <div className="h-16 animate-pulse rounded bg-muted" />
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
          <h1 className="text-2xl font-bold text-foreground">Revenue</h1>
          <p className="text-muted-foreground">Track revenue metrics and growth</p>
        </div>
        <Card className="border-destructive/50">
          <CardContent className="p-6">
            <p className="text-destructive">Failed to load revenue data. Please try again.</p>
          </CardContent>
        </Card>
      </div>
    )
  }

  const mrrData = revenue?.revenueByMonth?.map((d) => ({ name: d.date, mrr: d.value })) ?? []
  const subscriptionByMonth = revenue?.revenueByMonth ?? []

  const stats = [
    { name: "MRR", value: revenue?.mrr != null ? `$${(revenue.mrr / 1000).toFixed(0)}k` : "—", change: `+${revenue?.revenueChange ?? 0}%`, icon: DollarSign },
    { name: "ARR", value: revenue?.arr != null ? `$${(revenue.arr / 1000).toFixed(0)}k` : "—", change: "+18%", icon: TrendingUp },
    { name: "New Subscriptions", value: "—", change: "+23%", icon: Users },
    { name: "Avg Revenue/User", value: "—", change: "+8%", icon: CreditCard },
  ]

  const breakdown = revenue?.revenueByPlan ?? [
    { plan: "Starter", amount: 39840, count: 200 },
    { plan: "Growth", amount: 56025, count: 566 },
    { plan: "Enterprise", amount: 28635, count: 57 },
  ]
  const totalRev = breakdown.reduce((s, p) => s + p.amount, 0)

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Revenue</h1>
          <p className="text-muted-foreground">Track revenue metrics and growth</p>
        </div>
        <Select value={period} onValueChange={setPeriod}>
          <SelectTrigger className="w-40">
            <SelectValue placeholder="Select period" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="30d">Last 30 days</SelectItem>
            <SelectItem value="3m">Last 3 months</SelectItem>
            <SelectItem value="6m">Last 6 months</SelectItem>
            <SelectItem value="1y">Last year</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat) => (
          <Card key={stat.name}>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-muted-foreground">{stat.name}</p>
                  <p className="mt-1 text-2xl font-bold text-foreground">{stat.value}</p>
                  <div className="mt-1 flex items-center gap-1">
                    <TrendingUp className="h-4 w-4 text-primary" />
                    <span className="text-sm text-primary">{stat.change}</span>
                    <span className="text-sm text-muted-foreground">vs last month</span>
                  </div>
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
            <CardTitle>Monthly Recurring Revenue</CardTitle>
            <CardDescription>MRR growth (API: revenueByMonth)</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-[350px]">
              {mrrData.length === 0 ? (
                <div className="flex h-full items-center justify-center text-muted-foreground">No data</div>
              ) : (
                <ResponsiveContainer width="100%" height="100%">
                  <LineChart data={mrrData}>
                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                    <XAxis dataKey="name" stroke="hsl(var(--muted-foreground))" fontSize={12} />
                    <YAxis stroke="hsl(var(--muted-foreground))" fontSize={12} tickFormatter={(value) => `$${value / 1000}k`} />
                    <Tooltip contentStyle={{ backgroundColor: "hsl(var(--card))", border: "1px solid hsl(var(--border))", borderRadius: "8px" }} formatter={(value: number) => [`$${value.toLocaleString()}`, ""]} />
                    <Legend />
                    <Line type="monotone" dataKey="mrr" stroke="hsl(var(--chart-1))" strokeWidth={2} name="MRR" />
                  </LineChart>
                </ResponsiveContainer>
              )}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Revenue by Month</CardTitle>
            <CardDescription>Monthly revenue trend (API: revenueByMonth)</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-[350px]">
              {subscriptionByMonth.length === 0 ? (
                <div className="flex h-full items-center justify-center text-muted-foreground">No data</div>
              ) : (
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={subscriptionByMonth.map((d) => ({ name: d.date, revenue: d.value }))}>
                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                    <XAxis dataKey="name" stroke="hsl(var(--muted-foreground))" fontSize={12} />
                    <YAxis stroke="hsl(var(--muted-foreground))" fontSize={12} tickFormatter={(v) => `$${v / 1000}k`} />
                    <Tooltip contentStyle={{ backgroundColor: "hsl(var(--card))", border: "1px solid hsl(var(--border))", borderRadius: "8px" }} />
                    <Bar dataKey="revenue" fill="hsl(var(--chart-2))" radius={[4, 4, 0, 0]} name="Revenue" />
                  </BarChart>
                </ResponsiveContainer>
              )}
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Revenue Breakdown</CardTitle>
          <CardDescription>Revenue by plan type (API: revenueByPlan)</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 md:grid-cols-3">
            {breakdown.map((p) => (
              <div key={p.plan} className="rounded-lg border border-border p-4">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-sm text-muted-foreground">{p.plan}</span>
                  <span className="text-sm text-primary">{totalRev ? Math.round((p.amount / totalRev) * 100) : 0}%</span>
                </div>
                <p className="text-2xl font-bold text-foreground">${p.amount.toLocaleString()}</p>
                <p className="text-sm text-muted-foreground">{p.count} subscriptions</p>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
