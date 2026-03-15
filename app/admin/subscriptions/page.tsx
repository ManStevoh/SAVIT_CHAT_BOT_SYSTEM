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
import { Search, MoreVertical, CreditCard, TrendingUp, AlertCircle, CheckCircle } from "lucide-react"
// API: GET /api/admin/subscriptions — list subscriptions (useAdminSubscriptions)
import { useAdminSubscriptions } from "@/lib/api-hooks"
import type { Subscription } from "@/lib/mock-data"

export default function AdminSubscriptionsPage() {
  const [searchQuery, setSearchQuery] = useState("")
  const [planFilter, setPlanFilter] = useState("all")
  const { data: subscriptions, error, isLoading, mutate } = useAdminSubscriptions({
    plan: planFilter !== "all" ? planFilter : undefined,
  })

  if (isLoading && !subscriptions) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Subscriptions</h1>
          <p className="text-muted-foreground">Manage all subscriptions and billing</p>
        </div>
        <Card>
          <CardContent className="p-8">
            <div className="flex items-center justify-center gap-2 text-muted-foreground">
              <span className="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
              Loading subscriptions...
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
          <h1 className="text-2xl font-bold text-foreground">Subscriptions</h1>
          <p className="text-muted-foreground">Manage all subscriptions and billing</p>
        </div>
        <Card className="border-destructive/50">
          <CardContent className="p-6">
            <p className="text-destructive">Failed to load subscriptions. Please try again.</p>
            <Button variant="outline" className="mt-2" onClick={() => mutate()}>Retry</Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  const list = subscriptions ?? []
  const filtered = searchQuery
    ? list.filter((s) => s.companyName.toLowerCase().includes(searchQuery.toLowerCase()))
    : list

  const stats = [
    { name: "Total Subscriptions", value: list.length.toLocaleString(), icon: CreditCard },
    { name: "Active", value: list.filter((s) => s.status === "active").length.toString(), icon: CheckCircle },
    { name: "Trial", value: list.filter((s) => s.status === "trial").length.toString(), icon: TrendingUp },
    { name: "Past Due", value: "—", icon: AlertCircle },
  ]

  const formatAmount = (s: Subscription) =>
    s.amount === 0 ? "Trial" : `$${s.amount}/${s.billingCycle === "yearly" ? "yr" : "mo"}`

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Subscriptions</h1>
        <p className="text-muted-foreground">Manage all subscriptions and billing</p>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat) => (
          <Card key={stat.name}>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-muted-foreground">{stat.name}</p>
                  <p className="mt-1 text-2xl font-bold text-foreground">{stat.value}</p>
                </div>
                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                  <stat.icon className="h-6 w-6 text-primary" />
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>All Subscriptions</CardTitle>
          <div className="flex items-center gap-2">
            <select
              className="rounded-md border border-input bg-background px-3 py-2 text-sm"
              value={planFilter}
              onChange={(e) => setPlanFilter(e.target.value)}
            >
              <option value="all">All Plans</option>
              <option value="starter">Starter</option>
              <option value="professional">Professional</option>
              <option value="enterprise">Enterprise</option>
            </select>
            <div className="relative w-64">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Search subscriptions..."
                className="pl-10"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
              />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {filtered.length === 0 ? (
            <div className="py-12 text-center text-muted-foreground">No subscriptions found.</div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Company</TableHead>
                  <TableHead>Plan</TableHead>
                  <TableHead>Amount</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Renewal</TableHead>
                  <TableHead></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filtered.map((sub) => (
                  <TableRow key={sub.id}>
                    <TableCell className="font-medium text-foreground">{sub.companyName}</TableCell>
                    <TableCell>
                      <Badge variant={sub.plan === "enterprise" ? "default" : "secondary"}>{sub.plan}</Badge>
                    </TableCell>
                    <TableCell className="text-foreground">{formatAmount(sub)}</TableCell>
                    <TableCell>
                      <Badge variant={sub.status === "active" ? "default" : sub.status === "trial" ? "secondary" : "outline"}>{sub.status}</Badge>
                    </TableCell>
                    <TableCell className="text-muted-foreground">{new Date(sub.endDate).toLocaleDateString()}</TableCell>
                    <TableCell>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon">
                            <MoreVertical className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem>View Details</DropdownMenuItem>
                          <DropdownMenuItem>Change Plan</DropdownMenuItem>
                          <DropdownMenuItem className="text-destructive">Cancel</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
