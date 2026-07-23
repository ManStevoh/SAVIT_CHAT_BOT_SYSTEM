"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
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
import { Plus, Pencil, Trash2, Star, MessageSquare } from "lucide-react"
import { useAdminTestimonials, type AdminTestimonial } from "@/lib/api-hooks"
import { createTestimonial, updateTestimonial, deleteTestimonial, type CreateTestimonialData } from "@/lib/api-actions"
import { useToast } from "@/hooks/use-toast"

const defaultForm = {
  name: "",
  role: "",
  content: "",
  rating: 5,
  sortOrder: 0,
  isActive: true,
}

export default function AdminTestimonialsPage() {
  const { data: testimonials = [], error, isLoading, mutate } = useAdminTestimonials()
  const { toast } = useToast()
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<AdminTestimonial | null>(null)
  const [form, setForm] = useState(defaultForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState("")
  const [deleteTarget, setDeleteTarget] = useState<AdminTestimonial | null>(null)
  const [deleting, setDeleting] = useState(false)

  const openCreate = () => {
    setEditing(null)
    setForm(defaultForm)
    setFormError("")
    setDialogOpen(true)
  }

  const openEdit = (t: AdminTestimonial) => {
    setEditing(t)
    setForm({
      name: t.name,
      role: t.role ?? "",
      content: t.content,
      rating: t.rating ?? 5,
      sortOrder: t.sortOrder ?? 0,
      isActive: t.isActive ?? true,
    })
    setFormError("")
    setDialogOpen(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setFormError("")
    const payload: CreateTestimonialData = {
      name: form.name.trim(),
      role: form.role.trim() || undefined,
      content: form.content.trim(),
      rating: form.rating,
      sortOrder: form.sortOrder,
      isActive: form.isActive,
    }
    if (!payload.name || !payload.content) {
      setFormError("Name and content are required.")
      return
    }
    setSaving(true)
    try {
      if (editing) {
        const res = await updateTestimonial(editing.id, payload)
        if (res.success) {
          toast({ title: "Testimonial updated" })
          setDialogOpen(false)
          mutate()
        } else {
          setFormError(res.message ?? "Update failed")
        }
      } else {
        const res = await createTestimonial(payload)
        if (res.success) {
          toast({ title: "Testimonial added" })
          setDialogOpen(false)
          mutate()
        } else {
          setFormError(res.message ?? "Create failed")
        }
      }
    } catch {
      setFormError("Request failed")
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      const res = await deleteTestimonial(deleteTarget.id)
      if (res.success) {
        toast({ title: "Testimonial deleted" })
        setDeleteTarget(null)
        mutate()
      } else {
        toast({ title: res.message ?? "Delete failed", variant: "destructive" })
      }
    } catch {
      toast({ title: "Delete failed", variant: "destructive" })
    } finally {
      setDeleting(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Testimonials</h1>
          <p className="text-muted-foreground">Manage testimonials shown on the public landing page</p>
        </div>
        <Button onClick={openCreate} className="gap-2">
          <Plus className="h-4 w-4" />
          Add testimonial
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <MessageSquare className="h-5 w-5" />
            Landing page testimonials
          </CardTitle>
          <CardDescription>Only active testimonials appear on the landing page. Order by sort order.</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center py-8">
              <span className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
            </div>
          ) : error ? (
            <p className="text-destructive py-4">Failed to load testimonials.</p>
          ) : testimonials.length === 0 ? (
            <p className="text-muted-foreground py-8 text-center">
              No testimonials yet. Add one to show on the landing page.
            </p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Role</TableHead>
                  <TableHead>Content</TableHead>
                  <TableHead className="w-20">Rating</TableHead>
                  <TableHead className="w-20">Order</TableHead>
                  <TableHead className="w-24">Active</TableHead>
                  <TableHead className="w-32 text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {testimonials.map((t) => (
                  <TableRow key={t.id}>
                    <TableCell className="font-medium">{t.name}</TableCell>
                    <TableCell className="text-muted-foreground">{t.role || "—"}</TableCell>
                    <TableCell className="max-w-xs truncate text-muted-foreground">{t.content}</TableCell>
                    <TableCell>
                      <span className="flex items-center gap-0.5">
                        {Array.from({ length: 5 }).map((_, i) => (
                          <Star
                            key={i}
                            className={`h-4 w-4 ${i < (t.rating ?? 5) ? "fill-primary text-primary" : "text-muted-foreground/40"}`}
                          />
                        ))}
                      </span>
                    </TableCell>
                    <TableCell>{t.sortOrder}</TableCell>
                    <TableCell>{t.isActive ? "Yes" : "No"}</TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Button variant="ghost" size="sm" onClick={() => openEdit(t)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive" onClick={() => setDeleteTarget(t)}>
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
        <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{editing ? "Edit testimonial" : "Add testimonial"}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="name">Name</Label>
                <Input
                  id="name"
                  value={form.name}
                  onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                  placeholder="Sarah Johnson"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="role">Role / company</Label>
                <Input
                  id="role"
                  value={form.role}
                  onChange={(e) => setForm((f) => ({ ...f, role: e.target.value }))}
                  placeholder="Owner, QuickBite Restaurant"
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="content">Quote</Label>
              <Textarea
                id="content"
                value={form.content}
                onChange={(e) => setForm((f) => ({ ...f, content: e.target.value }))}
                placeholder="RelayIQ transformed our order management..."
                rows={4}
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Rating (1–5)</Label>
                <div className="flex gap-1">
                  {[1, 2, 3, 4, 5].map((r) => (
                    <button
                      key={r}
                      type="button"
                      onClick={() => setForm((f) => ({ ...f, rating: r }))}
                      className="p-1 rounded hover:bg-muted"
                    >
                      <Star className={`h-6 w-6 ${r <= form.rating ? "fill-primary text-primary" : "text-muted-foreground/40"}`} />
                    </button>
                  ))}
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="sortOrder">Sort order</Label>
                <Input
                  id="sortOrder"
                  type="number"
                  min={0}
                  value={form.sortOrder}
                  onChange={(e) => setForm((f) => ({ ...f, sortOrder: parseInt(e.target.value, 10) || 0 }))}
                />
              </div>
            </div>
            <div className="flex items-center gap-2">
              <Switch
                id="isActive"
                checked={form.isActive}
                onCheckedChange={(checked) => setForm((f) => ({ ...f, isActive: !!checked }))}
              />
              <Label htmlFor="isActive">Show on landing page</Label>
            </div>
            {formError && <p className="text-sm text-destructive">{formError}</p>}
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={saving}>
                {saving ? "Saving…" : editing ? "Update" : "Add"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete testimonial?</AlertDialogTitle>
            <AlertDialogDescription>
              This will remove &quot;{deleteTarget?.name}&quot; from the landing page. You can add it again later.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} disabled={deleting} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
              {deleting ? "Deleting…" : "Delete"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
