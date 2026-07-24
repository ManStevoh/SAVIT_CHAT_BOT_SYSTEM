'use client'

import { useState, useEffect, Suspense } from 'react'
import Link from 'next/link'
import { useSearchParams } from 'next/navigation'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { StatsCard, StatsGrid } from '@/components/shared/stats-card'
import { ChartCard } from '@/components/shared/chart-card'
import { StatusBadge } from '@/components/shared/status-badge'
import { useAnalytics, useOrders, useChats, useCompanySettings, useSubscription } from '@/lib/api-hooks'
import { formatCurrencyAmount, normalizeCurrencyCode } from '@/lib/format-currency'
import { CHART_ACCENT, CHART_PRIMARY } from '@/lib/chart-colors'
import { MessageSquare, ShoppingCart, Users, Bot, ArrowRight } from 'lucide-react'

function getGreeting(): string {
  const hour = new Date().getHours()
  if (hour < 12) return 'Good morning'
  if (hour < 17) return 'Good afternoon'
  return 'Good evening'
}

function getStoredName(): string | null {
  if (typeof window === 'undefined') return null
  const raw = localStorage.getItem('auth_user') ?? sessionStorage.getItem('auth_user')
  if (!raw) return null
  try {
    const user = JSON.parse(raw) as { name?: string }
    return user.name?.split(' ')[0] ?? null
  } catch {
    return null
  }
}

export default function DashboardPage() {
  return (
    <Suspense fallback={<div className="p-6 text-sm text-muted-foreground">Loading dashboard…</div>}>
      <DashboardPageContent />
    </Suspense>
  )
}

