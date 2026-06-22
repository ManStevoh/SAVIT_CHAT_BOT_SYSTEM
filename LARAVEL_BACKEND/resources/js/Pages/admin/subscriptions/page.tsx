"use client"

import { useState, useMemo } from "react"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Label } from "@/components/ui/label"
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
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Search, MoreVertical, CreditCard, TrendingUp, AlertCircle, CheckCircle } from "lucide-react"
import { useAdminSubscriptions, useAdminPlans } from "@/lib/api-hooks"
import { adminUpdateSubscription } from "@/lib/api-actions"
import type { Subscription } from "@/lib/mock-data"
import { useToast } from "@/hooks/use-toast"

export default function AdminSubscriptionsPage() {
  const [searchQuery, setSearchQuery] = useState("")
  const [planFilter, setPlanFilter] = useState("all")
  const { data: subscriptions, error, isLoading, mutate } = useAdminSubscriptions({
    plan: planFilter !== "all" ? planFilter : undefined,
  })
  const { data: plans } = useAdminPlans()
  const { toast } = useToast()

  const [detailsSub, setDetailsSub] = useState<Subscription | null>(null)
  const [changeSub, setChangeSub] = useState<Subscription | null>(null)
  const [changePlanSlug, setChangePlanSlug] = useState("")
  const [changeStatus, setChangeStatus] = useState<"active" | "trial">("active")
  const [changeBilling, setChangeBilling] = useState<"monthly" | "yearly">("monthly")
  const [savingPlan, setSavingPlan] = useState(false)

  const [cancelSub, setCancelSub] = useState<Subscription | null>(null)
  const [cancelling, setCancelling] = useState(false)

  const planSlugs = useMemo(() => {
    const slugs = (plans ?? []).map((p) => p.slug).filter(Boolean)
    if (slugs.length) return slugs as string[]
    return ["starter", "professional", "enterprise"]
  }, [plans])

  const openChangePlan = (sub: Subscription) => {
    setChangeSub(sub)
    setChangePlanSlug(sub.plan)
    setChangeStatus(sub.status === "trial" ? "trial" : "active")
    setChangeBilling(sub.billingCycle === "yearly" ? "yearly" : "monthly")
  }

  const handleSaveChangePlan = async () => {
    if (!changeSub || !changePlanSlug) return
    setSavingPlan(true)
    try {
      const res = await adminUpdateSubscription(changeSub.id, {
        status: changeStatus,
        plan: changePlanSlug,
        billingCycle: changeStatus === "active" ? changeBilling : undefined,
      })
      if (res.success) {
        toast({ title: res.message ?? "Subscription updated" })
        setChangeSub(null)
        await mutate()
      } else {
        toast({ title: res.message ?? "Update failed", variant: "destructive" })
      }
    } finally {
      setSavingPlan(false)
    }
  }

  const handleConfirmCancel = async () => {
    if (!cancelSub) return
    setCancelling(true)
    try {
      const res = await adminUpdateSubscription(cancelSub.id, { status: "cancelled" })
      if (res.success) {
        toast({ title: res.message ?? "Subscription cancelled" })
        setCancelSub(null)
        await mutate()
      } else {
        toast({ title: res.message ?? "Cancel failed", variant: "destructive" })
      }
    } finally {
      setCancelling(false)
    }
  }

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
            <Button variant="outline" className="mt-2" onClick={() => mutate()}>
              Retry
            </Button>
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
              {planSlugs.map((slug) => (
                <option key={slug} value={slug}>
                  {slug.charAt(0).toUpperCase() + slug.slice(1)}
                </option>
              ))}
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
                      <Badge
                        variant={
                          sub.status === "active"
                            ? "default"
                            : sub.status === "trial"
                              ? "secondary"
                              : "outline"
                        }
                      >
                        {sub.status}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-muted-foreground">
                      {new Date(sub.endDate).toLocaleDateString()}
                    </TableCell>
                    <TableCell>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button type="button" variant="ghost" size="icon" aria-label="Actions">
                            <MoreVertical className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem onSelect={() => setDetailsSub(sub)}>View Details</DropdownMenuItem>
                          <DropdownMenuItem onSelect={() => openChangePlan(sub)}>Change Plan</DropdownMenuItem>
                          <DropdownMenuItem
                            className="text-destructive focus:text-destructive"
                            onSelect={() => setCancelSub(sub)}
                          >
                            Cancel
                          </DropdownMenuItem>
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

      <Dialog open={!!detailsSub} onOpenChange={(o) => !o && setDetailsSub(null)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Subscription details</DialogTitle>
          </DialogHeader>
          {detailsSub && (
            <div className="grid gap-3 text-sm">
              <div className="flex justify-between gap-4">
                <span className="text-muted-foreground">Company</span>
                <span className="text-right font-medium">{detailsSub.companyName}</span>
              </div>
              <div className="flex justify-between gap-4">
                <span className="text-muted-foreground">Company ID</span>
                <span className="font-mono text-xs">{detailsSub.companyId}</span>
              </div>
              <div className="flex justify-between gap-4">
                <span className="text-muted-foreground">Plan</span>
                <span className="font-medium">{detailsSub.plan}</span>
              </div>
              <div className="flex justify-between gap-4">
                <span className="text-muted-foreground">Status</span>
                <span className="font-medium">{detailsSub.status}</span>
              </div>
              <div className="flex justify-between gap-4">
                <span className="text-muted-foreground">Amount</span>
                <span>{formatAmount(detailsSub)}</span>
              </div>
              <div className="flex justify-between gap-4">
                <span className="text-muted-foreground">Billing</span>
                <span>{detailsSub.billingCycle}</span>
              </div>
              <div className="flex justify-between gap-4">
                <span className="text-muted-foreground">Start</span>
                <span>{new Date(detailsSub.startDate).toLocaleDateString()}</span>
              </div>
              <div className="flex justify-between gap-4">
                <span className="text-muted-foreground">Renewal / end</span>
                <span>{new Date(detailsSub.endDate).toLocaleDateString()}</span>
              </div>
            </div>
          )}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDetailsSub(null)}>
              Close
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={!!changeSub} onOpenChange={(o) => !o && setChangeSub(null)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Assign plan</DialogTitle>
            <p className="text-sm text-muted-foreground">
              Manually set the plan and billing state for {changeSub?.companyName ?? "this company"}. This clears any
              linked Stripe subscription for this record so billing stays consistent.
            </p>
          </DialogHeader>
          {changeSub && (
            <div className="grid gap-4 py-2">
              <div className="grid gap-2">
                <Label htmlFor="admin-sub-plan">Plan</Label>
                <Select value={changePlanSlug} onValueChange={setChangePlanSlug}>
                  <SelectTrigger id="admin-sub-plan" className="w-full">
                    <SelectValue placeholder="Select plan" />
                  </SelectTrigger>
                  <SelectContent>
                    {(plans ?? []).length > 0
                      ? (plans ?? []).map((p) => (
                          <SelectItem key={p.id} value={p.slug}>
                            {p.name} ({p.slug})
                          </SelectItem>
                        ))
                      : planSlugs.map((slug) => (
                          <SelectItem key={slug} value={slug}>
                            {slug}
                          </SelectItem>
                        ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="grid gap-2">
                <Label>Status</Label>
                <Select
                  value={changeStatus}
                  onValueChange={(v) => setChangeStatus(v as "active" | "trial")}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="active">Active (paid / comped)</SelectItem>
                    <SelectItem value="trial">Trial</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              {changeStatus === "active" && (
                <div className="grid gap-2">
                  <Label>Billing period</Label>
                  <Select
                    value={changeBilling}
                    onValueChange={(v) => setChangeBilling(v as "monthly" | "yearly")}
                  >
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="monthly">Monthly</SelectItem>
                      <SelectItem value="yearly">Yearly</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              )}
            </div>
          )}
          <DialogFooter className="gap-2 sm:gap-0">
            <Button type="button" variant="outline" onClick={() => setChangeSub(null)}>
              Close
            </Button>
            <Button type="button" disabled={savingPlan || !changePlanSlug} onClick={() => void handleSaveChangePlan()}>
              {savingPlan ? "Saving…" : "Save"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!cancelSub} onOpenChange={(o) => !o && !cancelling && setCancelSub(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Cancel subscription?</AlertDialogTitle>
            <AlertDialogDescription>
              This marks the subscription as cancelled for {cancelSub?.companyName ?? "this company"}. They may lose
              access when the current period ends, depending on your platform rules.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={cancelling}>Back</AlertDialogCancel>
            <Button
              type="button"
              variant="destructive"
              disabled={cancelling}
              onClick={() => void handleConfirmCancel()}
            >
              {cancelling ? "Cancelling…" : "Cancel subscription"}
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
