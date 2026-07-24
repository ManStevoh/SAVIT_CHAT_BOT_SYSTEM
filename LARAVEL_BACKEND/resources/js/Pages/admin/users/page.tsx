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
import { Search, MoreVertical, Users, UserCheck, UserPlus, Shield, LogIn, KeyRound } from "lucide-react"
import { useAdminUsers } from "@/lib/api-hooks"
import { adminResetUserPassword, updateUserStatus, adminImpersonateUser } from "@/lib/api-actions"
import { setAuthCookie } from "@/lib/auth-cookie"
import type { User } from "@/lib/mock-data"

function formatRelativeTime(iso: string): string {
  try {
    const d = new Date(iso)
    const now = new Date()
    const diffMs = now.getTime() - d.getTime()
    const diffMins = Math.floor(diffMs / 60000)
    if (diffMins < 1) return "Just now"
    if (diffMins < 60) return `${diffMins} min ago`
    const diffHours = Math.floor(diffMins / 60)
    if (diffHours < 24) return `${diffHours} hour ago`
    const diffDays = Math.floor(diffHours / 24)
    return `${diffDays} day${diffDays > 1 ? "s" : ""} ago`
  } catch {
    return iso
  }
}

export default function AdminUsersPage() {
  const router = useRouter()
  const [searchQuery, setSearchQuery] = useState("")
  const [roleFilter, setRoleFilter] = useState("all")
  const [resetPasswordUser, setResetPasswordUser] = useState<User | null>(null)
  const [newPassword, setNewPassword] = useState("")
  const [confirmPassword, setConfirmPassword] = useState("")
  const [resetLoading, setResetLoading] = useState(false)
  const [resetError, setResetError] = useState<string | null>(null)
  const [suspendTarget, setSuspendTarget] = useState<User | null>(null)
  const [impersonateLoading, setImpersonateLoading] = useState<string | null>(null)
  const { data: users, error, isLoading, mutate } = useAdminUsers({
    search: searchQuery || undefined,
    role: roleFilter !== "all" ? roleFilter : undefined,
  })

  const saveResetPassword = useCallback(async () => {
    if (!resetPasswordUser) return
    if (newPassword.length < 8) {
      setResetError("Password must be at least 8 characters")
      return
    }
    if (newPassword !== confirmPassword) {
      setResetError("Passwords do not match")
      return
    }
    setResetLoading(true)
    setResetError(null)
    const res = await adminResetUserPassword(resetPasswordUser.id, newPassword, confirmPassword)
    setResetLoading(false)
    if (res.success) {
      setResetPasswordUser(null)
      setNewPassword("")
      setConfirmPassword("")
    } else {
      setResetError(res.message ?? "Failed to reset password")
    }
  }, [resetPasswordUser, newPassword, confirmPassword])

  const confirmSuspend = useCallback(async () => {
    if (!suspendTarget) return
    await updateUserStatus(suspendTarget.id, "inactive")
    mutate()
    setSuspendTarget(null)
  }, [suspendTarget, mutate])

  const handleImpersonateUser = useCallback(
    async (user: User) => {
      if (user.role === "admin") return
      setImpersonateLoading(user.id)
      const res = await adminImpersonateUser(user.id)
      setImpersonateLoading(null)
      if (res.success && res.token && res.user) {
        localStorage.removeItem("auth_token")
        localStorage.removeItem("auth_user")
        sessionStorage.setItem("auth_token", res.token)
        sessionStorage.setItem("auth_user", JSON.stringify(res.user))
        setAuthCookie(res.user.role, false)
        router.push("/dashboard")
      }
    },
    [router]
  )

  if (isLoading && !users) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Users</h1>
          <p className="text-muted-foreground">Manage all users across the platform</p>
        </div>
        <Card>
          <CardContent className="p-8">
            <div className="flex items-center justify-center gap-2 text-muted-foreground">
              <span className="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
              Loading users...
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
          <h1 className="text-2xl font-bold text-foreground">Users</h1>
          <p className="text-muted-foreground">Manage all users across the platform</p>
        </div>
        <Card className="border-destructive/50">
          <CardContent className="p-6">
            <p className="text-destructive">Failed to load users. Please try again.</p>
            <Button variant="outline" className="mt-2" onClick={() => mutate()}>Retry</Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  const list = users ?? []

  const stats = [
    { name: "Total Users", value: list.length.toLocaleString(), icon: Users },
    { name: "Active Today", value: "—", icon: UserCheck },
    { name: "New This Week", value: "—", icon: UserPlus },
    { name: "Admins", value: list.filter((u) => u.role === "admin").length.toString(), icon: Shield },
  ]

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Users</h1>
        <p className="text-muted-foreground">Manage all users across the platform</p>
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
          <CardTitle>All Users</CardTitle>
          <div className="flex items-center gap-2">
            <select
              className="rounded-md border border-input bg-background px-3 py-2 text-sm"
              value={roleFilter}
              onChange={(e) => setRoleFilter(e.target.value)}
            >
              <option value="all">All Roles</option>
              <option value="admin">Admin</option>
              <option value="company_owner">Company Owner</option>
              <option value="company_user">Company User</option>
            </select>
            <div className="relative w-64">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Search users..."
                className="pl-10"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
              />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {list.length === 0 ? (
            <div className="py-12 text-center text-muted-foreground">No users found.</div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>User</TableHead>
                  <TableHead>Company</TableHead>
                  <TableHead>Role</TableHead>
                  <TableHead>Consent</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Last Active</TableHead>
                  <TableHead></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {list.map((user: User) => (
                  <TableRow key={user.id}>
                    <TableCell>
                      <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
                          {user.name.charAt(0)}
                        </div>
                        <div>
                          <div className="font-medium text-foreground">{user.name}</div>
                          <div className="text-sm text-muted-foreground">{user.email}</div>
                        </div>
                      </div>
                    </TableCell>
                    <TableCell className="text-foreground">{user.companyName ?? "—"}</TableCell>
                    <TableCell>
                      <Badge variant={user.role === "admin" ? "default" : "secondary"}>
                        {user.role.replace("_", " ")}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-xs text-muted-foreground space-y-1">
                      <div>
                        Terms:{" "}
                        {user.termsAcceptedAt ? (
                          <span className="text-foreground">Yes</span>
                        ) : (
                          <span>—</span>
                        )}
                      </div>
                      <div>
                        Marketing:{" "}
                        {user.marketingConsent ? (
                          <span className="text-foreground">Opted in</span>
                        ) : (
                          <span>No</span>
                        )}
                      </div>
                    </TableCell>
                    <TableCell>
                      <Badge variant={user.status === "active" ? "default" : "secondary"}>{user.status}</Badge>
                    </TableCell>
                    <TableCell className="text-muted-foreground">{formatRelativeTime(user.lastLogin)}</TableCell>
                    <TableCell>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon">
                            <MoreVertical className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem onClick={() => setResetPasswordUser(user)}>
                            <KeyRound className="mr-2 h-4 w-4" />
                            Reset Password
                          </DropdownMenuItem>
                          <DropdownMenuItem
                            onClick={() => handleImpersonateUser(user)}
                            disabled={!!impersonateLoading || user.role === "admin"}
                          >
                            {impersonateLoading === user.id ? (
                              <span className="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                            ) : (
                              <LogIn className="mr-2 h-4 w-4" />
                            )}
                            Login as user
                          </DropdownMenuItem>
                          <DropdownMenuItem
                            className="text-destructive"
                            onClick={() => setSuspendTarget(user)}
                            disabled={user.role === "admin" || user.status === "inactive"}
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

      <Dialog
        open={!!resetPasswordUser}
        onOpenChange={(open) => {
          if (!open) {
            setResetPasswordUser(null)
            setNewPassword("")
            setConfirmPassword("")
            setResetError(null)
          }
        }}
      >
        <DialogContent className="sm:max-w-md max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Reset password</DialogTitle>
            <DialogDescription>
              {resetPasswordUser
                ? `Set a new password for ${resetPasswordUser.name} (${resetPasswordUser.email}). They will need to use this to sign in.`
                : ""}
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            {resetError && (
              <p className="rounded-md bg-destructive/10 p-2 text-sm text-destructive">{resetError}</p>
            )}
            <div className="grid gap-2">
              <Label htmlFor="new-password">New password</Label>
              <Input
                id="new-password"
                type="password"
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                placeholder="Min 8 characters"
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="confirm-password">Confirm password</Label>
              <Input
                id="confirm-password"
                type="password"
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                placeholder="Confirm new password"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setResetPasswordUser(null)}>
              Cancel
            </Button>
            <Button onClick={saveResetPassword} disabled={resetLoading}>
              {resetLoading ? (
                <span className="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />
              ) : (
                "Update password"
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!suspendTarget} onOpenChange={(open) => !open && setSuspendTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Suspend user?</AlertDialogTitle>
            <AlertDialogDescription>
              {suspendTarget
                ? `"${suspendTarget.name}" will be set to inactive and will not be able to sign in until reactivated.`
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
