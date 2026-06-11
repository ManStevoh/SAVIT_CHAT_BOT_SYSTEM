"use client"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Building2, Users, MessageSquare, DollarSign, TrendingUp } from "lucide-react"
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
// API: GET /api/admin/overview — stats (useAdminOverview)
// API: GET /api/admin/companies — recent companies (useAdminCompanies, slice for "recent")
// Charts: GET /api/admin/overview returns companyGrowthData, messageVolumeData
import { useAdminOverview, useAdminCompanies, useAdminSystemHealth } from "@/lib/api-hooks"
import { Badge } from "@/components/ui/badge"
import { AlertTriangle } from "lucide-react"

export default function AdminOverviewPage() {
  const { data: overview, error: overviewError, isLoading: overviewLoading } = useAdminOverview()
  const { data: companies, isLoading: companiesLoading } = useAdminCompanies({})
  const { data: systemHealth } = useAdminSystemHealth()

  const recentCompanies = companies?.slice(0, 4) ?? []
  const monthlyRevenue = overview?.monthlyRevenue ?? overview?.totalRevenue ?? 0
  const stats = overview
    ? [
        { name: "Total Companies", value: overview.totalCompanies.toLocaleString(), change: `+${overview.companiesChange}%`, icon: Building2 },
        { name: "Active Users", value: overview.totalUsers.toLocaleString(), change: `${overview.usersChange != null ? (overview.usersChange >= 0 ? '+' : '') + overview.usersChange : 0}%`, icon: Users },
        { name: "Messages Processed", value: (overview.totalMessages / 1e6).toFixed(1) + "M", change: `+${overview.messagesChange}%`, icon: MessageSquare },
        { name: "Monthly Revenue", value: `$${(monthlyRevenue / 1000).toFixed(0)}k`, change: `+${overview.revenueChange}%`, icon: DollarSign },
      ]
    : [
        { name: "Total Companies", value: "—", change: "—", icon: Building2 },
        { name: "Active Users", value: "—", change: "—", icon: Users },
        { name: "Messages Processed", value: "—", change: "—", icon: MessageSquare },
        { name: "Monthly Revenue", value: "—", change: "—", icon: DollarSign },
      ]

  const queueAlerts = systemHealth?.alerts ?? []

  if (overviewLoading && !overview) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Platform Overview</h1>
          <p className="text-muted-foreground">Monitor your platform performance and metrics</p>
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

  if (overviewError) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Platform Overview</h1>
          <p className="text-muted-foreground">Monitor your platform performance and metrics</p>
        </div>
        <Card className="border-destructive/50">
          <CardContent className="p-6">
            <p className="text-destructive">Failed to load overview. Please try again.</p>
          </CardContent>
        </Card>
      </div>
    )
  }

  if (!overview) {
    return null
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Platform Overview</h1>
        <p className="text-muted-foreground">Monitor your platform performance and metrics</p>
      </div>

      {queueAlerts.length > 0 && (
        <Card className="border-amber-500/50 bg-amber-500/5">
          <CardHeader className="pb-2">
            <CardTitle className="text-base flex items-center gap-2">
              <AlertTriangle className="h-4 w-4 text-amber-600" />
              System alerts
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            {queueAlerts.map((alert) => (
              <p key={alert} className="text-muted-foreground">{alert}</p>
            ))}
            <div className="flex gap-2 pt-1">
              <Badge variant={systemHealth?.queue?.healthy ? 'secondary' : 'destructive'}>
                Queue: {systemHealth?.queue?.pending ?? 0} pending, {systemHealth?.queue?.failed ?? 0} failed
              </Badge>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Stats — from useAdminOverview() */}
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

      {/* Charts — from GET /api/admin/overview (companyGrowthData, messageVolumeData) */}
      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Company Growth</CardTitle>
            <CardDescription>Total registered companies over time</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-[300px]">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={overview.companyGrowthData ?? []}>
                  <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                  <XAxis dataKey="name" stroke="hsl(var(--muted-foreground))" fontSize={12} />
                  <YAxis stroke="hsl(var(--muted-foreground))" fontSize={12} />
                  <Tooltip contentStyle={{ backgroundColor: "hsl(var(--card))", border: "1px solid hsl(var(--border))", borderRadius: "8px" }} />
                  <Line type="monotone" dataKey="companies" stroke="hsl(var(--primary))" strokeWidth={2} dot={{ fill: "hsl(var(--primary))" }} />
                </LineChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Message Volume</CardTitle>
            <CardDescription>Messages processed per day this week</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-[300px]">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={overview.messageVolumeData ?? []}>
                  <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                  <XAxis dataKey="name" stroke="hsl(var(--muted-foreground))" fontSize={12} />
                  <YAxis stroke="hsl(var(--muted-foreground))" fontSize={12} tickFormatter={(value) => `${value / 1000}k`} />
                  <Tooltip contentStyle={{ backgroundColor: "hsl(var(--card))", border: "1px solid hsl(var(--border))", borderRadius: "8px" }} formatter={(value: number) => [`${(value / 1000).toFixed(0)}k messages`, "Messages"]} />
                  <Bar dataKey="messages" fill="hsl(var(--primary))" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Recent Companies — from useAdminCompanies() */}
      <Card>
        <CardHeader>
          <CardTitle>Recent Companies</CardTitle>
          <CardDescription>Latest companies registered on the platform</CardDescription>
        </CardHeader>
        <CardContent>
          {companiesLoading ? (
            <div className="py-8 text-center text-muted-foreground">Loading...</div>
          ) : recentCompanies.length === 0 ? (
            <div className="py-8 text-center text-muted-foreground">No companies yet.</div>
          ) : (
            <div className="space-y-4">
              {recentCompanies.map((company) => (
                <div key={company.id} className="flex items-center justify-between rounded-lg border border-border p-4">
                  <div className="flex items-center gap-4">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-sm font-medium text-primary">
                      {company.name.charAt(0)}
                    </div>
                    <div>
                      <div className="font-medium text-foreground">{company.name}</div>
                      <div className="text-sm text-muted-foreground">{company.totalChats} chats • {company.plan}</div>
                    </div>
                  </div>
                  <div className="text-right">
                    <div className={`text-sm font-medium ${company.status === "active" ? "text-primary" : "text-muted-foreground"}`}>
                      {company.status}
                    </div>
                    <div className="text-xs text-muted-foreground">{new Date(company.createdAt).toLocaleDateString()}</div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