function DashboardPageContent() {
  const searchParams = useSearchParams()
  const trialStarted = searchParams.get('trial_started') === '1'
  const [chartPeriod, setChartPeriod] = useState('7d')
  const [firstName, setFirstName] = useState<string | null>(null)
  const { data: subscription } = useSubscription()

  useEffect(() => {
    setFirstName(getStoredName())
  }, [])

  const { data: analytics, isLoading: analyticsLoading, error: analyticsError } = useAnalytics(chartPeriod)
  const { data: ordersData, isLoading: ordersLoading } = useOrders({ limit: 5 })
  const { data: chats, isLoading: chatsLoading } = useChats({ limit: 5 })
  const { data: companySettings } = useCompanySettings()
  const catalogCurrency = normalizeCurrencyCode(companySettings?.displayCurrency)

  const formatCurrency = (value: number) => formatCurrencyAmount(value, catalogCurrency)

  const periodLabel =
    chartPeriod === '7d' ? '7d' : chartPeriod === '30d' ? '30d' : '90d'

  const showTrialBanner =
    trialStarted ||
    (subscription?.status === 'trial' && (subscription.daysRemaining ?? 0) > 0)

  return (
    <div className="space-y-8">
      {showTrialBanner && (
        <div className="rounded-lg border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-foreground">
          <p className="font-medium">
            Welcome — your free trial
            {subscription?.planName ? ` of ${subscription.planName}` : ''} is active
            {typeof subscription?.daysRemaining === 'number'
              ? ` (${subscription.daysRemaining} day${subscription.daysRemaining === 1 ? '' : 's'} left)`
              : ''}
            .
          </p>
          <p className="mt-1 text-muted-foreground">
            Connect WhatsApp and explore the product. You can upgrade anytime from{' '}
            <Link href="/dashboard/subscription" className="font-medium text-primary underline">
              Subscription
            </Link>
            .
          </p>
        </div>
      )}

      <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
            {new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
          </p>
          <h1 className="mt-1 text-2xl font-semibold tracking-tight text-foreground">
            {getGreeting()}
            {firstName ? `, ${firstName}` : ''}
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Here&apos;s what&apos;s happening with your business today.
          </p>
        </div>
      </div>

      <StatsGrid columns={4}>
        <StatsCard
          title={`Messages (${periodLabel})`}
          value={analytics?.totalMessages || 0}
          change={analytics?.messagesChange}
          changeLabel="vs previous period"
          icon={MessageSquare}
          isLoading={analyticsLoading}
          formatter={(v) => v.toLocaleString()}
        />
        <StatsCard
          title={`Orders (${periodLabel})`}
          value={analytics?.totalOrders || 0}
          change={analytics?.ordersChange}
          changeLabel="vs previous period"
          icon={ShoppingCart}
          isLoading={analyticsLoading}
          formatter={(v) => v.toLocaleString()}
        />
        <StatsCard
          title={`Customers (${periodLabel})`}
          value={analytics?.totalCustomers || 0}
          change={analytics?.customersChange}
          changeLabel="vs previous period"
          icon={Users}
          isLoading={analyticsLoading}
          formatter={(v) => v.toLocaleString()}
        />
        <StatsCard
          title={`Revenue (${periodLabel})`}
          value={analytics?.totalRevenue || 0}
          change={analytics?.revenueChange}
          changeLabel="vs previous period"
          icon={Bot}
          isLoading={analyticsLoading}
          formatter={formatCurrency}
        />
      </StatsGrid>

      {analyticsError && (
        <Card className="border-destructive/40 bg-destructive/5 shadow-sm">
          <CardContent className="p-5">
            <p className="text-sm font-medium text-destructive">Failed to load analytics data</p>
            <p className="mt-1 text-sm text-muted-foreground">{analyticsError.message}</p>
            <Button variant="outline" size="sm" className="mt-4" onClick={() => window.location.reload()}>
              Try again
            </Button>
          </CardContent>
        </Card>
      )}

      <div className="grid gap-5 lg:grid-cols-2">
        <ChartCard
          title="Messages per day"
          data={analytics?.messagesPerDay}
          type="line"
          color={CHART_PRIMARY}
          isLoading={analyticsLoading}
          showPeriodSelector
          period={chartPeriod}
          onPeriodChange={setChartPeriod}
        />
        <ChartCard
          title="Orders per day"
          data={analytics?.ordersPerDay}
          type="bar"
          color={CHART_ACCENT}
          isLoading={analyticsLoading}
          showPeriodSelector
          period={chartPeriod}
          onPeriodChange={setChartPeriod}
        />
      </div>

      <div className="grid gap-5 lg:grid-cols-2">
        <Card className="border-border/60 bg-card shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-3">
            <div>
              <CardTitle className="text-sm font-medium">Recent orders</CardTitle>
              <CardDescription className="text-xs">Latest orders from customers</CardDescription>
            </div>
            <Button variant="ghost" size="sm" asChild className="h-8 text-xs text-muted-foreground">
              <Link href="/dashboard/orders">
                View all <ArrowRight className="ml-1 h-3.5 w-3.5" />
              </Link>
            </Button>
          </CardHeader>
          <CardContent className="pt-0">
            {ordersLoading ? (
              <div className="space-y-3">
                {[...Array(4)].map((_, i) => (
                  <div key={i} className="flex items-center justify-between">
                    <div className="space-y-2">
                      <Skeleton className="h-3.5 w-24" />
                      <Skeleton className="h-3 w-32" />
                    </div>
                    <Skeleton className="h-5 w-16" />
                  </div>
                ))}
              </div>
            ) : ordersData?.orders && ordersData.orders.length > 0 ? (
              <div className="divide-y divide-border/60">
                {ordersData.orders.slice(0, 4).map((order) => (
                  <div
                    key={order.id}
                    className="flex items-center justify-between py-3 first:pt-0 last:pb-0"
                  >
                    <div className="min-w-0 space-y-0.5">
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-medium text-foreground">
                          {order.orderNumber}
                        </span>
                        <StatusBadge status={order.status} />
                      </div>
                      <p className="truncate text-xs text-muted-foreground">
                        {order.customerName} · {order.products.length} item(s)
                      </p>
                    </div>
                    <span className="ml-3 shrink-0 text-sm font-medium tabular-nums text-foreground">
                      {formatCurrency(order.total)}
                    </span>
                  </div>
                ))}
              </div>
            ) : (
              <div className="flex flex-col items-center justify-center py-10 text-center">
                <ShoppingCart className="h-10 w-10 text-muted-foreground/30" strokeWidth={1.25} />
                <p className="mt-3 text-sm font-medium text-foreground">No orders yet</p>
                <p className="mt-1 text-xs text-muted-foreground">
                  Orders will appear here when customers place them
                </p>
              </div>
            )}
          </CardContent>
        </Card>

        <Card className="border-border/60 bg-card shadow-sm">
          <CardHeader className="flex flex-row items-center justify-between pb-3">
            <div>
              <CardTitle className="text-sm font-medium">Recent conversations</CardTitle>
              <CardDescription className="text-xs">Latest customer messages</CardDescription>
            </div>
            <Button variant="ghost" size="sm" asChild className="h-8 text-xs text-muted-foreground">
              <Link href="/dashboard/chats">
                View all <ArrowRight className="ml-1 h-3.5 w-3.5" />
              </Link>
            </Button>
          </CardHeader>
          <CardContent className="pt-0">
            {chatsLoading ? (
              <div className="space-y-3">
                {[...Array(4)].map((_, i) => (
                  <div key={i} className="flex items-start gap-3">
                    <Skeleton className="h-9 w-9 rounded-full" />
                    <div className="flex-1 space-y-2">
                      <Skeleton className="h-3.5 w-24" />
                      <Skeleton className="h-3 w-full" />
                    </div>
                  </div>
                ))}
              </div>
            ) : chats && chats.length > 0 ? (
              <div className="divide-y divide-border/60">
                {chats.slice(0, 4).map((chat) => (
                  <div key={chat.id} className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium text-foreground">
                      {chat.customerName.charAt(0)}
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center justify-between gap-2">
                        <span className="truncate text-sm font-medium text-foreground">
                          {chat.customerName}
                        </span>
                        <span className="shrink-0 text-[11px] text-muted-foreground">
                          {chat.lastMessageTime}
                        </span>
                      </div>
                      <p className="truncate text-xs text-muted-foreground">
                        {chat.lastMessage}
                      </p>
                    </div>
                    {chat.unreadCount > 0 && (
                      <span className="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-medium text-primary-foreground">
                        {chat.unreadCount}
                      </span>
                    )}
                  </div>
                ))}
              </div>
            ) : (
              <div className="flex flex-col items-center justify-center py-10 text-center">
                <MessageSquare className="h-10 w-10 text-muted-foreground/30" strokeWidth={1.25} />
                <p className="mt-3 text-sm font-medium text-foreground">No conversations yet</p>
                <p className="mt-1 text-xs text-muted-foreground">
                  Messages will appear here when customers contact you
                </p>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      <Card className="border-border/60 bg-card shadow-sm">
        <CardHeader className="pb-3">
          <CardTitle className="text-sm font-medium">Top products</CardTitle>
          <CardDescription className="text-xs">Best selling products this period</CardDescription>
        </CardHeader>
        <CardContent className="pt-0">
          {analyticsLoading ? (
            <div className="space-y-3">
              {[...Array(4)].map((_, i) => (
                <div key={i} className="flex items-center justify-between">
                  <Skeleton className="h-3.5 w-48" />
                  <Skeleton className="h-3.5 w-20" />
                </div>
              ))}
            </div>
          ) : analytics?.topProducts && analytics.topProducts.length > 0 ? (
            <div className="divide-y divide-border/60">
              {analytics.topProducts.map((product, index) => (
                <div
                  key={product.id}
                  className="flex items-center justify-between py-3 first:pt-0 last:pb-0"
                >
                  <div className="flex items-center gap-3">
                    <span className="flex h-7 w-7 items-center justify-center rounded-md bg-muted text-xs font-medium tabular-nums text-muted-foreground">
                      {index + 1}
                    </span>
                    <div>
                      <p className="text-sm font-medium text-foreground">{product.name}</p>
                      <p className="text-xs text-muted-foreground">{product.sales} sales</p>
                    </div>
                  </div>
                  <span className="text-sm font-medium tabular-nums text-foreground">
                    {formatCurrency(product.revenue)}
                  </span>
                </div>
              ))}
            </div>
          ) : (
            <div className="py-10 text-center text-sm text-muted-foreground">
              No product data available
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
