'use client'

import { useState } from 'react'
import Link from 'next/link'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { StatsCard, StatsGrid } from '@/components/shared/stats-card'
import { ChartCard } from '@/components/shared/chart-card'
import { StatusBadge } from '@/components/shared/status-badge'
import { useAnalytics, useOrders, useChats, useCompanySettings } from '@/lib/api-hooks'
import { formatCurrencyAmount, normalizeCurrencyCode } from '@/lib/format-currency'
import { MessageSquare, ShoppingCart, Users, Bot, ArrowRight } from 'lucide-react'

export default function DashboardPage() {
  const [chartPeriod, setChartPeriod] = useState('7d')
  
  // API: GET /api/company/analytics, orders?limit=5, chats?limit=5
  const { data: analytics, isLoading: analyticsLoading, error: analyticsError } = useAnalytics(chartPeriod)
  const { data: ordersData, isLoading: ordersLoading } = useOrders({ limit: 5 })
  const { data: chats, isLoading: chatsLoading } = useChats({ limit: 5 })
  const { data: companySettings } = useCompanySettings()
  const catalogCurrency = normalizeCurrencyCode(companySettings?.displayCurrency)

  const formatCurrency = (value: number) => formatCurrencyAmount(value, catalogCurrency)

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div>
        <h1 className="text-2xl font-bold text-foreground">Dashboard</h1>
        <p className="text-muted-foreground">
          Welcome back! Here&apos;s what&apos;s happening today.
        </p>
      </div>

      {/* Stats Grid — real data from GET /api/company/analytics */}
      <StatsGrid columns={4}>
        <StatsCard
          title={chartPeriod === '7d' ? 'Messages (7d)' : chartPeriod === '30d' ? 'Messages (30d)' : 'Messages (90d)'}
          value={analytics?.totalMessages || 0}
          change={analytics?.messagesChange}
          changeLabel="vs previous period"
          icon={MessageSquare}
          isLoading={analyticsLoading}
          formatter={(v) => v.toLocaleString()}
        />
        <StatsCard
          title={chartPeriod === '7d' ? 'Orders (7d)' : chartPeriod === '30d' ? 'Orders (30d)' : 'Orders (90d)'}
          value={analytics?.totalOrders || 0}
          change={analytics?.ordersChange}
          changeLabel="vs previous period"
          icon={ShoppingCart}
          isLoading={analyticsLoading}
          formatter={(v) => v.toLocaleString()}
        />
        <StatsCard
          title={chartPeriod === '7d' ? 'Customers (7d)' : chartPeriod === '30d' ? 'Customers (30d)' : 'Customers (90d)'}
          value={analytics?.totalCustomers || 0}
          change={analytics?.customersChange}
          changeLabel="vs previous period"
          icon={Users}
          isLoading={analyticsLoading}
          formatter={(v) => v.toLocaleString()}
        />
        <StatsCard
          title={chartPeriod === '7d' ? 'Revenue (7d)' : chartPeriod === '30d' ? 'Revenue (30d)' : 'Revenue (90d)'}
          value={analytics?.totalRevenue || 0}
          change={analytics?.revenueChange}
          changeLabel="vs previous period"
          icon={Bot}
          isLoading={analyticsLoading}
          formatter={formatCurrency}
        />
      </StatsGrid>

      {/* Error State for Analytics */}
      {analyticsError && (
        <Card className="border-destructive/50 bg-destructive/10">
          <CardContent className="p-6">
            <p className="text-destructive font-medium">Failed to load analytics data</p>
            <p className="text-muted-foreground text-sm mt-1">{analyticsError.message}</p>
            <Button variant="outline" className="mt-4" onClick={() => window.location.reload()}>
              Try Again
            </Button>
          </CardContent>
        </Card>
      )}

      {/* Charts - API Ready with Dynamic Data */}
      <div className="grid gap-6 lg:grid-cols-2">
        <ChartCard
          title="Messages per Day"
          data={analytics?.messagesPerDay}
          type="line"
          color="#22c55e"
          isLoading={analyticsLoading}
          showPeriodSelector
          period={chartPeriod}
          onPeriodChange={setChartPeriod}
        />
        <ChartCard
          title="Orders per Day"
          data={analytics?.ordersPerDay}
          type="bar"
          color="#22c55e"
          isLoading={analyticsLoading}
          showPeriodSelector
          period={chartPeriod}
          onPeriodChange={setChartPeriod}
        />
      </div>

      {/* Recent Data Tables - API Ready */}
      <div className="grid gap-6 lg:grid-cols-2">
        {/* Recent Orders Card */}
        <Card className="bg-card border-border/50">
          <CardHeader className="flex flex-row items-center justify-between">
            <div>
              <CardTitle className="text-base font-medium">Recent Orders</CardTitle>
              <CardDescription>Latest orders from customers</CardDescription>
            </div>
            <Button variant="ghost" size="sm" asChild>
              <Link href="/dashboard/orders">
                View All <ArrowRight className="ml-1 h-4 w-4" />
              </Link>
            </Button>
          </CardHeader>
          <CardContent>
            {ordersLoading ? (
              // Loading Skeleton
              <div className="space-y-4">
                {[...Array(4)].map((_, i) => (
                  <div key={i} className="flex items-center justify-between">
                    <div className="space-y-2">
                      <Skeleton className="h-4 w-24" />
                      <Skeleton className="h-3 w-32" />
                    </div>
                    <Skeleton className="h-6 w-16" />
                  </div>
                ))}
              </div>
            ) : ordersData?.orders && ordersData.orders.length > 0 ? (
              // Data Display
              <div className="space-y-4">
                {ordersData.orders.slice(0, 4).map((order) => (
                  <div
                    key={order.id}
                    className="flex items-center justify-between rounded-lg p-3 transition-colors hover:bg-muted/5"
                  >
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <span className="font-medium text-foreground">
                          {order.orderNumber}
                        </span>
                        <StatusBadge status={order.status} />
                      </div>
                      <p className="text-sm text-muted-foreground">
                        {order.customerName} - {order.products.length} item(s)
                      </p>
                    </div>
                    <span className="font-medium text-foreground">
                      {formatCurrency(order.total)}
                    </span>
                  </div>
                ))}
              </div>
            ) : (
              // Empty State
              <div className="flex flex-col items-center justify-center py-8 text-center">
                <ShoppingCart className="h-12 w-12 text-muted-foreground/50" />
                <p className="mt-4 font-medium text-foreground">No orders yet</p>
                <p className="text-sm text-muted-foreground">
                  Orders will appear here when customers place them
                </p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Recent Conversations Card */}
        <Card className="bg-card border-border/50">
          <CardHeader className="flex flex-row items-center justify-between">
            <div>
              <CardTitle className="text-base font-medium">Recent Conversations</CardTitle>
              <CardDescription>Latest customer messages</CardDescription>
            </div>
            <Button variant="ghost" size="sm" asChild>
              <Link href="/dashboard/chats">
                View All <ArrowRight className="ml-1 h-4 w-4" />
              </Link>
            </Button>
          </CardHeader>
          <CardContent>
            {chatsLoading ? (
              // Loading Skeleton
              <div className="space-y-4">
                {[...Array(4)].map((_, i) => (
                  <div key={i} className="flex items-start gap-3">
                    <Skeleton className="h-10 w-10 rounded-full" />
                    <div className="flex-1 space-y-2">
                      <Skeleton className="h-4 w-24" />
                      <Skeleton className="h-3 w-full" />
                    </div>
                  </div>
                ))}
              </div>
            ) : chats && chats.length > 0 ? (
              // Data Display
              <div className="space-y-4">
                {chats.slice(0, 4).map((chat) => (
                  <div
                    key={chat.id}
                    className="flex items-start gap-3 rounded-lg p-3 transition-colors hover:bg-muted/5"
                  >
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
                      {chat.customerName.charAt(0)}
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center justify-between">
                        <span className="font-medium text-foreground">
                          {chat.customerName}
                        </span>
                        <span className="text-xs text-muted-foreground">
                          {chat.lastMessageTime}
                        </span>
                      </div>
                      <p className="truncate text-sm text-muted-foreground">
                        {chat.lastMessage}
                      </p>
                    </div>
                    {chat.unreadCount > 0 && (
                      <span className="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-xs font-medium text-primary-foreground">
                        {chat.unreadCount}
                      </span>
                    )}
                  </div>
                ))}
              </div>
            ) : (
              // Empty State
              <div className="flex flex-col items-center justify-center py-8 text-center">
                <MessageSquare className="h-12 w-12 text-muted-foreground/50" />
                <p className="mt-4 font-medium text-foreground">No conversations yet</p>
                <p className="text-sm text-muted-foreground">
                  Messages will appear here when customers contact you
                </p>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Top Products - API Ready */}
      <Card className="bg-card border-border/50">
        <CardHeader>
          <CardTitle className="text-base font-medium">Top Products</CardTitle>
          <CardDescription>Best selling products this period</CardDescription>
        </CardHeader>
        <CardContent>
          {analyticsLoading ? (
            <div className="space-y-4">
              {[...Array(4)].map((_, i) => (
                <div key={i} className="flex items-center justify-between">
                  <Skeleton className="h-4 w-48" />
                  <Skeleton className="h-4 w-24" />
                </div>
              ))}
            </div>
          ) : analytics?.topProducts && analytics.topProducts.length > 0 ? (
            <div className="space-y-4">
              {analytics.topProducts.map((product, index) => (
                <div
                  key={product.id}
                  className="flex items-center justify-between rounded-lg p-3 transition-colors hover:bg-muted/5"
                >
                  <div className="flex items-center gap-3">
                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
                      {index + 1}
                    </span>
                    <div>
                      <p className="font-medium text-foreground">{product.name}</p>
                      <p className="text-sm text-muted-foreground">
                        {product.sales} sales
                      </p>
                    </div>
                  </div>
                  <span className="font-medium text-foreground">
                    {formatCurrency(product.revenue)}
                  </span>
                </div>
              ))}
            </div>
          ) : (
            <div className="flex flex-col items-center justify-center py-8 text-center">
              <p className="text-muted-foreground">No product data available</p>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
