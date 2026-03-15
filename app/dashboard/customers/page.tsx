"use client"

import { useState } from "react"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Search, MoreVertical, Users, UserPlus, UserCheck, TrendingUp } from "lucide-react"
// API: GET /api/company/customers — list customers (useCustomers in api-hooks)
import { useCustomers } from "@/lib/api-hooks"
import type { Customer } from "@/lib/mock-data"

function formatCurrency(value: number): string {
  // API may return amount in cents or whole units; adjust per backend
  return new Intl.NumberFormat("en-US", { style: "currency", currency: "USD", minimumFractionDigits: 0 }).format(value)
}
function formatDate(dateStr: string): string {
  try {
    return new Date(dateStr).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" })
  } catch {
    return dateStr
  }
}

// Derive display status from customer data (backend may provide status field later)
function getStatusBadgeVariant(customer: Customer): "default" | "secondary" | "outline" | "destructive" {
  if (customer.totalOrders >= 10) return "default" // vip
  if (customer.totalOrders >= 1) return "secondary" // active
  return "outline" // new
}
function getStatusLabel(customer: Customer): string {
  if (customer.totalOrders >= 10) return "vip"
  if (customer.totalOrders >= 1) return "active"
  return "new"
}

export default function CustomersPage() {
  const [searchQuery, setSearchQuery] = useState("")
  const [page, setPage] = useState(1)
  const limit = 10

  // API call: GET /api/company/customers?search=&page=1&limit=10
  const { data, error, isLoading, mutate } = useCustomers({ search: searchQuery || undefined, page, limit })

  // Loading state: show skeleton or spinner
  if (isLoading && !data) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Customers</h1>
          <p className="text-muted-foreground">Manage your customer relationships</p>
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
              Loading customers...
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  // Error state: API request failed
  if (error) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Customers</h1>
          <p className="text-muted-foreground">Manage your customer relationships</p>
        </div>
        <Card className="border-destructive/50">
          <CardContent className="p-6">
            <p className="text-destructive">Failed to load customers. Please try again.</p>
            <Button variant="outline" className="mt-2" onClick={() => mutate()}>
              Retry
            </Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  const customers = data?.customers ?? []
  const total = data?.total ?? 0
  const totalPages = data?.totalPages ?? 1

  // Stats: TODO replace with GET /api/company/customers/stats when available
  const stats = [
    { name: "Total Customers", value: total.toLocaleString(), icon: Users, change: "+12%" },
    { name: "New This Month", value: "—", icon: UserPlus, change: "—" },
    { name: "Active Customers", value: "—", icon: UserCheck, change: "—" },
    { name: "Avg. Orders/Customer", value: customers.length ? (customers.reduce((s, c) => s + c.totalOrders, 0) / customers.length).toFixed(1) : "0", icon: TrendingUp, change: "—" },
  ]

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Customers</h1>
        <p className="text-muted-foreground">Manage your customer relationships</p>
      </div>

      {/* Stats — API: optional GET /api/company/customers/stats */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat) => (
          <Card key={stat.name}>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-muted-foreground">{stat.name}</p>
                  <p className="mt-1 text-2xl font-bold text-foreground">{stat.value}</p>
                  <p className="text-sm text-primary">{stat.change} this month</p>
                </div>
                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                  <stat.icon className="h-6 w-6 text-primary" />
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Customers Table — data from useCustomers() */}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>All Customers</CardTitle>
          <div className="relative w-64">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Search customers..."
              className="pl-10"
              value={searchQuery}
              onChange={(e) => {
                setSearchQuery(e.target.value)
                setPage(1)
              }}
            />
          </div>
        </CardHeader>
        <CardContent>
          {customers.length === 0 ? (
            // Empty state: no customers or no results
            <div className="py-12 text-center text-muted-foreground">
              {searchQuery ? "No customers match your search." : "No customers yet."}
            </div>
          ) : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Customer</TableHead>
                    <TableHead>Phone</TableHead>
                    <TableHead>Orders</TableHead>
                    <TableHead>Total Spent</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Joined</TableHead>
                    <TableHead></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {customers.map((customer) => (
                    <TableRow key={customer.id}>
                      <TableCell>
                        <div className="flex items-center gap-3">
                          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
                            {customer.name.charAt(0)}
                          </div>
                          <div>
                            <div className="font-medium text-foreground">{customer.name}</div>
                            <div className="text-sm text-muted-foreground">{customer.email ?? "—"}</div>
                          </div>
                        </div>
                      </TableCell>
                      <TableCell className="text-muted-foreground">{customer.phone}</TableCell>
                      <TableCell className="text-foreground">{customer.totalOrders}</TableCell>
                      <TableCell className="font-medium text-foreground">{formatCurrency(customer.totalSpent)}</TableCell>
                      <TableCell>
                        <Badge variant={getStatusBadgeVariant(customer)}>{getStatusLabel(customer)}</Badge>
                      </TableCell>
                      <TableCell className="text-muted-foreground">{formatDate(customer.createdAt)}</TableCell>
                      <TableCell>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon">
                              <MoreVertical className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem>View Profile</DropdownMenuItem>
                            <DropdownMenuItem>View Orders</DropdownMenuItem>
                            <DropdownMenuItem>Send Message</DropdownMenuItem>
                            <DropdownMenuItem>Add Tag</DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
              {/* Pagination placeholder — API already returns page/totalPages */}
              {totalPages > 1 && (
                <div className="mt-4 flex items-center justify-between">
                  <p className="text-sm text-muted-foreground">
                    Page {page} of {totalPages} ({total} total)
                  </p>
                  <div className="flex gap-2">
                    <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                      Previous
                    </Button>
                    <Button variant="outline" size="sm" disabled={page >= totalPages} onClick={() => setPage((p) => p + 1)}>
                      Next
                    </Button>
                  </div>
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
