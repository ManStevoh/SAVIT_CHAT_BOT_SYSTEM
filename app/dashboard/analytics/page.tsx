"use client"

import { useState } from "react"
import { useSearchParams } from "next/navigation"
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
import { useAnalytics, useCompanySettings } from "@/lib/api-hooks"
import { formatCurrencyAmount, normalizeCurrencyCode } from "@/lib/format-currency"
import { StatsCard, StatsGrid } from "@/components/shared/stats-card"
import { PageHeader } from "@/components/shared/page-header"
import { CHART_PALETTE, CHART_PRIMARY, CHART_ACCENT } from "@/lib/chart-colors"

const responseTimePlaceholder = [
  { name: "< 1 min", value: 65 },
  { name: "1-5 min", value: 25 },
  { name: "5-15 min", value: 8 },
  { name: "> 15 min", value: 2 },
]

const tooltipStyle = {
  backgroundColor: "var(--card)",
  border: "1px solid var(--border)",
  borderRadius: "8px",
  fontSize: "12px",
}

export default function AnalyticsPage() {
  const searchParams = useSearchParams()
  const requestedTab = searchParams.get("tab") ?? "messages"
  const initialTab = ["messages", "orders", "customers", "products"].includes(requestedTab)
    ? requestedTab
    : "messages"
  const selectedProduct = searchParams.get("product")?.trim() ?? ""
  const [chartPeriod, setChartPeriod] = useState("7d")
  const [activeTab, setActiveTab] = useState(initialTab)

  const { data: analytics, error, isLoading } = useAnalytics(chartPeriod)
  const { data: companySettings } = useCompanySettings()
  const catalogCurrency = normalizeCurrencyCode(companySettings?.displayCurrency)

  const messagesChartData =
    analytics?.messagesPerDay?.map((d) => ({
      name: d.date,
      messages: d.value,
      aiHandled: Math.round(d.value * 0.85),
      humanHandled: Math.round(d.value * 0.15),
    })) ?? []

  const ordersChartData =
    analytics?.ordersPerDay?.map((d) => ({
      name: d.date,
      orders: d.value,
      revenue:
        analytics?.revenuePerDay?.find((r) => r.date === d.date)?.value ?? d.value * 1000,
    })) ?? []

  const topProductsData =
    analytics?.topProducts?.map((p) => ({
      name: p.name,
      sales: p.sales,
      revenue: p.revenue,
    })) ?? []

  const filteredTopProductsData = selectedProduct
    ? topProductsData.filter((p) =>
        p.name.toLowerCase().includes(selectedProduct.toLowerCase())
      )
    : topProductsData

  const customerGrowthData = analytics?.customerGrowth ?? []

  if (isLoading && !analytics) {
    return (
      <div className="space-y-8">
        <PageHeader title="Analytics" description="Track your business performance" />
        <StatsGrid columns={4}>
          {[1, 2, 3, 4].map((i) => (
            <StatsCard key={i} title="Loading" value={0} isLoading />
          ))}
        </StatsGrid>
      </div>
    )
  }

  if (error) {
    return (
      <div className="space-y-8">
        <PageHeader title="Analytics" description="Track your business performance" />
        <Card className="border-destructive/40 bg-destructive/5 shadow-sm">
          <CardContent className="p-5">
            <p className="text-sm text-destructive">Failed to load analytics. Please try again.</p>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="space-y-8">
      <PageHeader
        title="Analytics"
        description="Track your business performance"
        actions={
          <Select value={chartPeriod} onValueChange={setChartPeriod}>
            <SelectTrigger className="h-9 w-36 border-border/60 text-sm">
              <SelectValue placeholder="Select period" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="24h">Last 24 hours</SelectItem>
              <SelectItem value="7d">Last 7 days</SelectItem>
              <SelectItem value="30d">Last 30 days</SelectItem>
              <SelectItem value="90d">Last 90 days</SelectItem>
            </SelectContent>
          </Select>
        }
      />

      <StatsGrid columns={4}>
        <StatsCard
          title="Total messages"
          value={analytics?.totalMessages ?? 0}
          change={analytics?.messagesChange}
          changeLabel="vs previous period"
          icon={MessageSquare}
          formatter={(v) => v.toLocaleString()}
        />
        <StatsCard
          title="Total orders"
          value={analytics?.totalOrders ?? 0}
          change={analytics?.ordersChange}
          changeLabel="vs previous period"
          icon={ShoppingCart}
          formatter={(v) => v.toLocaleString()}
        />
        <StatsCard
          title="New customers"
          value={analytics?.totalCustomers ?? 0}
          change={analytics?.customersChange}
          changeLabel="vs previous period"
          icon={Users}
          formatter={(v) => v.toLocaleString()}
        />
        <StatsCard
          title="Revenue"
          value={analytics?.totalRevenue ?? 0}
          change={analytics?.revenueChange}
          changeLabel="vs previous period"
          icon={TrendingUp}
          formatter={(v) => formatCurrencyAmount(v, catalogCurrency)}
        />
      </StatsGrid>

      <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-5">
        <TabsList className="h-9 bg-muted/60 p-1">
          <TabsTrigger value="messages" className="text-xs">
            Messages
          </TabsTrigger>
          <TabsTrigger value="orders" className="text-xs">
            Orders
          </TabsTrigger>
          <TabsTrigger value="customers" className="text-xs">
            Customers
          </TabsTrigger>
          <TabsTrigger value="products" className="text-xs">
            Products
          </TabsTrigger>
        </TabsList>

        <TabsContent value="messages" className="space-y-5">
          <div className="grid gap-5 lg:grid-cols-2">
            <Card className="border-border/60 bg-card shadow-sm">
              <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium">Messages per day</CardTitle>
                <CardDescription className="text-xs">AI vs human handled messages</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="h-[280px]">
                  {messagesChartData.length === 0 ? (
                    <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                      No data for this period
                    </div>
                  ) : (
                    <ResponsiveContainer width="100%" height="100%">
                      <AreaChart data={messagesChartData}>
                        <CartesianGrid strokeDasharray="3 3" className="stroke-border/60" vertical={false} />
                        <XAxis dataKey="name" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                        <YAxis tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                        <Tooltip contentStyle={tooltipStyle} />
                        <Legend />
                        <Area
                          type="monotone"
                          dataKey="aiHandled"
                          stackId="1"
                          stroke={CHART_PRIMARY}
                          fill={CHART_PRIMARY}
                          fillOpacity={0.5}
                          name="AI handled"
                        />
                        <Area
                          type="monotone"
                          dataKey="humanHandled"
                          stackId="1"
                          stroke={CHART_ACCENT}
                          fill={CHART_ACCENT}
                          fillOpacity={0.5}
                          name="Human handled"
                        />
                      </AreaChart>
                    </ResponsiveContainer>
                  )}
                </div>
              </CardContent>
            </Card>

            <Card className="border-border/60 bg-card shadow-sm">
              <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium">Response time</CardTitle>
                <CardDescription className="text-xs">How quickly messages are answered</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="h-[280px]">
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Pie
                        data={responseTimePlaceholder}
                        cx="50%"
                        cy="50%"
                        innerRadius={60}
                        outerRadius={95}
                        paddingAngle={3}
                        dataKey="value"
                        label={({ name, value }) => `${name}: ${value}%`}
                      >
                        {responseTimePlaceholder.map((_, index) => (
                          <Cell key={`cell-${index}`} fill={CHART_PALETTE[index % CHART_PALETTE.length]} />
                        ))}
                      </Pie>
                      <Tooltip contentStyle={tooltipStyle} />
                    </PieChart>
                  </ResponsiveContainer>
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="orders">
          <Card className="border-border/60 bg-card shadow-sm">
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium">Orders & revenue</CardTitle>
              <CardDescription className="text-xs">Daily orders and revenue trends</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="h-[360px]">
                {ordersChartData.length === 0 ? (
                  <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                    No data for this period
                  </div>
                ) : (
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={ordersChartData}>
                      <CartesianGrid strokeDasharray="3 3" className="stroke-border/60" vertical={false} />
                      <XAxis dataKey="name" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                      <YAxis yAxisId="left" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                      <YAxis yAxisId="right" orientation="right" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                      <Tooltip contentStyle={tooltipStyle} />
                      <Legend />
                      <Bar yAxisId="left" dataKey="orders" fill={CHART_PRIMARY} radius={[3, 3, 0, 0]} name="Orders" />
                      <Bar
                        yAxisId="right"
                        dataKey="revenue"
                        fill={CHART_ACCENT}
                        radius={[3, 3, 0, 0]}
                        name={`Revenue (${catalogCurrency})`}
                      />
                    </BarChart>
                  </ResponsiveContainer>
                )}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="customers">
          <Card className="border-border/60 bg-card shadow-sm">
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium">Customer growth</CardTitle>
              <CardDescription className="text-xs">Total customer base over time</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="h-[360px]">
                {customerGrowthData.length === 0 ? (
                  <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                    No data for this period
                  </div>
                ) : (
                  <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={customerGrowthData}>
                      <CartesianGrid strokeDasharray="3 3" className="stroke-border/60" vertical={false} />
                      <XAxis dataKey="date" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                      <YAxis tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                      <Tooltip contentStyle={tooltipStyle} />
                      <Line
                        type="monotone"
                        dataKey="value"
                        stroke={CHART_PRIMARY}
                        strokeWidth={2}
                        dot={false}
                        activeDot={{ r: 4 }}
                      />
                    </LineChart>
                  </ResponsiveContainer>
                )}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="products" className="space-y-4">
          {selectedProduct !== "" && (
            <div className="rounded-lg border border-border/60 bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
              Showing product analytics for:{" "}
              <span className="font-medium text-foreground">{selectedProduct}</span>
            </div>
          )}
          <Card className="border-border/60 bg-card shadow-sm">
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium">Top products</CardTitle>
              <CardDescription className="text-xs">Best selling products this period</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="h-[360px]">
                {filteredTopProductsData.length === 0 ? (
                  <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                    No data for this period
                  </div>
                ) : (
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={filteredTopProductsData} layout="vertical">
                      <CartesianGrid strokeDasharray="3 3" className="stroke-border/60" horizontal={false} />
                      <XAxis type="number" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                      <YAxis dataKey="name" type="category" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} width={120} />
                      <Tooltip contentStyle={tooltipStyle} />
                      <Bar dataKey="sales" fill={CHART_PRIMARY} radius={[0, 3, 3, 0]} name="Sales" />
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
