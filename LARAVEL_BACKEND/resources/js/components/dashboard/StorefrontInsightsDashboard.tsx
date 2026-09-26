'use client'

import { useMemo, useState } from 'react'
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { Eye, Globe, ShoppingBag, ShoppingCart, Store, Users, CreditCard } from 'lucide-react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { StatsCard, StatsGrid } from '@/components/shared/stats-card'
import { useStorefrontAnalytics } from '@/lib/api-hooks'
import { CHART_PRIMARY } from '@/lib/chart-colors'

const tooltipStyle = {
  backgroundColor: 'var(--card)',
  border: '1px solid var(--border)',
  borderRadius: '8px',
  fontSize: '12px',
}

function countryFlag(code: string): string {
  const cc = code.toUpperCase()
  if (!/^[A-Z]{2}$/.test(cc)) return ''
  return String.fromCodePoint(...[...cc].map((c) => 127397 + c.charCodeAt(0)))
}

function countryName(code: string): string {
  try {
    return new Intl.DisplayNames(['en'], { type: 'region' }).of(code) ?? code
  } catch {
    return code
  }
}

export function StorefrontInsightsDashboard() {
  const [days, setDays] = useState('30')
  const { data, error, isLoading } = useStorefrontAnalytics(Number(days))

  const chartData = data?.visitorsPerDay ?? []
  const topProducts = data?.topProducts ?? []
  const countries = data?.countries ?? []
  const visitors = data?.visitors ?? 0
  const purchases = data?.purchase ?? 0
  const conversion = visitors > 0 ? (purchases / visitors) * 100 : 0

  const countryMax = useMemo(
    () => Math.max(1, ...countries.map((c) => c.visitors)),
    [countries]
  )

  if (error && !data) {
    return (
      <Card className="border-destructive/40 bg-destructive/5 shadow-sm">
        <CardContent className="p-5">
          <p className="text-sm text-destructive">Could not load shop visits. Try again in a moment.</p>
        </CardContent>
      </Card>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 className="text-base font-semibold text-foreground">Shop visits</h2>
          <p className="mt-0.5 text-sm text-muted-foreground">
            People who opened your store, what they viewed, and where they came from.
          </p>
        </div>
        <Select value={days} onValueChange={setDays}>
          <SelectTrigger className="h-9 w-40 border-border/60 text-sm">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="7">Last 7 days</SelectItem>
            <SelectItem value="30">Last 30 days</SelectItem>
            <SelectItem value="90">Last 90 days</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <StatsGrid columns={3}>
        <StatsCard
          title="People"
          value={visitors}
          change={data?.visitorsChange}
          changeLabel="vs previous period"
          icon={Users}
          isLoading={isLoading && !data}
          formatter={(v) => v.toLocaleString()}
        />
        <StatsCard
          title="Shop views"
          value={data?.view_catalog ?? 0}
          description="Times the store page opened"
          icon={Store}
          isLoading={isLoading && !data}
          formatter={(v) => v.toLocaleString()}
        />
        <StatsCard
          title="Purchases"
          value={purchases}
          description={
            visitors > 0 ? `${conversion.toFixed(1)}% of visitors bought` : 'Completed checkouts'
          }
          icon={ShoppingBag}
          isLoading={isLoading && !data}
          formatter={(v) => v.toLocaleString()}
        />
      </StatsGrid>

      <StatsGrid columns={3}>
        <StatsCard
          title="Product views"
          value={data?.view_product ?? 0}
          icon={Eye}
          isLoading={isLoading && !data}
          formatter={(v) => v.toLocaleString()}
        />
        <StatsCard
          title="Added to cart"
          value={data?.add_to_cart ?? 0}
          icon={ShoppingCart}
          isLoading={isLoading && !data}
          formatter={(v) => v.toLocaleString()}
        />
        <StatsCard
          title="Started checkout"
          value={data?.begin_checkout ?? 0}
          icon={CreditCard}
          isLoading={isLoading && !data}
          formatter={(v) => v.toLocaleString()}
        />
      </StatsGrid>

      <Card className="border-border/60 shadow-sm">
        <CardHeader className="pb-2">
          <CardTitle className="text-base">People over time</CardTitle>
          <CardDescription>Unique shoppers each day in this period</CardDescription>
        </CardHeader>
        <CardContent className="h-64 pt-2">
          {isLoading && !data ? (
            <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
              Loading visits…
            </div>
          ) : visitors === 0 ? (
            <div className="flex h-full flex-col items-center justify-center gap-1 text-center">
              <p className="text-sm font-medium text-foreground">No visits yet</p>
              <p className="max-w-sm text-xs text-muted-foreground">
                Share your store link. Counts show up after someone opens the shop.
              </p>
            </div>
          ) : (
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={chartData} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="storeVisitorsFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor={CHART_PRIMARY} stopOpacity={0.28} />
                    <stop offset="100%" stopColor={CHART_PRIMARY} stopOpacity={0.02} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                <XAxis dataKey="date" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                <YAxis allowDecimals={false} tick={{ fontSize: 11 }} tickLine={false} axisLine={false} width={28} />
                <Tooltip contentStyle={tooltipStyle} />
                <Area
                  type="monotone"
                  dataKey="value"
                  name="People"
                  stroke={CHART_PRIMARY}
                  fill="url(#storeVisitorsFill)"
                  strokeWidth={2}
                />
              </AreaChart>
            </ResponsiveContainer>
          )}
        </CardContent>
      </Card>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card className="border-border/60 shadow-sm">
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Most viewed products</CardTitle>
            <CardDescription>What shoppers opened in this period</CardDescription>
          </CardHeader>
          <CardContent>
            {topProducts.length === 0 ? (
              <p className="py-8 text-center text-sm text-muted-foreground">
                Product views will appear here.
              </p>
            ) : (
              <div className="h-56">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={topProducts} layout="vertical" margin={{ top: 4, right: 12, left: 8, bottom: 0 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" horizontal={false} />
                    <XAxis type="number" allowDecimals={false} tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                    <YAxis
                      type="category"
                      dataKey="name"
                      width={110}
                      tick={{ fontSize: 11 }}
                      tickLine={false}
                      axisLine={false}
                    />
                    <Tooltip contentStyle={tooltipStyle} />
                    <Bar dataKey="views" name="Views" fill={CHART_PRIMARY} radius={[0, 6, 6, 0]} barSize={16} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            )}
          </CardContent>
        </Card>

        <Card className="border-border/60 shadow-sm">
          <CardHeader className="pb-2">
            <div className="flex items-center gap-2">
              <Globe className="h-4 w-4 text-muted-foreground" />
              <div>
                <CardTitle className="text-base">Where visitors are</CardTitle>
                <CardDescription>Country from the network, not a stored IP</CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            {countries.length === 0 ? (
              <p className="py-8 text-center text-sm text-muted-foreground">
                Country shows for visits that come through the live shop.
              </p>
            ) : (
              <ul className="space-y-3">
                {countries.map((row) => (
                  <li key={row.country} className="space-y-1.5">
                    <div className="flex items-center justify-between gap-3 text-sm">
                      <span className="min-w-0 truncate font-medium">
                        {countryFlag(row.country)} {countryName(row.country)}
                      </span>
                      <span className="tabular-nums text-muted-foreground">
                        {row.visitors.toLocaleString()}
                      </span>
                    </div>
                    <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                      <div
                        className="h-full rounded-full bg-primary"
                        style={{ width: `${Math.max(6, (row.visitors / countryMax) * 100)}%` }}
                      />
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
