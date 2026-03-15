"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
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
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Plus, Pencil, Trash2, Layers } from "lucide-react"
import { useAdminPlans } from "@/lib/api-hooks"
import { createPlan, updatePlan, deletePlan, type CreatePlanData } from "@/lib/api-actions"
import type { Plan } from "@/lib/mock-data"

const TRIAL_ELAPSED_OPTIONS: { value: string; label: string }[] = [
  { value: "require_payment", label: "Require payment to continue" },
  { value: "downgrade", label: "Downgrade to free plan" },
  { value: "suspend", label: "Suspend account" },
  { value: "cancel", label: "Cancel subscription" },
]

const defaultForm: CreatePlanData & { featuresText: string } = {
  name: "",
  slug: "",
  priceDisplay: "",
  priceAmount: null,
  description: "",
  features: [],
  featuresText: "",
  popular: false,
  cta: "Start Free Trial",
  sortOrder: 0,
  stripePriceId: "",
  isFree: false,
  hasTrial: false,
  trialDays: null,
  trialElapsedAction: null,
}

function slugify(s: string): string {
  return s
    .toLowerCase()
    .replace(/\s+/g, "-")
    .replace(/[^a-z0-9_-]/g, "")
}

export default function AdminPlansPage() {
  const { data: plans, error, isLoading, mutate } = useAdminPlans()
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editingPlan, setEditingPlan] = useState<Plan | null>(null)
  const [form, setForm] = useState(defaultForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState("")
  const [deleteTarget, setDeleteTarget] = useState<Plan | null>(null)
  const [deleting, setDeleting] = useState(false)

  const openCreate = () => {
    setEditingPlan(null)
    setForm({ ...defaultForm })
    setFormError("")
    setDialogOpen(true)
  }

  const openEdit = (plan: Plan) => {
    setEditingPlan(plan)
    setForm({
      name: plan.name,
      slug: plan.slug,
      priceDisplay: plan.priceDisplay ?? plan.price ?? "",
      priceAmount: plan.priceAmount ?? null,
      description: plan.description ?? "",
      features: plan.features ?? [],
      featuresText: (plan.features ?? []).join("\n"),
      popular: plan.popular ?? false,
      cta: plan.cta ?? "Start Free Trial",
      sortOrder: plan.sortOrder ?? 0,
      stripePriceId: plan.stripePriceId ?? "",
      isFree: plan.isFree ?? false,
      hasTrial: plan.hasTrial ?? false,
      trialDays: plan.trialDays ?? null,
      trialElapsedAction: plan.trialElapsedAction ?? null,
    })
    setFormError("")
    setDialogOpen(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setFormError("")
    const features = form.featuresText
      .split("\n")
      .map((s) => s.trim())
      .filter(Boolean)
    const payload: CreatePlanData = {
      name: form.name.trim(),
      slug: form.slug.trim() || slugify(form.name),
      priceDisplay: form.priceDisplay.trim(),
      priceAmount: form.priceAmount != null && form.priceAmount !== "" ? Number(form.priceAmount) : null,
      description: form.description?.trim() || undefined,
      features,
      popular: form.popular,
      cta: form.cta?.trim() || "Start Free Trial",
      sortOrder: form.sortOrder ?? 0,
      stripePriceId: form.stripePriceId?.trim() || undefined,
      isFree: form.isFree ?? false,
      hasTrial: form.hasTrial ?? false,
      trialDays: form.hasTrial && form.trialDays != null && form.trialDays > 0 ? form.trialDays : null,
      trialElapsedAction: form.hasTrial && form.trialElapsedAction ? form.trialElapsedAction : null,
    }
    if (!payload.name || !payload.slug || !payload.priceDisplay) {
      setFormError("Name, slug, and price display are required.")
      return
    }
    setSaving(true)
    try {
      if (editingPlan) {
        const res = await updatePlan(editingPlan.id, payload)
        if (res.success) {
          setDialogOpen(false)
          mutate()
        } else {
          setFormError(res.message ?? "Update failed.")
        }
      } else {
        const res = await createPlan(payload)
        if (res.success) {
          setDialogOpen(false)
          mutate()
        } else {
          setFormError(res.message ?? "Create failed.")
        }
      }
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      const res = await deletePlan(deleteTarget.id)
      if (res.success) {
        setDeleteTarget(null)
        mutate()
      }
    } finally {
      setDeleting(false)
    }
  }

  if (isLoading && !plans) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Plans</h1>
          <p className="text-muted-foreground">Create and manage pricing plans shown on the public pricing page</p>
        </div>
        <Card>
          <CardContent className="p-8">
            <div className="flex items-center justify-center gap-2 text-muted-foreground">
              <span className="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
              Loading plans...
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
          <h1 className="text-2xl font-bold text-foreground">Plans</h1>
          <p className="text-muted-foreground">Create and manage pricing plans shown on the public pricing page</p>
        </div>
        <Card className="border-destructive/50">
          <CardContent className="p-6">
            <p className="text-destructive">Failed to load plans. Please try again.</p>
            <Button variant="outline" className="mt-2" onClick={() => mutate()}>Retry</Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  const list = plans ?? []

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Plans</h1>
          <p className="text-muted-foreground">Create and manage pricing plans shown on the public pricing page</p>
        </div>
        <Button onClick={openCreate}>
          <Plus className="mr-2 h-4 w-4" />
          Add Plan
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Layers className="h-5 w-5" />
            Pricing Plans
          </CardTitle>
        </CardHeader>
        <CardContent>
          {list.length === 0 ? (
            <div className="py-12 text-center text-muted-foreground">
              No plans yet. Click &quot;Add Plan&quot; to create your first plan.
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Slug</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead>Price</TableHead>
                  <TableHead>Trial</TableHead>
                  <TableHead>Popular</TableHead>
                  <TableHead>CTA</TableHead>
                  <TableHead>Order</TableHead>
                  <TableHead className="w-[100px]"></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {list.map((plan) => (
                  <TableRow key={plan.id}>
                    <TableCell className="font-medium text-foreground">{plan.name}</TableCell>
                    <TableCell className="text-muted-foreground font-mono text-sm">{plan.slug}</TableCell>
                    <TableCell>
                      {plan.isFree ? (
                        <Badge variant="secondary">Free</Badge>
                      ) : (
                        <span className="text-muted-foreground text-sm">Paid</span>
                      )}
                    </TableCell>
                    <TableCell>
                      {plan.priceDisplay ?? plan.price ?? "—"}
                      {plan.priceAmount != null && (
                        <span className="ml-1 text-muted-foreground text-xs">(${plan.priceAmount})</span>
                      )}
                    </TableCell>
                    <TableCell className="text-muted-foreground text-sm">
                      {plan.hasTrial && plan.trialDays != null ? (
                        <span>
                          {plan.trialDays} days
                          {plan.trialElapsedAction && (
                            <span className="block text-xs">
                              → {TRIAL_ELAPSED_OPTIONS.find((o) => o.value === plan.trialElapsedAction)?.label ?? plan.trialElapsedAction}
                            </span>
                          )}
                        </span>
                      ) : (
                        "—"
                      )}
                    </TableCell>
                    <TableCell>
                      {plan.popular ? <Badge variant="default">Popular</Badge> : "—"}
                    </TableCell>
                    <TableCell className="text-muted-foreground text-sm">{plan.cta ?? "—"}</TableCell>
                    <TableCell className="text-muted-foreground">{plan.sortOrder ?? 0}</TableCell>
                    <TableCell>
                      <div className="flex items-center gap-1">
                        <Button variant="ghost" size="icon" onClick={() => openEdit(plan)} title="Edit">
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="text-destructive hover:text-destructive"
                          onClick={() => setDeleteTarget(plan)}
                          title="Delete"
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{editingPlan ? "Edit Plan" : "Add Plan"}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            {formError && (
              <p className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">{formError}</p>
            )}
            <div className="grid gap-2">
              <Label htmlFor="plan-name">Name</Label>
              <Input
                id="plan-name"
                value={form.name}
                onChange={(e) => {
                  setForm((f) => ({ ...f, name: e.target.value }))
                  if (!editingPlan) setForm((f) => ({ ...f, slug: slugify(e.target.value) }))
                }}
                placeholder="e.g. Growth"
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="plan-slug">Slug (used in subscriptions)</Label>
              <Input
                id="plan-slug"
                value={form.slug}
                onChange={(e) => setForm((f) => ({ ...f, slug: e.target.value.toLowerCase().replace(/\s+/g, "-").replace(/[^a-z0-9_-]/g, "") }))}
                placeholder="e.g. professional"
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="grid gap-2">
                <Label htmlFor="plan-price-display">Price display</Label>
                <Input
                  id="plan-price-display"
                  value={form.priceDisplay}
                  onChange={(e) => setForm((f) => ({ ...f, priceDisplay: e.target.value }))}
                  placeholder="e.g. $99 or Custom"
                />
              </div>
              <div className="grid gap-2">
                <Label htmlFor="plan-price-amount">Price amount (optional)</Label>
                <Input
                  id="plan-price-amount"
                  type="number"
                  min={0}
                  step={0.01}
                  value={form.priceAmount ?? ""}
                  onChange={(e) => setForm((f) => ({ ...f, priceAmount: e.target.value === "" ? null : Number(e.target.value) }))}
                  placeholder="99"
                />
              </div>
            </div>
            <div className="grid gap-2">
              <Label htmlFor="plan-description">Description</Label>
              <Input
                id="plan-description"
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                placeholder="For growing businesses..."
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="plan-features">Features (one per line)</Label>
              <textarea
                id="plan-features"
                className="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                value={form.featuresText}
                onChange={(e) => setForm((f) => ({ ...f, featuresText: e.target.value }))}
                placeholder="3 WhatsApp numbers&#10;10,000 messages/month"
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="plan-cta">CTA button text</Label>
              <Input
                id="plan-cta"
                value={form.cta}
                onChange={(e) => setForm((f) => ({ ...f, cta: e.target.value }))}
                placeholder="Start Free Trial"
              />
            </div>

            <div className="space-y-4 rounded-lg border border-border p-4">
              <p className="text-sm font-medium text-foreground">Plan type & trial</p>
              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="plan-is-free"
                  checked={form.isFree}
                  onChange={(e) =>
                    setForm((f) => ({
                      ...f,
                      isFree: e.target.checked,
                      hasTrial: e.target.checked ? false : f.hasTrial,
                    }))
                  }
                  className="h-4 w-4 rounded border-input"
                />
                <Label htmlFor="plan-is-free">This is a free plan (no payment required)</Label>
              </div>
              {!form.isFree && (
                <>
                  <div className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      id="plan-has-trial"
                      checked={form.hasTrial}
                      onChange={(e) => setForm((f) => ({ ...f, hasTrial: e.target.checked }))}
                      className="h-4 w-4 rounded border-input"
                    />
                    <Label htmlFor="plan-has-trial">This plan has a trial period users can try</Label>
                  </div>
                  {form.hasTrial && (
                    <>
                      <div className="grid gap-2">
                        <Label htmlFor="plan-trial-days">Trial length (days)</Label>
                        <Input
                          id="plan-trial-days"
                          type="number"
                          min={1}
                          max={365}
                          value={form.trialDays ?? ""}
                          onChange={(e) =>
                            setForm((f) => ({
                              ...f,
                              trialDays: e.target.value === "" ? null : parseInt(e.target.value, 10) || null,
                            }))
                          }
                          placeholder="e.g. 14"
                        />
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="plan-trial-elapsed">When trial elapses, what happens to the customer account?</Label>
                        <Select
                          value={form.trialElapsedAction ?? ""}
                          onValueChange={(value) => setForm((f) => ({ ...f, trialElapsedAction: value || null }))}
                        >
                          <SelectTrigger id="plan-trial-elapsed" className="w-full">
                            <SelectValue placeholder="Select behavior..." />
                          </SelectTrigger>
                          <SelectContent>
                            {TRIAL_ELAPSED_OPTIONS.map((opt) => (
                              <SelectItem key={opt.value} value={opt.value}>
                                {opt.label}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                    </>
                  )}
                </>
              )}
            </div>

            <div className="flex items-center gap-2">
              <input
                type="checkbox"
                id="plan-popular"
                checked={form.popular}
                onChange={(e) => setForm((f) => ({ ...f, popular: e.target.checked }))}
                className="h-4 w-4 rounded border-input"
              />
              <Label htmlFor="plan-popular">Mark as &quot;Most Popular&quot;</Label>
            </div>
            <div className="grid gap-2">
              <Label htmlFor="plan-sort">Sort order</Label>
              <Input
                id="plan-sort"
                type="number"
                min={0}
                value={form.sortOrder}
                onChange={(e) => setForm((f) => ({ ...f, sortOrder: parseInt(e.target.value, 10) || 0 }))}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="plan-stripe-price">Stripe Price ID (optional)</Label>
              <Input
                id="plan-stripe-price"
                value={form.stripePriceId ?? ""}
                onChange={(e) => setForm((f) => ({ ...f, stripePriceId: e.target.value }))}
                placeholder="price_1ABC..."
              />
              <p className="text-xs text-muted-foreground">
                Create a recurring price in Stripe Dashboard and paste the Price ID here to enable checkout for this plan.
              </p>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={saving}>
                {saving ? "Saving..." : editingPlan ? "Update Plan" : "Create Plan"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete plan?</AlertDialogTitle>
            <AlertDialogDescription>
              This will remove &quot;{deleteTarget?.name}&quot; from the pricing page. Companies already on this plan
              may still show it in their subscription until you change their plan. This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} disabled={deleting} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
              {deleting ? "Deleting..." : "Delete"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
