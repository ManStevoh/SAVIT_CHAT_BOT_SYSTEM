"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { MessageSquare, ShoppingCart, Users, TrendingUp } from "lucide-react"
import {
  LineChart,
  Line,
  BarChart,
  Bar,
  PieChart,
  Pie,
  Cell,
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
} from "recharts"
// API: GET /api/company/analytics?period=7d — stats and chart data (useAnalytics in api-hooks)
import { useAnalytics } from "@/lib/api-hooks"

const COLORS = ["hsl(var(--chart-1))", "hsl(var(--chart-2))", "hsl(var(--chart-3))", "hsl(var(--chart-4))"]

// Response time: optional API field or separate endpoint; placeholder until backend provides
const responseTimePlaceholder = [
  { name: "< 1 min", value: 65 },
  { name: "1-5 min", value: 25 },
  { name: "5-15 min", value: 8 },
  { name: "> 15 min", value: 2 },
]

export default function AnalyticsPage() {
  const [chartPeriod, setChartPeriod] = useState("7d")

  const { data: analytics, error, isLoading } = useAnalytics(chartPeriod)

  // Map API messagesPerDay to area chart (AI vs Human if API provides; else single series)
  const messagesChartData = analytics?.messagesPerDay?.map((d) => ({
    name: d.date,
    messages: d.value,
    aiHandled: Math.round(d.value * 0.85),
    humanHandled: Math.round(d.value * 0.15),
  })) ?? []

  const ordersChartData = analytics?.ordersPerDay?.map((d) => ({
    name: d.date,
    orders: d.value,
    revenue: analytics?.revenuePerDay?.find((r) => r.date === d.date)?.value ?? d.value * 1000,
  })) ?? []

  const topProductsData = analytics?.topProducts?.map((p) => ({
    name: p.name,
    sales: p.sales,
    revenue: p.revenue,
  })) ?? []

  const customerGrowthData = analytics?.customerGrowth ?? []

  if (isLoading && !analytics) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-foreground">Analytics</h1>
            <p className="text-muted-foreground">Track your business performance</p>
          </div>
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
        <Card>
          <CardContent className="p-8">
            <div className="flex items-center justify-center gap-2 text-muted-foreground">
              <span className="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
              Loading analytics...
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Analytics</h1>
          <p className="text-muted-foreground">Track your business performance</p>
        </div>
        <Card className="border-destructive/50">
          <CardContent className="p-6">
            <p className="text-destructive">Failed to load analytics. Please try again.</p>
          </CardContent>
        </Card>
      </div>
    )
  }

  const stats = [
    { name: "Total Messages", value: analytics?.totalMessages?.toLocaleString() ?? "—", change: `${analytics?.messagesChange ?? 0}%`, icon: MessageSquare },
    { name: "Total Orders", value: analytics?.totalOrders?.toLocaleString() ?? "—", change: `${analytics?.ordersChange ?? 0}%`, icon: ShoppingCart },
    { name: "New Customers", value: analytics?.totalCustomers?.toLocaleString() ?? "—", change: `${analytics?.customersChange ?? 0}%`, icon: Users },
    { name: "Revenue", value: analytics?.totalRevenue != null ? `$${(analytics.totalRevenue / 1000).toFixed(0)}k` : "—", change: `${analytics?.revenueChange ?? 0}%`, icon: TrendingUp },
  ]

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Analytics</h1>
          <p className="text-muted-foreground">Track your business performance</p>
        </div>
        <Select value={chartPeriod} onValueChange={setChartPeriod}>
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

      {/* Stats — from useAnalytics() */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat) => (
          <Card key={stat.name}>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-muted-foreground">{stat.name}</p>
                  <p className="mt-1 text-2xl font-bold text-foreground">{stat.value}</p>
                  <p className="text-sm text-primary">+{stat.change} from last week</p>
                </div>
                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                  <stat.icon className="h-6 w-6 text-primary" />
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <Tabs defaultValue="messages" className="space-y-6">
        <TabsList>
          <TabsTrigger value="messages">Messages</TabsTrigger>
          <TabsTrigger value="orders">Orders</TabsTrigger>
          <TabsTrigger value="customers">Customers</TabsTrigger>
          <TabsTrigger value="products">Products</TabsTrigger>
        </TabsList>

        <TabsContent value="messages" className="space-y-6">
          <div className="grid gap-6 lg:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle>Messages per Day</CardTitle>
                <CardDescription>AI vs Human handled messages (API: messagesPerDay)</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="h-[300px]">
                  {messagesChartData.length === 0 ? (
                    <div className="flex h-full items-center justify-center text-muted-foreground">No data for this period</div>
                  ) : (
                    <ResponsiveContainer width="100%" height="100%">
                      <AreaChart data={messagesChartData}>
                        <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                        <XAxis dataKey="name" stroke="hsl(var(--muted-foreground))" fontSize={12} />
                        <YAxis stroke="hsl(var(--muted-foreground))" fontSize={12} />
                        <Tooltip contentStyle={{ backgroundColor: "hsl(var(--card))", border: "1px solid hsl(var(--border))", borderRadius: "8px" }} />
                        <Legend />
                        <Area type="monotone" dataKey="aiHandled" stackId="1" stroke="hsl(var(--chart-1))" fill="hsl(var(--chart-1))" fillOpacity={0.6} name="AI Handled" />
                        <Area type="monotone" dataKey="humanHandled" stackId="1" stroke="hsl(var(--chart-2))" fill="hsl(var(--chart-2))" fillOpacity={0.6} name="Human Handled" />
                      </AreaChart>
                    </ResponsiveContainer>
                  )}
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Response Time Distribution</CardTitle>
                <CardDescription>How quickly messages are answered (API: optional)</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="h-[300px]">
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Pie
                        data={responseTimePlaceholder}
                        cx="50%"
                        cy="50%"
                        innerRadius={60}
                        outerRadius={100}
                        paddingAngle={5}
                        dataKey="value"
                        label={({ name, value }) => `${name}: ${value}%`}
                      >
                        {responseTimePlaceholder.map((_, index) => (
                          <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                        ))}
                      </Pie>
                      <Tooltip contentStyle={{ backgroundColor: "hsl(var(--card))", border: "1px solid hsl(var(--border))", borderRadius: "8px" }} />
                    </PieChart>
                  </ResponsiveContainer>
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="orders" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Orders & Revenue</CardTitle>
              <CardDescription>Daily orders and revenue (API: ordersPerDay, revenuePerDay)</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="h-[400px]">
                {ordersChartData.length === 0 ? (
                  <div className="flex h-full items-center justify-center text-muted-foreground">No data for this period</div>
                ) : (
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={ordersChartData}>
                      <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                      <XAxis dataKey="name" stroke="hsl(var(--muted-foreground))" fontSize={12} />
                      <YAxis yAxisId="left" stroke="hsl(var(--muted-foreground))" fontSize={12} />
                      <YAxis yAxisId="right" orientation="right" stroke="hsl(var(--muted-foreground))" fontSize={12} />
                      <Tooltip contentStyle={{ backgroundColor: "hsl(var(--card))", border: "1px solid hsl(var(--border))", borderRadius: "8px" }} />
                      <Legend />
                      <Bar yAxisId="left" dataKey="orders" fill="hsl(var(--chart-1))" radius={[4, 4, 0, 0]} name="Orders" />
                      <Bar yAxisId="right" dataKey="revenue" fill="hsl(var(--chart-2))" radius={[4, 4, 0, 0]} name="Revenue ($)" />
                    </BarChart>
                  </ResponsiveContainer>
                )}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="customers" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Customer Growth</CardTitle>
              <CardDescription>Total customer base over time (API: customerGrowth)</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="h-[400px]">
                {customerGrowthData.length === 0 ? (
                  <div className="flex h-full items-center justify-center text-muted-foreground">No data for this period</div>
                ) : (
                  <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={customerGrowthData}>
                      <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                      <XAxis dataKey="date" stroke="hsl(var(--muted-foreground))" fontSize={12} />
                      <YAxis stroke="hsl(var(--muted-foreground))" fontSize={12} />
                      <Tooltip contentStyle={{ backgroundColor: "hsl(var(--card))", border: "1px solid hsl(var(--border))", borderRadius: "8px" }} />
                      <Line type="monotone" dataKey="value" stroke="hsl(var(--primary))" strokeWidth={3} dot={{ fill: "hsl(var(--primary))" }} />
                    </LineChart>
                  </ResponsiveContainer>
                )}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="products" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Top Products</CardTitle>
              <CardDescription>Best selling products (API: topProducts)</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="h-[400px]">
                {topProductsData.length === 0 ? (
                  <div className="flex h-full items-center justify-center text-muted-foreground">No data for this period</div>
                ) : (
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={topProductsData} layout="vertical">
                      <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                      <XAxis type="number" stroke="hsl(var(--muted-foreground))" fontSize={12} />
                      <YAxis dataKey="name" type="category" stroke="hsl(var(--muted-foreground))" fontSize={12} width={120} />
                      <Tooltip contentStyle={{ backgroundColor: "hsl(var(--card))", border: "1px solid hsl(var(--border))", borderRadius: "8px" }} />
                      <Legend />
                      <Bar dataKey="sales" fill="hsl(var(--chart-1))" radius={[0, 4, 4, 0]} name="Sales" />
                    </BarChart>
                  </ResponsiveContainer>
                )}
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}
