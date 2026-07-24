"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
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
import { Plus, Pencil, Trash2, Tag } from "lucide-react"
import { toast } from "sonner"
import { useAdminPlans, useAdminSubscriptionOffers } from "@/lib/api-hooks"
import {
  createSubscriptionOffer,
  updateSubscriptionOffer,
  deleteSubscriptionOffer,
  type SubscriptionOfferPayload,
  type SubscriptionOffer,
} from "@/lib/api-actions"

const emptyForm: SubscriptionOfferPayload = {
  name: "",
  code: "",
  description: "",
  discountType: "percent",
  discountValue: 10,
  currency: "",
  planId: null,
  maxRedemptions: null,
  maxPerCompany: 1,
  startsAt: "",
  endsAt: "",
  isActive: true,
  firstPaymentOnly: true,
}

export default function AdminOffersPage() {
  const { data: offers = [], mutate, isLoading } = useAdminSubscriptionOffers()
  const { data: plans = [] } = useAdminPlans()
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState<SubscriptionOffer | null>(null)
  const [form, setForm] = useState<SubscriptionOfferPayload>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [deleteId, setDeleteId] = useState<string | null>(null)

  const openCreate = () => {
    setEditing(null)
    setForm({ ...emptyForm })
    setOpen(true)
  }

  const openEdit = (offer: SubscriptionOffer) => {
    setEditing(offer)
    setForm({
      name: offer.name,
      code: offer.code,
      description: offer.description ?? "",
      discountType: offer.discountType,
      discountValue: offer.discountValue,
      currency: offer.currency ?? "",
      planId: offer.planId,
      maxRedemptions: offer.maxRedemptions,
      maxPerCompany: offer.maxPerCompany,
      startsAt: offer.startsAt ? offer.startsAt.slice(0, 16) : "",
      endsAt: offer.endsAt ? offer.endsAt.slice(0, 16) : "",
      isActive: offer.isActive,
      firstPaymentOnly: offer.firstPaymentOnly,
    })
    setOpen(true)
  }

  const handleSave = async () => {
    if (!form.name.trim() || !form.code.trim()) {
      toast.error("Name and code are required.")
      return
    }
    setSaving(true)
    const payload: SubscriptionOfferPayload = {
      ...form,
      code: form.code.trim().toUpperCase(),
      currency: form.currency?.trim() ? form.currency.trim().toUpperCase() : null,
      planId: form.planId || null,
      maxRedemptions: form.maxRedemptions || null,
      startsAt: form.startsAt || null,
      endsAt: form.endsAt || null,
    }
    const result = editing
      ? await updateSubscriptionOffer(editing.id, payload)
      : await createSubscriptionOffer(payload)
    setSaving(false)
    if (!result.success) {
      toast.error(result.message ?? "Could not save offer.")
      return
    }
    toast.success(editing ? "Offer updated." : "Offer created.")
    setOpen(false)
    mutate()
  }

  const handleDelete = async () => {
    if (!deleteId) return
    const result = await deleteSubscriptionOffer(deleteId)
    setDeleteId(null)
    if (!result.success) {
      toast.error(result.message ?? "Could not delete offer.")
      return
    }
    toast.success("Offer deleted.")
    mutate()
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Offers &amp; Coupons</h1>
          <p className="text-muted-foreground">
            Create discount codes customers apply at Paystack or M-Pesa checkout.
            WhatsApp campaigns (Dashboard → WhatsApp Campaigns) are separate — those message your customers, not sell plans.
          </p>
        </div>
        <Button onClick={openCreate}>
          <Plus className="mr-2 h-4 w-4" />
          New offer
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Tag className="h-5 w-5" />
            Active offers
          </CardTitle>
          <CardDescription>
            Coupons reduce the charged amount at subscription checkout. Expiry reminders (7 / 3 / 1 days) run daily via scheduled job.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : offers.length === 0 ? (
            <p className="text-sm text-muted-foreground">No offers yet. Create a coupon code to start promotions.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Code</TableHead>
                  <TableHead>Discount</TableHead>
                  <TableHead>Plan</TableHead>
                  <TableHead>Uses</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {offers.map((offer) => (
                  <TableRow key={offer.id}>
                    <TableCell className="font-medium">{offer.name}</TableCell>
                    <TableCell>
                      <code className="rounded bg-muted px-1.5 py-0.5 text-sm">{offer.code}</code>
                    </TableCell>
                    <TableCell>
                      {offer.discountType === "percent"
                        ? `${offer.discountValue}%`
                        : `${offer.currency ?? ""} ${offer.discountValue}`.trim()}
                    </TableCell>
                    <TableCell>{offer.planName ?? "Any paid plan"}</TableCell>
                    <TableCell>
                      {offer.redemptionCount}
                      {offer.maxRedemptions != null ? ` / ${offer.maxRedemptions}` : ""}
                    </TableCell>
                    <TableCell>
                      <Badge variant={offer.isCurrentlyValid ? "default" : "secondary"}>
                        {offer.isCurrentlyValid ? "Valid" : offer.isActive ? "Inactive window" : "Off"}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right space-x-1">
                      <Button size="icon" variant="ghost" onClick={() => openEdit(offer)}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                      <Button size="icon" variant="ghost" onClick={() => setDeleteId(offer.id)}>
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{editing ? "Edit offer" : "Create offer"}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-4 py-2">
            <div className="grid gap-2">
              <Label>Name</Label>
              <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Summer 20%" />
            </div>
            <div className="grid gap-2">
              <Label>Coupon code</Label>
              <Input
                value={form.code}
                onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })}
                placeholder="SAVE20"
              />
            </div>
            <div className="grid gap-2">
              <Label>Description</Label>
              <Input
                value={form.description ?? ""}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
                placeholder="Optional note for admins"
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-2">
                <Label>Type</Label>
                <Select
                  value={form.discountType}
                  onValueChange={(v) => setForm({ ...form, discountType: v as "percent" | "fixed" })}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="percent">Percent</SelectItem>
                    <SelectItem value="fixed">Fixed amount</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="grid gap-2">
                <Label>Value</Label>
                <Input
                  type="number"
                  min={0.01}
                  step="0.01"
                  value={form.discountValue}
                  onChange={(e) => setForm({ ...form, discountValue: Number(e.target.value) })}
                />
              </div>
            </div>
            <div className="grid gap-2">
              <Label>Currency lock (optional)</Label>
              <Input
                value={form.currency ?? ""}
                onChange={(e) => setForm({ ...form, currency: e.target.value })}
                placeholder="e.g. KES — leave blank for any"
              />
            </div>
            <div className="grid gap-2">
              <Label>Plan restriction</Label>
              <Select
                value={form.planId ?? "any"}
                onValueChange={(v) => setForm({ ...form, planId: v === "any" ? null : v })}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="any">Any paid plan</SelectItem>
                  {plans.map((p) => (
                    <SelectItem key={p.id} value={p.id}>
                      {p.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-2">
                <Label>Max redemptions</Label>
                <Input
                  type="number"
                  min={1}
                  placeholder="Unlimited"
                  value={form.maxRedemptions ?? ""}
                  onChange={(e) =>
                    setForm({
                      ...form,
                      maxRedemptions: e.target.value ? Number(e.target.value) : null,
                    })
                  }
                />
              </div>
              <div className="grid gap-2">
                <Label>Max per company</Label>
                <Input
                  type="number"
                  min={1}
                  value={form.maxPerCompany ?? 1}
                  onChange={(e) => setForm({ ...form, maxPerCompany: Number(e.target.value) || 1 })}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-2">
                <Label>Starts at</Label>
                <Input
                  type="datetime-local"
                  value={form.startsAt ?? ""}
                  onChange={(e) => setForm({ ...form, startsAt: e.target.value })}
                />
              </div>
              <div className="grid gap-2">
                <Label>Ends at</Label>
                <Input
                  type="datetime-local"
                  value={form.endsAt ?? ""}
                  onChange={(e) => setForm({ ...form, endsAt: e.target.value })}
                />
              </div>
            </div>
            <div className="flex items-center justify-between rounded-lg border p-3">
              <Label>Active</Label>
              <Switch checked={!!form.isActive} onCheckedChange={(v) => setForm({ ...form, isActive: v })} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button onClick={handleSave} disabled={saving}>
              {saving ? "Saving…" : "Save offer"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!deleteId} onOpenChange={(o) => !o && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete offer?</AlertDialogTitle>
            <AlertDialogDescription>This removes the coupon code. Past redemptions stay in the ledger.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete}>Delete</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
