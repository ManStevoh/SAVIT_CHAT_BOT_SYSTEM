"use client"

import { useEffect, useMemo, useState } from "react"
import useSWR from "swr"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Checkbox } from "@/components/ui/checkbox"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Plus, Pencil, Trash2, Send, Megaphone } from "lucide-react"
import {
  createPlatformMarketing,
  deletePlatformMarketing,
  listMerchantLifecycleSends,
  listPlatformMarketing,
  listPlatformMarketingSends,
  sendPlatformMarketingNow,
  updatePlatformMarketing,
  type PlatformMarketingMessage,
  type PlatformMarketingPayload,
} from "@/lib/api-actions"
import { useToast } from "@/hooks/use-toast"

const defaultForm: PlatformMarketingPayload = {
  name: "",
  title: "",
  bodyText: "",
  bodyHtml: "",
  ctaLabel: "Open dashboard",
  ctaUrl: "",
  channelEmail: true,
  channelWhatsapp: true,
  channelPopup: true,
  audience: "marketing_consent",
  status: "draft",
  sendMode: "manual",
  scheduledAt: "",
  recurringInterval: "weekly",
  trigger: "registered",
  triggerDelaySeconds: 0,
  popupOnce: true,
}

function toDatetimeLocal(iso?: string | null): string {
  if (!iso) return ""
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ""
  const pad = (n: number) => String(n).padStart(2, "0")
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

export default function AdminMarketingPage() {
  const { toast } = useToast()
  const { data, error, isLoading, mutate } = useSWR("admin-marketing", listPlatformMarketing, {
    revalidateOnFocus: false,
  })
  const messages = data?.messages ?? []
  const { data: sendLog, mutate: mutateSends } = useSWR("admin-marketing-sends", () => listPlatformMarketingSends(), {
    revalidateOnFocus: false,
  })
  const { data: dripLog } = useSWR("admin-lifecycle-sends", listMerchantLifecycleSends, {
    revalidateOnFocus: false,
  })

  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<PlatformMarketingMessage | null>(null)
  const [form, setForm] = useState<PlatformMarketingPayload>(defaultForm)
  const [saving, setSaving] = useState(false)
  const [sendingId, setSendingId] = useState<number | null>(null)
  const [formError, setFormError] = useState("")
  const [deleteTarget, setDeleteTarget] = useState<PlatformMarketingMessage | null>(null)
  const [tab, setTab] = useState<"campaigns" | "sends" | "onboarding">("campaigns")

  const openCreate = () => {
    setEditing(null)
    setForm(defaultForm)
    setFormError("")
    setDialogOpen(true)
  }

  const openEdit = (row: PlatformMarketingMessage) => {
    setEditing(row)
    setForm({
      name: row.name,
      title: row.title,
      bodyText: row.bodyText,
      bodyHtml: row.bodyHtml ?? "",
      ctaLabel: row.ctaLabel ?? "",
      ctaUrl: row.ctaUrl ?? "",
      channelEmail: row.channelEmail,
      channelWhatsapp: row.channelWhatsapp,
      channelPopup: row.channelPopup,
      audience: row.audience,
      status: row.status,
      sendMode: row.sendMode,
      scheduledAt: toDatetimeLocal(row.scheduledAt),
      recurringInterval: row.recurringInterval ?? "weekly",
      trigger: row.trigger ?? "registered",
      triggerDelaySeconds: row.triggerDelaySeconds ?? 0,
      popupOnce: row.popupOnce,
    })
    setFormError("")
    setDialogOpen(true)
  }

  const payload = useMemo((): PlatformMarketingPayload => {
    return {
      ...form,
      scheduledAt: form.scheduledAt ? new Date(form.scheduledAt).toISOString() : null,
      triggerDelaySeconds: Number(form.triggerDelaySeconds || 0),
    }
  }, [form])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setFormError("")
    if (!form.name.trim() || !form.title.trim() || !form.bodyText.trim()) {
      setFormError("Name, title, and message body are required.")
      return
    }
    if (!form.channelEmail && !form.channelWhatsapp && !form.channelPopup) {
      setFormError("Pick at least one channel: email, WhatsApp, or login popup.")
      return
    }
    setSaving(true)
    try {
      const res = editing
        ? await updatePlatformMarketing(editing.id, payload)
        : await createPlatformMarketing(payload)
      if (res.success) {
        toast({ title: editing ? "Campaign updated" : "Campaign created" })
        setDialogOpen(false)
        mutate()
      } else {
        setFormError((res as { message?: string }).message || "Save failed")
      }
    } catch {
      setFormError("Request failed")
    } finally {
      setSaving(false)
    }
  }

  const handleSendNow = async (row: PlatformMarketingMessage) => {
    setSendingId(row.id)
    try {
      const res = await sendPlatformMarketingNow(row.id)
      if (res.success) {
        toast({ title: `Sent ${res.sent ?? 0} message(s)` })
        mutate()
        mutateSends()
      } else {
        toast({ title: res.message ?? "Send failed", variant: "destructive" })
      }
    } finally {
      setSendingId(null)
    }
  }

  useEffect(() => {
    document.title = "Merchant marketing"
  }, [])

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Merchant marketing</h1>
          <p className="text-sm text-muted-foreground mt-1 max-w-2xl">
            Write extra messages for merchants. Send by email and WhatsApp, schedule or automate them, and show a popup when they log in.
          </p>
        </div>
        <Button onClick={openCreate}>
          <Plus className="h-4 w-4 mr-2" />
          New campaign
        </Button>
      </div>

      <div className="flex gap-2">
        {([
          ["campaigns", "Campaigns"],
          ["sends", "Delivery log"],
          ["onboarding", "Onboarding drip"],
        ] as const).map(([id, label]) => (
          <Button key={id} variant={tab === id ? "default" : "outline"} size="sm" onClick={() => setTab(id)}>
            {label}
          </Button>
        ))}
      </div>

      {tab === "campaigns" && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Megaphone className="h-5 w-5" />
              Campaigns
            </CardTitle>
            <CardDescription>
              Manual send, one-time schedule, recurring, or trigger on register / verify / login. Email and WhatsApp go only to merchants who opted in.
            </CardDescription>
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <p className="text-sm text-muted-foreground">Loading…</p>
            ) : error ? (
              <p className="text-sm text-destructive">Could not load campaigns.</p>
            ) : messages.length === 0 ? (
              <p className="text-sm text-muted-foreground">No campaigns yet. Create one to email, WhatsApp, or popup merchants.</p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Channels</TableHead>
                    <TableHead>Audience</TableHead>
                    <TableHead>Mode</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Reach</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {messages.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell>
                        <div className="font-medium">{row.name}</div>
                        <div className="text-xs text-muted-foreground">{row.title}</div>
                      </TableCell>
                      <TableCell className="text-xs">
                        {[row.channelEmail && "Email", row.channelWhatsapp && "WhatsApp", row.channelPopup && "Popup"]
                          .filter(Boolean)
                          .join(" · ")}
                      </TableCell>
                      <TableCell className="text-xs">{row.audience.replace(/_/g, " ")}</TableCell>
                      <TableCell className="text-xs">
                        {row.sendMode}
                        {row.sendMode === "scheduled" && row.scheduledAt ? ` · ${new Date(row.scheduledAt).toLocaleString()}` : ""}
                        {row.sendMode === "recurring" ? ` · ${row.recurringInterval}` : ""}
                        {row.sendMode === "trigger" ? ` · ${row.trigger}` : ""}
                      </TableCell>
                      <TableCell className="capitalize text-xs">{row.status}</TableCell>
                      <TableCell className="text-xs">{row.audienceCount} merchants · {row.sendsCount} sends</TableCell>
                      <TableCell className="text-right space-x-1">
                        <Button size="sm" variant="outline" disabled={sendingId === row.id} onClick={() => handleSendNow(row)}>
                          <Send className="h-3.5 w-3.5 mr-1" />
                          Send now
                        </Button>
                        <Button size="sm" variant="ghost" onClick={() => openEdit(row)}>
                          <Pencil className="h-3.5 w-3.5" />
                        </Button>
                        <Button size="sm" variant="ghost" onClick={() => setDeleteTarget(row)}>
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>
      )}

      {tab === "sends" && (
        <Card>
          <CardHeader>
            <CardTitle>Delivery log</CardTitle>
            <CardDescription>Email, WhatsApp, skipped, failed, and dismissed popups.</CardDescription>
          </CardHeader>
          <CardContent>
            <SendsTable rows={(sendLog?.sends ?? []) as Array<Record<string, string>>} />
          </CardContent>
        </Card>
      )}

      {tab === "onboarding" && (
        <Card>
          <CardHeader>
            <CardTitle>Automatic onboarding drip</CardTitle>
            <CardDescription>
              Welcome, verified, add product, connect WhatsApp, payments, share store, then opted-in marketing tips. Runs hourly.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <SendsTable rows={(dripLog?.sends ?? []) as Array<Record<string, string>>} />
          </CardContent>
        </Card>
      )}

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{editing ? "Edit campaign" : "New campaign"}</DialogTitle>
          </DialogHeader>
          <form className="space-y-4" onSubmit={handleSubmit}>
            {formError ? <p className="text-sm text-destructive">{formError}</p> : null}
            <div className="space-y-2">
              <Label htmlFor="mk-name">Internal name</Label>
              <Input id="mk-name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
            </div>
            <div className="space-y-2">
              <Label htmlFor="mk-title">Title / email subject</Label>
              <Input id="mk-title" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required />
            </div>
            <div className="space-y-2">
              <Label htmlFor="mk-body">Message</Label>
              <Textarea id="mk-body" rows={5} value={form.bodyText} onChange={(e) => setForm({ ...form, bodyText: e.target.value })} required />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-2">
                <Label htmlFor="mk-cta-label">Button label</Label>
                <Input id="mk-cta-label" value={form.ctaLabel ?? ""} onChange={(e) => setForm({ ...form, ctaLabel: e.target.value })} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="mk-cta-url">Button URL</Label>
                <Input id="mk-cta-url" value={form.ctaUrl ?? ""} onChange={(e) => setForm({ ...form, ctaUrl: e.target.value })} />
              </div>
            </div>
            <div className="space-y-2">
              <Label>Channels</Label>
              <div className="flex flex-wrap gap-4">
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox checked={form.channelEmail} onCheckedChange={(v) => setForm({ ...form, channelEmail: v === true })} />
                  Email
                </label>
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox checked={form.channelWhatsapp} onCheckedChange={(v) => setForm({ ...form, channelWhatsapp: v === true })} />
                  WhatsApp
                </label>
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox checked={form.channelPopup} onCheckedChange={(v) => setForm({ ...form, channelPopup: v === true })} />
                  Login popup
                </label>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-2">
                <Label>Audience</Label>
                <Select value={form.audience} onValueChange={(v) => setForm({ ...form, audience: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="marketing_consent">Opted-in marketing</SelectItem>
                    <SelectItem value="all_merchants">All merchants</SelectItem>
                    <SelectItem value="no_product">No first product</SelectItem>
                    <SelectItem value="no_payments">Payments off</SelectItem>
                    <SelectItem value="no_whatsapp">WhatsApp not connected</SelectItem>
                    <SelectItem value="trial">On trial</SelectItem>
                    <SelectItem value="paid">Paid plan</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>Status</Label>
                <Select value={form.status} onValueChange={(v) => setForm({ ...form, status: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="draft">Draft</SelectItem>
                    <SelectItem value="scheduled">Scheduled</SelectItem>
                    <SelectItem value="active">Active</SelectItem>
                    <SelectItem value="paused">Paused</SelectItem>
                    <SelectItem value="archived">Archived</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="space-y-2">
              <Label>Automation</Label>
              <Select value={form.sendMode} onValueChange={(v) => setForm({ ...form, sendMode: v })}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="manual">Manual — send now</SelectItem>
                  <SelectItem value="scheduled">Schedule once</SelectItem>
                  <SelectItem value="recurring">Recurring</SelectItem>
                  <SelectItem value="trigger">Trigger (register / verify / login)</SelectItem>
                </SelectContent>
              </Select>
            </div>
            {form.sendMode === "scheduled" && (
              <div className="space-y-2">
                <Label htmlFor="mk-when">Send at</Label>
                <Input id="mk-when" type="datetime-local" value={form.scheduledAt ?? ""} onChange={(e) => setForm({ ...form, scheduledAt: e.target.value })} />
              </div>
            )}
            {form.sendMode === "recurring" && (
              <div className="space-y-2">
                <Label>Repeat</Label>
                <Select value={form.recurringInterval ?? "weekly"} onValueChange={(v) => setForm({ ...form, recurringInterval: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="daily">Daily</SelectItem>
                    <SelectItem value="weekly">Weekly</SelectItem>
                    <SelectItem value="monthly">Monthly</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            )}
            {form.sendMode === "trigger" && (
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                  <Label>When</Label>
                  <Select value={form.trigger ?? "registered"} onValueChange={(v) => setForm({ ...form, trigger: v })}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="registered">On register</SelectItem>
                      <SelectItem value="verified">On email verify</SelectItem>
                      <SelectItem value="login">On login</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="mk-delay">Delay (hours)</Label>
                  <Input
                    id="mk-delay"
                    type="number"
                    min={0}
                    value={Math.round((form.triggerDelaySeconds ?? 0) / 3600)}
                    onChange={(e) => setForm({ ...form, triggerDelaySeconds: Number(e.target.value || 0) * 3600 })}
                  />
                </div>
              </div>
            )}
            <div className="flex items-center justify-between rounded-lg border p-3">
              <div>
                <Label>Show login popup once</Label>
                <p className="text-xs text-muted-foreground">Off = show again each recurring period.</p>
              </div>
              <Switch checked={form.popupOnce} onCheckedChange={(v) => setForm({ ...form, popupOnce: v })} />
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>Cancel</Button>
              <Button type="submit" disabled={saving}>{saving ? "Saving…" : "Save campaign"}</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete campaign?</AlertDialogTitle>
            <AlertDialogDescription>This removes the campaign and its send log.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={async () => {
                if (!deleteTarget) return
                const res = await deletePlatformMarketing(deleteTarget.id)
                if (res.success) {
                  toast({ title: "Campaign deleted" })
                  mutate()
                }
                setDeleteTarget(null)
              }}
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

function SendsTable({ rows }: { rows: Array<Record<string, string>> }) {
  if (rows.length === 0) {
    return <p className="text-sm text-muted-foreground">No sends yet.</p>
  }
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>When</TableHead>
          <TableHead>Merchant</TableHead>
          <TableHead>Channel</TableHead>
          <TableHead>Status</TableHead>
          <TableHead>Detail</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {rows.map((row) => (
          <TableRow key={String(row.id)}>
            <TableCell className="text-xs">{row.sentAt ? new Date(row.sentAt).toLocaleString() : "—"}</TableCell>
            <TableCell className="text-xs">{row.userEmail || row.userName || row.step || row.messageName}</TableCell>
            <TableCell className="text-xs">{row.channel}{row.step ? ` · ${row.step}` : ""}</TableCell>
            <TableCell className="text-xs capitalize">{row.status}</TableCell>
            <TableCell className="text-xs text-muted-foreground">{row.error || "—"}</TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}
