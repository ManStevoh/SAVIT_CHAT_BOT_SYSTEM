'use client'

import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { TrendingUp, TrendingDown, LucideIcon } from 'lucide-react'
import { cn } from '@/lib/utils'

interface StatsCardProps {
  title: string
  value: string | number
  change?: number
  changeLabel?: string
  icon?: LucideIcon
  isLoading?: boolean
  formatter?: (value: number) => string
}

export function StatsCard({
  title,
  value,
  change,
  changeLabel = 'vs last period',
  icon: Icon,
  isLoading,
  formatter,
}: StatsCardProps) {
  const formattedValue =
    typeof value === 'number' && formatter ? formatter(value) : value

  if (isLoading) {
    return (
      <Card className="border-border/60 bg-card shadow-sm">
        <CardContent className="p-5">
          <Skeleton className="mb-3 h-3 w-20" />
          <Skeleton className="h-8 w-28" />
          <Skeleton className="mt-2 h-3 w-16" />
        </CardContent>
      </Card>
    )
  }

  return (
    <Card className="border-border/60 bg-card shadow-sm transition-shadow hover:shadow-premium">
      <CardContent className="p-5">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0 space-y-1">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
              {title}
            </p>
            <p className="text-2xl font-semibold tabular-nums tracking-tight text-foreground">
              {formattedValue}
            </p>
            {change !== undefined && (
              <div className="flex items-center gap-1 pt-0.5">
                {change >= 0 ? (
                  <TrendingUp className="h-3 w-3 text-emerald-600 dark:text-emerald-400" />
                ) : (
                  <TrendingDown className="h-3 w-3 text-red-500" />
                )}
                <span
                  className={cn(
                    'text-xs font-medium tabular-nums',
                    change >= 0
                      ? 'text-emerald-600 dark:text-emerald-400'
                      : 'text-red-500'
                  )}
                >
                  {change >= 0 ? '+' : ''}
                  {change.toFixed(1)}%
                </span>
                <span className="text-xs text-muted-foreground">{changeLabel}</span>
              </div>
            )}
          </div>
          {Icon && (
            <Icon className="h-4 w-4 shrink-0 text-muted-foreground/60" strokeWidth={1.5} />
          )}
        </div>
      </CardContent>
    </Card>
  )
}

interface StatsGridProps {
  children: React.ReactNode
  columns?: 2 | 3 | 4
}

export function StatsGrid({ children, columns = 4 }: StatsGridProps) {
  const gridCols = {
    2: 'grid-cols-1 sm:grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
  }

  return <div className={cn('grid gap-4', gridCols[columns])}>{children}</div>
}
