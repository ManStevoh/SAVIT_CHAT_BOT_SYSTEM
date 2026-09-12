"use client"

import { useState } from "react"
import { Copy, Plus, Users } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { FormModal } from "@/components/shared/modal"
import { useCompanyTeam, useSubscription } from "@/lib/api-hooks"
import { inviteTeamMember } from "@/lib/api-actions"
import { useSWRConfig } from "swr"
import { toast } from "sonner"
import { SettingSection } from "./shared"
import { UpgradePrompt } from "@/components/shared/upgrade-prompt"

export function TeamSection() {
  const { mutate } = useSWRConfig()
  const { data: teamMembers = [], isLoading } = useCompanyTeam()
  const { data: subscription } = useSubscription()
  const isStarter = (subscription?.plan ?? "free") === "free"

  const [dialogOpen, setDialogOpen] = useState(false)
  const [name, setName] = useState("")
  const [email, setEmail] = useState("")
  const [role, setRole] = useState<"agent" | "company_admin">("agent")
  const [submitting, setSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [tempPassword, setTempPassword] = useState<string | null>(null)

  const openInvite = () => {
    setName("")
    setEmail("")
    setRole("agent")
    setFormError(null)
    setTempPassword(null)
    setDialogOpen(true)
  }

  const submitInvite = async () => {
    if (!name.trim() || !email.trim()) {
      setFormError("Name and email are required.")
      return
    }
    setSubmitting(true)
    setFormError(null)
    const res = await inviteTeamMember({ name: name.trim(), email: email.trim(), role })
    setSubmitting(false)
    if (!res.success) {
      setFormError(res.message ?? "Couldn't invite this person.")
      return
    }
    setTempPassword(res.temporaryPassword ?? null)
    toast.success(res.message ?? "Team member added.")
    mutate("company-team")
  }

  const copyPassword = async () => {
    if (!tempPassword) return
    try {
      await navigator.clipboard.writeText(tempPassword)
      toast.success("Temporary password copied")
    } catch {
      toast.error("Couldn't copy — select it manually.")
    }
  }

  return (
    <div className="space-y-4">
      {isStarter && (
        <UpgradePrompt
          title="Starter includes 1 seat — grow the team on Growth"
          description="Your Starter workspace covers one team member. Growth unlocks 3 seats with roles, so staff can share chats, orders, and the inbox."
          highlights={["3 team members", "Roles & shared inbox", "WhatsApp number connection"]}
          compact
        />
      )}

      <SettingSection
        title="Team"
        description={isStarter ? "Just you on Starter — invite seats unlock on Growth." : "Everyone with access to this workspace."}
        badge={
          <Badge variant="outline" className="gap-1 text-[11px]">
            <Users className="h-3 w-3" />
            {teamMembers.length} {teamMembers.length === 1 ? "member" : "members"}
          </Badge>
        }
        actions={
          <Button size="sm" onClick={openInvite}>
            <Plus className="mr-1.5 h-3.5 w-3.5" />
            Invite
          </Button>
        }
      >
        {isLoading ? (
          <p className="py-6 text-center text-sm text-muted-foreground">Loading team…</p>
        ) : teamMembers.length === 0 ? (
          <p className="rounded-lg bg-muted/50 px-4 py-6 text-center text-sm text-muted-foreground">
            No team members yet. You're the first — invite others to share the workload.
          </p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Member</TableHead>
                <TableHead>Role</TableHead>
                <TableHead>Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {teamMembers.map((m) => (
                <TableRow key={m.id}>
                  <TableCell>
                    <div className="flex items-center gap-3">
                      <span className="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
                        {m.name.charAt(0).toUpperCase()}
                      </span>
                      <span>
                        <span className="block text-sm font-medium text-foreground">{m.name}</span>
                        <span className="block text-xs text-muted-foreground">{m.email}</span>
                      </span>
                    </div>
                  </TableCell>
                  <TableCell className="text-sm capitalize text-muted-foreground">{m.role}</TableCell>
                  <TableCell>
                    <Badge variant={m.status === "active" ? "default" : "secondary"}>{m.status}</Badge>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
        <p className="text-xs text-muted-foreground">
          Invited members sign in with a temporary password. Role changes and removals are handled by support for now.
        </p>
      </SettingSection>

      <FormModal
        open={dialogOpen}
        onOpenChange={setDialogOpen}
        title="Invite a team member"
        description="They'll get an email with a temporary password."
        onSubmit={submitInvite}
        isLoading={submitting}
        submitLabel="Send invite"
      >
        <div className="space-y-4">
          {formError && (
            <p className="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-[13px] text-destructive">
              {formError}
            </p>
          )}
          {tempPassword ? (
            <div className="space-y-2 rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-3">
              <p className="text-sm font-medium text-foreground">Invite sent. Share this password securely:</p>
              <div className="flex items-center gap-2">
                <code className="flex-1 rounded-md bg-muted px-2.5 py-1.5 font-mono text-sm">{tempPassword}</code>
                <Button type="button" variant="outline" size="icon" onClick={copyPassword} title="Copy password">
                  <Copy className="h-4 w-4" />
                </Button>
              </div>
            </div>
          ) : (
            <>
              <div className="space-y-1.5">
                <Label htmlFor="invite-name">Full name</Label>
                <Input id="invite-name" value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. Amina Yusuf" />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="invite-email">Email</Label>
                <Input id="invite-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="amina@business.com" />
              </div>
              <div className="space-y-1.5">
                <Label>Role</Label>
                <Select value={role} onValueChange={(v) => setRole(v as "agent" | "company_admin")}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="agent">Agent — chats, orders, catalog</SelectItem>
                    <SelectItem value="company_admin">Admin — full access</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </>
          )}
        </div>
      </FormModal>
    </div>
  )
}
