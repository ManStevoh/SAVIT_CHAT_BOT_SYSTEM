'use client'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  ResponsiveContainer,
  LineChart,
  Line,
  BarChart,
  Bar,
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  PieChart,
  Pie,
  Cell,
} from 'recharts'
import { CHART_PALETTE, CHART_PRIMARY } from '@/lib/chart-colors'

export interface ChartDataPoint {
  date: string
  value: number
  label?: string
}

interface ChartCardProps {
  title: string
  data: ChartDataPoint[] | undefined
  isLoading?: boolean
  error?: Error | null
  type?: 'line' | 'bar' | 'area' | 'pie'
  color?: string
  showPeriodSelector?: boolean
  period?: string
  onPeriodChange?: (period: string) => void
  valueFormatter?: (value: number) => string
  height?: number
}

export function ChartCard({
  title,
  data,
  isLoading,
  error,
  type = 'line',
  color = CHART_PRIMARY,
  showPeriodSelector,
  period = '7d',
  onPeriodChange,
  valueFormatter = (v) => v.toLocaleString(),
  height = 280,
}: ChartCardProps) {
  if (isLoading) {
    return (
      <Card className="border-border/60 bg-card shadow-sm">
        <CardHeader className="flex flex-row items-center justify-between pb-2">
          <Skeleton className="h-4 w-32" />
          {showPeriodSelector && <Skeleton className="h-8 w-28" />}
        </CardHeader>
        <CardContent>
          <Skeleton className="w-full rounded-lg" style={{ height }} />
        </CardContent>
      </Card>
    )
  }

  if (error) {
    return (
      <Card className="border-border/60 bg-card shadow-sm">
        <CardHeader>
          <CardTitle className="text-sm font-medium">{title}</CardTitle>
        </CardHeader>
        <CardContent>
          <div
            className="flex flex-col items-center justify-center text-center"
            style={{ height }}
          >
            <p className="text-sm text-destructive">Failed to load chart data</p>
            <p className="mt-1 text-xs text-muted-foreground">{error.message}</p>
          </div>
        </CardContent>
      </Card>
    )
  }

  if (!data || data.length === 0) {
    return (
      <Card className="border-border/60 bg-card shadow-sm">
        <CardHeader className="flex flex-row items-center justify-between pb-2">
          <CardTitle className="text-sm font-medium">{title}</CardTitle>
          {showPeriodSelector && (
            <Select value={period} onValueChange={onPeriodChange}>
              <SelectTrigger className="h-8 w-28 border-border/60 text-xs">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="7d">Last 7 days</SelectItem>
                <SelectItem value="30d">Last 30 days</SelectItem>
                <SelectItem value="90d">Last 90 days</SelectItem>
              </SelectContent>
            </Select>
          )}
        </CardHeader>
        <CardContent>
          <div
            className="flex flex-col items-center justify-center text-center"
            style={{ height }}
          >
            <p className="text-sm text-muted-foreground">No data available</p>
            <p className="mt-1 text-xs text-muted-foreground/70">
              Data will appear here once available
            </p>
          </div>
        </CardContent>
      </Card>
    )
  }

  const renderChart = () => {
    const commonProps = {
      data,
      margin: { top: 5, right: 5, left: -20, bottom: 5 },
    }

    const axisProps = {
      stroke: 'var(--muted-foreground)',
      fontSize: 11,
      tickLine: false,
      axisLine: false,
    }

    const CustomTooltip = ({
      active,
      payload,
      label,
    }: {
      active?: boolean
      payload?: { value: number }[]
      label?: string
    }) => {
      if (active && payload && payload.length) {
        return (
          <div className="rounded-lg border border-border bg-popover p-2.5 shadow-premium">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="text-sm font-medium tabular-nums text-foreground">
              {valueFormatter(payload[0].value)}
            </p>
          </div>
        )
      }
      return null
    }

    switch (type) {
      case 'bar':
        return (
          <ResponsiveContainer width="100%" height={height}>
            <BarChart {...commonProps}>
              <CartesianGrid strokeDasharray="3 3" className="stroke-border/60" vertical={false} />
              <XAxis dataKey="date" {...axisProps} />
              <YAxis {...axisProps} tickFormatter={valueFormatter} />
              <Tooltip content={<CustomTooltip />} />
              <Bar dataKey="value" fill={color} radius={[3, 3, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        )

      case 'area':
        return (
          <ResponsiveContainer width="100%" height={height}>
            <AreaChart {...commonProps}>
              <defs>
                <linearGradient id={`gradient-${title}`} x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor={color} stopOpacity={0.2} />
                  <stop offset="95%" stopColor={color} stopOpacity={0} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" className="stroke-border/60" vertical={false} />
              <XAxis dataKey="date" {...axisProps} />
              <YAxis {...axisProps} tickFormatter={valueFormatter} />
              <Tooltip content={<CustomTooltip />} />
              <Area
                type="monotone"
                dataKey="value"
                stroke={color}
                fill={`url(#gradient-${title})`}
                strokeWidth={2}
              />
            </AreaChart>
          </ResponsiveContainer>
        )

      case 'pie':
        return (
          <ResponsiveContainer width="100%" height={height}>
            <PieChart>
              <Pie
                data={data}
                cx="50%"
                cy="50%"
                innerRadius={60}
                outerRadius={100}
                paddingAngle={2}
                dataKey="value"
                nameKey="date"
                label={({ date, value }) => `${date}: ${valueFormatter(value)}`}
              >
                {data.map((_, index) => (
                  <Cell key={`cell-${index}`} fill={CHART_PALETTE[index % CHART_PALETTE.length]} />
                ))}
              </Pie>
              <Tooltip content={<CustomTooltip />} />
              <Legend />
            </PieChart>
          </ResponsiveContainer>
        )

      default:
        return (
          <ResponsiveContainer width="100%" height={height}>
            <LineChart {...commonProps}>
              <CartesianGrid strokeDasharray="3 3" className="stroke-border/60" vertical={false} />
              <XAxis dataKey="date" {...axisProps} />
              <YAxis {...axisProps} tickFormatter={valueFormatter} />
              <Tooltip content={<CustomTooltip />} />
              <Line
                type="monotone"
                dataKey="value"
                stroke={color}
                strokeWidth={2}
                dot={false}
                activeDot={{ r: 4, fill: color }}
              />
            </LineChart>
          </ResponsiveContainer>
        )
    }
  }

  return (
    <Card className="border-border/60 bg-card shadow-sm">
      <CardHeader className="flex flex-row items-center justify-between pb-2">
        <CardTitle className="text-sm font-medium">{title}</CardTitle>
        {showPeriodSelector && (
          <Select value={period} onValueChange={onPeriodChange}>
            <SelectTrigger className="h-8 w-28 border-border/60 text-xs">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="7d">Last 7 days</SelectItem>
              <SelectItem value="30d">Last 30 days</SelectItem>
              <SelectItem value="90d">Last 90 days</SelectItem>
            </SelectContent>
          </Select>
        )}
      </CardHeader>
      <CardContent>{renderChart()}</CardContent>
    </Card>
  )
}
