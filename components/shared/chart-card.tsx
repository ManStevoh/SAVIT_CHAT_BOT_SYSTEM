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

const COLORS = ['#22c55e', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899']

export function ChartCard({
  title,
  data,
  isLoading,
  error,
  type = 'line',
  color = '#22c55e',
  showPeriodSelector,
  period = '7d',
  onPeriodChange,
  valueFormatter = (v) => v.toLocaleString(),
  height = 300,
}: ChartCardProps) {
  // Loading State
  if (isLoading) {
    return (
      <Card className="bg-card border-border/50">
        <CardHeader className="flex flex-row items-center justify-between pb-2">
          <Skeleton className="h-5 w-32" />
          {showPeriodSelector && <Skeleton className="h-9 w-28" />}
        </CardHeader>
        <CardContent>
          <Skeleton className="w-full" style={{ height }} />
        </CardContent>
      </Card>
    )
  }

  // Error State
  if (error) {
    return (
      <Card className="bg-card border-border/50">
        <CardHeader>
          <CardTitle className="text-base font-medium">{title}</CardTitle>
        </CardHeader>
        <CardContent>
          <div
            className="flex flex-col items-center justify-center text-center"
            style={{ height }}
          >
            <p className="text-destructive text-sm">Failed to load chart data</p>
            <p className="text-muted-foreground text-xs mt-1">{error.message}</p>
          </div>
        </CardContent>
      </Card>
    )
  }

  // Empty State
  if (!data || data.length === 0) {
    return (
      <Card className="bg-card border-border/50">
        <CardHeader className="flex flex-row items-center justify-between pb-2">
          <CardTitle className="text-base font-medium">{title}</CardTitle>
          {showPeriodSelector && (
            <Select value={period} onValueChange={onPeriodChange}>
              <SelectTrigger className="w-28 bg-card border-border/50">
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
            <p className="text-muted-foreground text-sm">No data available</p>
            <p className="text-muted-foreground/70 text-xs mt-1">
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
      stroke: '#6b7280',
      fontSize: 12,
      tickLine: false,
      axisLine: false,
    }

    const CustomTooltip = ({ active, payload, label }: { active?: boolean, payload?: { value: number }[], label?: string }) => {
      if (active && payload && payload.length) {
        return (
          <div className="bg-popover border border-border rounded-lg p-3 shadow-lg">
            <p className="text-muted-foreground text-xs">{label}</p>
            <p className="text-foreground font-medium">
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
              <CartesianGrid strokeDasharray="3 3" stroke="#374151" vertical={false} />
              <XAxis dataKey="date" {...axisProps} />
              <YAxis {...axisProps} tickFormatter={valueFormatter} />
              <Tooltip content={<CustomTooltip />} />
              <Bar dataKey="value" fill={color} radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        )

      case 'area':
        return (
          <ResponsiveContainer width="100%" height={height}>
            <AreaChart {...commonProps}>
              <defs>
                <linearGradient id={`gradient-${title}`} x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor={color} stopOpacity={0.3} />
                  <stop offset="95%" stopColor={color} stopOpacity={0} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" stroke="#374151" vertical={false} />
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
                  <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                ))}
              </Pie>
              <Tooltip content={<CustomTooltip />} />
              <Legend />
            </PieChart>
          </ResponsiveContainer>
        )

      default: // line
        return (
          <ResponsiveContainer width="100%" height={height}>
            <LineChart {...commonProps}>
              <CartesianGrid strokeDasharray="3 3" stroke="#374151" vertical={false} />
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
    <Card className="bg-card border-border/50">
      <CardHeader className="flex flex-row items-center justify-between pb-2">
        <CardTitle className="text-base font-medium">{title}</CardTitle>
        {showPeriodSelector && (
          <Select value={period} onValueChange={onPeriodChange}>
            <SelectTrigger className="w-28 bg-card border-border/50">
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
