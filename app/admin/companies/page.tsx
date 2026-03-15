"use client"

import { useState, useCallback } from "react"
import { useRouter } from "next/navigation"
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
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogDescription,
} from "@/components/ui/dialog"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Search, MoreVertical, Building2, TrendingUp, UserPlus, AlertCircle, LogIn, Pencil } from "lucide-react"
import { useAdminCompanies } from "@/lib/api-hooks"
import {
  getAdminCompany,
  updateAdminCompany,
  updateCompanyStatus,
  adminImpersonateCompany,
  type UpdateAdminCompanyData,
} from "@/lib/api-actions"
import type { Company } from "@/lib/mock-data"

const PLAN_OPTIONS: Company["plan"][] = ["starter", "professional", "enterprise"]
const STATUS_OPTIONS: Company["status"][] = ["active", "suspended", "pending"]

export default function AdminCompaniesPage() {
  const router = useRouter()
  const [searchQuery, setSearchQuery] = useState("")
  const [statusFilter, setStatusFilter] = useState("all")
  const [editingCompany, setEditingCompany] = useState<Company | null>(null)
  const [editForm, setEditForm] = useState<UpdateAdminCompanyData & { name: string; email: string; phone: string }>({
    name: "",
    email: "",
    phone: "",
    plan: "starter",
    status: "active",
  })
  const [editLoading, setEditLoading] = useState(false)
  const [editError, setEditError] = useState<string | null>(null)
  const [suspendTarget, setSuspendTarget] = useState<Company | null>(null)
  const [impersonateLoading, setImpersonateLoading] = useState<string | null>(null)
  const { data: companies, error, isLoading, mutate } = useAdminCompanies({
    search: searchQuery || undefined,
    status: statusFilter !== "all" ? statusFilter : undefined,
  })

  if (isLoading && !companies) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Companies</h1>
          <p className="text-muted-foreground">Manage all registered companies on the platform</p>
        </div>
        <Card>
          <CardContent className="p-8">
            <div className="flex items-center justify-center gap-2 text-muted-foreground">
              <span className="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
              Loading companies...
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
          <h1 className="text-2xl font-bold text-foreground">Companies</h1>
          <p className="text-muted-foreground">Manage all registered companies on the platform</p>
        </div>
        <Card className="border-destructive/50">
          <CardContent className="p-6">
            <p className="text-destructive">Failed to load companies. Please try again.</p>
            <Button variant="outline" className="mt-2" onClick={() => mutate()}>Retry</Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  const list = companies ?? []

  const openEditDialog = useCallback(async (company: Company) => {
    setEditingCompany(company)
    setEditForm({
      name: company.name,
      email: company.email,
      phone: company.phone ?? "",
      plan: company.plan,
      status: company.status,
    })
    setEditError(null)
    const res = await getAdminCompany(company.id)
    if (res.success && res.company) {
      setEditForm({
        name: res.company.name,
        email: res.company.email,
        phone: res.company.phone ?? "",
        plan: res.company.plan,
        status: res.company.status,
      })
    }
  }, [])

  const saveEdit = useCallback(async () => {
    if (!editingCompany) return
    setEditLoading(true)
    setEditError(null)
    const res = await updateAdminCompany(editingCompany.id, {
      name: editForm.name,
      email: editForm.email,
      phone: editForm.phone || undefined,
      plan: editForm.plan,
      status: editForm.status,
    })
    setEditLoading(false)
    if (res.success) {
      mutate()
      setEditingCompany(null)
    } else {
      setEditError(res.message ?? "Failed to update company")
    }
  }, [editingCompany, editForm, mutate])

  const confirmSuspend = useCallback(async () => {
    if (!suspendTarget) return
    await updateCompanyStatus(suspendTarget.id, "suspended")
    mutate()
    setSuspendTarget(null)
  }, [suspendTarget, mutate])

  const handleImpersonateCompany = useCallback(
    async (company: Company) => {
      setImpersonateLoading(company.id)
      const res = await adminImpersonateCompany(company.id)
      setImpersonateLoading(null)
      if (res.success && res.token && res.user) {
        localStorage.removeItem("auth_token")
        localStorage.removeItem("auth_user")
        sessionStorage.setItem("auth_token", res.token)
        sessionStorage.setItem("auth_user", JSON.stringify(res.user))
        router.push("/dashboard")
      }
    },
    [router]
  )

  const stats = [
    { name: "Total Companies", value: list.length.toLocaleString(), icon: Building2 },
    { name: "Active This Month", value: "—", icon: TrendingUp },
    { name: "New This Week", value: "—", icon: UserPlus },
    { name: "Churned", value: "—", icon: AlertCircle },
  ]

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Companies</h1>
        <p className="text-muted-foreground">Manage all registered companies on the platform</p>
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
          <CardTitle>All Companies</CardTitle>
          <div className="flex items-center gap-2">
            <select
              className="rounded-md border border-input bg-background px-3 py-2 text-sm"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
            >
              <option value="all">All Status</option>
              <option value="active">Active</option>
              <option value="suspended">Suspended</option>
              <option value="pending">Pending</option>
            </select>
            <div className="relative w-64">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Search companies..."
                className="pl-10"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
              />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {list.length === 0 ? (
            <div className="py-12 text-center text-muted-foreground">No companies found.</div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Company</TableHead>
                  <TableHead>Plan</TableHead>
                  <TableHead>Chats</TableHead>
                  <TableHead>Orders</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Joined</TableHead>
                  <TableHead></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {list.map((company: Company) => (
                  <TableRow key={company.id}>
                    <TableCell>
                      <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-sm font-medium text-primary">
                          {company.name.charAt(0)}
                        </div>
                        <div>
                          <div className="font-medium text-foreground">{company.name}</div>
                          <div className="text-sm text-muted-foreground">{company.email}</div>
                        </div>
                      </div>
                    </TableCell>
                    <TableCell>
                      <Badge variant={company.plan === "enterprise" ? "default" : "secondary"}>{company.plan}</Badge>
                    </TableCell>
                    <TableCell className="text-foreground">{company.totalChats.toLocaleString()}</TableCell>
                    <TableCell className="text-foreground">{company.totalOrders.toLocaleString()}</TableCell>
                    <TableCell>
                      <Badge variant={company.status === "active" ? "default" : company.status === "pending" ? "secondary" : "destructive"}>
                        {company.status}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-muted-foreground">{new Date(company.createdAt).toLocaleDateString()}</TableCell>
                    <TableCell>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon">
                            <MoreVertical className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem onClick={() => openEditDialog(company)}>
                            <Pencil className="mr-2 h-4 w-4" />
                            Edit Company
                          </DropdownMenuItem>
                          <DropdownMenuItem
                            onClick={() => handleImpersonateCompany(company)}
                            disabled={!!impersonateLoading}
                          >
                            {impersonateLoading === company.id ? (
                              <span className="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent inline-block" />
                            ) : (
                              <LogIn className="mr-2 h-4 w-4" />
                            )}
                            Login as company
                          </DropdownMenuItem>
                          <DropdownMenuItem
                            className="text-destructive"
                            onClick={() => setSuspendTarget(company)}
                            disabled={company.status === "suspended"}
                          >
                            Suspend
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

      <Dialog open={!!editingCompany} onOpenChange={(open) => !open && setEditingCompany(null)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Edit Company</DialogTitle>
            <DialogDescription>Update company details. Changes take effect immediately.</DialogDescription>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            {editError && (
              <p className="rounded-md bg-destructive/10 p-2 text-sm text-destructive">{editError}</p>
            )}
            <div className="grid gap-2">
              <Label htmlFor="edit-name">Name</Label>
              <Input
                id="edit-name"
                value={editForm.name}
                onChange={(e) => setEditForm((f) => ({ ...f, name: e.target.value }))}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="edit-email">Email</Label>
              <Input
                id="edit-email"
                type="email"
                value={editForm.email}
                onChange={(e) => setEditForm((f) => ({ ...f, email: e.target.value }))}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="edit-phone">Phone</Label>
              <Input
                id="edit-phone"
                value={editForm.phone}
                onChange={(e) => setEditForm((f) => ({ ...f, phone: e.target.value }))}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="edit-plan">Plan</Label>
              <select
                id="edit-plan"
                className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={editForm.plan}
                onChange={(e) => setEditForm((f) => ({ ...f, plan: e.target.value as Company["plan"] }))}
              >
                {PLAN_OPTIONS.map((p) => (
                  <option key={p} value={p}>
                    {p}
                  </option>
                ))}
              </select>
            </div>
            <div className="grid gap-2">
              <Label htmlFor="edit-status">Status</Label>
              <select
                id="edit-status"
                className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={editForm.status}
                onChange={(e) => setEditForm((f) => ({ ...f, status: e.target.value as Company["status"] }))}
              >
                {STATUS_OPTIONS.map((s) => (
                  <option key={s} value={s}>
                    {s}
                  </option>
                ))}
              </select>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setEditingCompany(null)}>
              Cancel
            </Button>
            <Button onClick={saveEdit} disabled={editLoading}>
              {editLoading ? (
                <span className="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />
              ) : (
                "Save changes"
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!suspendTarget} onOpenChange={(open) => !open && setSuspendTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Suspend company?</AlertDialogTitle>
            <AlertDialogDescription>
              {suspendTarget
                ? `"${suspendTarget.name}" will be suspended and may lose access to the platform. You can reactivate from Edit Company.`
                : ""}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={confirmSuspend} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
              Suspend
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
