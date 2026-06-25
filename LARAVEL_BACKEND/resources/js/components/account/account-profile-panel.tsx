"use client"

import { useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { getAccountProfile, updateAccountPassword, updateAccountProfile } from "@/lib/api-actions"
import type { User } from "@/lib/mock-data"
import { useToast } from "@/hooks/use-toast"

function persistAuthUser(user: User) {
  const storage = localStorage.getItem("auth_token") ? localStorage : sessionStorage
  storage.setItem("auth_user", JSON.stringify(user))
}

export function AccountProfilePanel({ title = "My account" }: { title?: string }) {
  const { toast } = useToast()
  const [loading, setLoading] = useState(true)
  const [profileSaving, setProfileSaving] = useState(false)
  const [passwordSaving, setPasswordSaving] = useState(false)
  const [name, setName] = useState("")
  const [email, setEmail] = useState("")
  const [phone, setPhone] = useState("")
  const [role, setRole] = useState("")
  const [currentPassword, setCurrentPassword] = useState("")
  const [newPassword, setNewPassword] = useState("")
  const [confirmPassword, setConfirmPassword] = useState("")

  useEffect(() => {
    let cancelled = false
    getAccountProfile()
      .then((user) => {
        if (cancelled) return
        setName(user.name)
        setEmail(user.email)
        setPhone(user.phone ?? "")
        setRole(user.role)
      })
      .catch(() => {
        if (!cancelled) {
          toast({ title: "Failed to load profile", variant: "destructive" })
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [toast])

  const handleProfileSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setProfileSaving(true)
    try {
      const res = await updateAccountProfile({ name, email, phone: phone || undefined })
      if (res.success && res.user) {
        persistAuthUser(res.user)
        toast({ title: res.message ?? "Profile saved" })
      } else {
        toast({ title: res.message ?? "Failed to save profile", variant: "destructive" })
      }
    } catch {
      toast({ title: "Failed to save profile", variant: "destructive" })
    } finally {
      setProfileSaving(false)
    }
  }

  const handlePasswordSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setPasswordSaving(true)
    try {
      const res = await updateAccountPassword({
        currentPassword,
        password: newPassword,
        confirmPassword,
      })
      if (res.success) {
        setCurrentPassword("")
        setNewPassword("")
        setConfirmPassword("")
        toast({ title: res.message ?? "Password updated" })
      } else {
        toast({ title: res.message ?? "Failed to update password", variant: "destructive" })
      }
    } catch {
      toast({ title: "Failed to update password", variant: "destructive" })
    } finally {
      setPasswordSaving(false)
    }
  }

  if (loading) {
    return (
      <div className="flex justify-center py-16">
        <span className="h-7 w-7 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">{title}</h1>
        <p className="text-muted-foreground">Update your personal name, email, and password.</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Profile</CardTitle>
          <CardDescription>
            {role === "admin"
              ? "Your super admin account details."
              : "Your login details for this account."}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form className="space-y-5" onSubmit={handleProfileSubmit}>
            <FieldGroup>
              <Field>
                <FieldLabel htmlFor="account-name">Full name</FieldLabel>
                <Input
                  id="account-name"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  required
                />
              </Field>
              <Field>
                <FieldLabel htmlFor="account-email">Email</FieldLabel>
                <Input
                  id="account-email"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                />
              </Field>
              <Field>
                <FieldLabel htmlFor="account-phone">Phone</FieldLabel>
                <Input
                  id="account-phone"
                  type="tel"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="Optional"
                />
              </Field>
            </FieldGroup>
            <Button type="submit" disabled={profileSaving}>
              {profileSaving ? "Saving…" : "Save profile"}
            </Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Password</CardTitle>
          <CardDescription>Change the password you use to sign in.</CardDescription>
        </CardHeader>
        <CardContent>
          <form className="space-y-5" onSubmit={handlePasswordSubmit}>
            <FieldGroup>
              <Field>
                <FieldLabel htmlFor="current-password">Current password</FieldLabel>
                <Input
                  id="current-password"
                  type="password"
                  value={currentPassword}
                  onChange={(e) => setCurrentPassword(e.target.value)}
                  required
                  autoComplete="current-password"
                />
              </Field>
              <Field>
                <FieldLabel htmlFor="new-password">New password</FieldLabel>
                <Input
                  id="new-password"
                  type="password"
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  required
                  autoComplete="new-password"
                />
              </Field>
              <Field>
                <FieldLabel htmlFor="confirm-password">Confirm new password</FieldLabel>
                <Input
                  id="confirm-password"
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  required
                  autoComplete="new-password"
                />
              </Field>
            </FieldGroup>
            <Button type="submit" disabled={passwordSaving}>
              {passwordSaving ? "Updating…" : "Update password"}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
