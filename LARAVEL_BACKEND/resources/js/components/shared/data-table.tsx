'use client'

import { useState } from 'react'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { ChevronLeft, ChevronRight, Search, SlidersHorizontal } from 'lucide-react'
import { cn } from '@/lib/utils'

export interface Column<T> {
  key: string
  header: string
  cell: (item: T) => React.ReactNode
  sortable?: boolean
  className?: string
}

export interface Filter {
  key: string
  label: string
  options: { value: string; label: string }[]
}

interface DataTableProps<T> {
  data: T[] | undefined
  columns: Column<T>[]
  isLoading?: boolean
  error?: Error | null
  searchPlaceholder?: string
  onSearch?: (query: string) => void
  filters?: Filter[]
  onFilterChange?: (key: string, value: string) => void
  filterValues?: Record<string, string>
  pagination?: {
    page: number
    totalPages: number
    onPageChange: (page: number) => void
  }
  emptyMessage?: string
  emptyDescription?: string
  view?: 'list' | 'grid'
  gridCell?: (item: T) => React.ReactNode
  toolbarExtra?: React.ReactNode
  onRowClick?: (item: T) => void
  tableClassName?: string
  framed?: boolean
}

export function DataTable<T extends { id: string }>({
  data,
  columns,
  isLoading,
  error,
  searchPlaceholder = 'Search...',
  onSearch,
  filters,
  onFilterChange,
  filterValues = {},
  pagination,
  emptyMessage = 'No data found',
  emptyDescription = 'Try adjusting your search or filters',
  view = 'list',
  gridCell,
  toolbarExtra,
  onRowClick,
  tableClassName,
  framed = true,
}: DataTableProps<T>) {
  const [searchQuery, setSearchQuery] = useState('')

  const handleSearch = (value: string) => {
    setSearchQuery(value)
    onSearch?.(value)
  }

  // Loading State
  if (isLoading) {
    return (
      <div className="space-y-4">
        {/* Search and Filters Skeleton */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <Skeleton className="h-10 w-full sm:w-64" />
          <div className="flex gap-2">
            <Skeleton className="h-10 w-32" />
            <Skeleton className="h-10 w-32" />
          </div>
        </div>
        
        {/* Table Skeleton */}
        <div className="overflow-hidden rounded-xl border border-border/60 bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow className="border-border/60 bg-muted/30 hover:bg-muted/30">
                {columns.map((column) => (
                  <TableHead key={column.key} className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    <Skeleton className="h-4 w-20" />
                  </TableHead>
                ))}
              </TableRow>
            </TableHeader>
            <TableBody>
              {[...Array(5)].map((_, i) => (
                <TableRow key={i} className="border-border/50">
                  {columns.map((column) => (
                    <TableCell key={column.key}>
                      <Skeleton className="h-4 w-full" />
                    </TableCell>
                  ))}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      </div>
    )
  }

  // Error State
  if (error) {
    return (
      <div className="flex flex-col items-center justify-center rounded-lg border border-destructive/50 bg-destructive/10 py-16">
        <div className="text-destructive text-lg font-medium">Error loading data</div>
        <p className="text-muted-foreground mt-1 text-sm">{error.message}</p>
        <Button
          variant="outline"
          className="mt-4"
          onClick={() => window.location.reload()}
        >
          Try Again
        </Button>
      </div>
    )
  }

  // Empty State
  if (!data || data.length === 0) {
    return (
      <div className="space-y-4">
        {/* Search and Filters */}
        {(onSearch || filters) && (
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            {onSearch && (
              <div className="relative w-full sm:w-64">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  placeholder={searchPlaceholder}
                  value={searchQuery}
                  onChange={(e) => handleSearch(e.target.value)}
                  className="pl-9 bg-card border-border/50"
                />
              </div>
            )}
            {filters && (
              <div className="flex flex-wrap items-center gap-2">
                {filters.map((filter) => (
                  <Select
                    key={filter.key}
                    value={filterValues[filter.key] || 'all'}
                    onValueChange={(value) => onFilterChange?.(filter.key, value)}
                  >
                    <SelectTrigger className="w-32 bg-card border-border/50">
                      <SelectValue placeholder={filter.label} />
                    </SelectTrigger>
                    <SelectContent>
                      {filter.options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                          {option.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                ))}
                {toolbarExtra}
              </div>
            )}
            {!filters && toolbarExtra}
          </div>
        )}

        {/* Empty State */}
        <div className="flex flex-col items-center justify-center rounded-lg border border-border/50 bg-card py-16">
          <SlidersHorizontal className="h-12 w-12 text-muted-foreground/50" />
          <div className="mt-4 text-lg font-medium text-foreground">{emptyMessage}</div>
          <p className="text-muted-foreground mt-1 text-sm">{emptyDescription}</p>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {/* Search and Filters */}
      {(onSearch || filters) && (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          {onSearch && (
            <div className="relative w-full sm:w-64">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder={searchPlaceholder}
                value={searchQuery}
                onChange={(e) => handleSearch(e.target.value)}
                className="pl-9 bg-card border-border/50"
              />
            </div>
          )}
          {filters && (
            <div className="flex flex-wrap items-center gap-2">
              {filters.map((filter) => (
                <Select
                  key={filter.key}
                  value={filterValues[filter.key] || 'all'}
                  onValueChange={(value) => onFilterChange?.(filter.key, value)}
                >
                  <SelectTrigger className="w-32 bg-card border-border/50">
                    <SelectValue placeholder={filter.label} />
                  </SelectTrigger>
                  <SelectContent>
                    {filter.options.map((option) => (
                      <SelectItem key={option.value} value={option.value}>
                        {option.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              ))}
              {toolbarExtra}
            </div>
          )}
          {!filters && toolbarExtra}
        </div>
      )}

      {view === 'grid' && gridCell ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {data.map((item) => (
            <div key={item.id}>{gridCell(item)}</div>
          ))}
        </div>
      ) : (
      <>
      <div className="space-y-3 md:hidden">
        {data.map((item) => {
          const actionCol = columns.find((column) => column.key === 'actions')
          const primaryCol = columns.find((column) => column.key !== 'actions') ?? columns[0]
          const detailCols = columns.filter((column) => column !== primaryCol && column !== actionCol)
          return (
            <div
              key={item.id}
              className={cn(
                'min-w-0 rounded-xl border border-border/50 bg-card p-3 shadow-sm',
                onRowClick && 'cursor-pointer'
              )}
              onClick={onRowClick ? () => onRowClick(item) : undefined}
            >
              {primaryCol ? <div className="min-w-0">{primaryCol.cell(item)}</div> : null}
              {detailCols.length > 0 ? (
                <div className="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 border-t border-border/40 pt-3">
                  {detailCols.map((column) => (
                    <div key={column.key} className="min-w-0">
                      {column.header ? (
                        <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                          {column.header}
                        </p>
                      ) : null}
                      <div className="mt-0.5 text-sm">{column.cell(item)}</div>
                    </div>
                  ))}
                </div>
              ) : null}
              {actionCol ? (
                <div
                  className="mt-3 flex flex-wrap justify-end gap-2"
                  onClick={(event) => event.stopPropagation()}
                >
                  {actionCol.cell(item)}
                </div>
              ) : null}
            </div>
          )
        })}
      </div>
      <div className={cn('hidden md:block', framed ? 'overflow-hidden rounded-2xl border border-border/50 bg-card/80 shadow-sm' : 'overflow-hidden rounded-xl border border-border/40')}>
        <Table className={tableClassName}>
          <TableHeader>
            <TableRow className="border-border/50 bg-muted/20 hover:bg-muted/20">
              {columns.map((column) => (
                <TableHead
                  key={column.key}
                  className={`px-4 text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground ${column.className ?? ''}`}
                >
                  {column.header}
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {data.map((item) => (
              <TableRow
                key={item.id}
                className={`border-border/40 hover:bg-muted/20${onRowClick ? ' cursor-pointer' : ''}`}
                onClick={onRowClick ? () => onRowClick(item) : undefined}
              >
                {columns.map((column) => (
                  <TableCell
                    key={column.key}
                    className={`px-4 py-3.5 ${column.className ?? ''}`}
                    onClick={column.key === 'actions' ? (e) => e.stopPropagation() : undefined}
                  >
                    {column.cell(item)}
                  </TableCell>
                ))}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
      </>
      )}

      {/* Pagination */}
      {pagination && pagination.totalPages > 1 && (
        <div className="flex items-center justify-between gap-2">
          <p className="text-sm text-muted-foreground">
            Page {pagination.page} of {pagination.totalPages}
          </p>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => pagination.onPageChange(pagination.page - 1)}
              disabled={pagination.page <= 1}
              className="border-border/50"
            >
              <ChevronLeft className="h-4 w-4 sm:mr-1" />
              <span className="hidden sm:inline">Previous</span>
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => pagination.onPageChange(pagination.page + 1)}
              disabled={pagination.page >= pagination.totalPages}
              className="border-border/50"
            >
              <span className="hidden sm:inline">Next</span>
              <ChevronRight className="h-4 w-4 sm:ml-1" />
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
