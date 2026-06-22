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
import { Plus, Pencil, Trash2, HelpCircle } from "lucide-react"
import { useAdminLandingFaqs, type AdminLandingFaq } from "@/lib/api-hooks"
import { createLandingFaq, updateLandingFaq, deleteLandingFaq, type CreateLandingFaqData } from "@/lib/api-actions"
import { useToast } from "@/hooks/use-toast"

const defaultForm = {
  question: "",
  answer: "",
  sortOrder: 0,
  isActive: true,
}

export default function AdminLandingFaqsPage() {
  const { data: faqs = [], error, isLoading, mutate } = useAdminLandingFaqs()
  const { toast } = useToast()
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<AdminLandingFaq | null>(null)
  const [form, setForm] = useState(defaultForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState("")
  const [deleteTarget, setDeleteTarget] = useState<AdminLandingFaq | null>(null)
  const [deleting, setDeleting] = useState(false)

  const openCreate = () => {
    setEditing(null)
    setForm(defaultForm)
    setFormError("")
    setDialogOpen(true)
  }

  const openEdit = (f: AdminLandingFaq) => {
    setEditing(f)
    setForm({
      question: f.question,
      answer: f.answer,
      sortOrder: f.sortOrder ?? 0,
      isActive: f.isActive ?? true,
    })
    setFormError("")
    setDialogOpen(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setFormError("")
    const payload: CreateLandingFaqData = {
      question: form.question.trim(),
      answer: form.answer.trim(),
      sortOrder: form.sortOrder,
      isActive: form.isActive,
    }
    if (!payload.question || !payload.answer) {
      setFormError("Question and answer are required.")
      return
    }
    setSaving(true)
    try {
      if (editing) {
        const res = await updateLandingFaq(editing.id, payload)
        if (res.success) {
          toast({ title: "FAQ updated" })
          setDialogOpen(false)
          mutate()
        } else {
          setFormError(res.message ?? "Update failed")
        }
      } else {
        const res = await createLandingFaq(payload)
        if (res.success) {
          toast({ title: "FAQ added" })
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
      const res = await deleteLandingFaq(deleteTarget.id)
      if (res.success) {
        toast({ title: "FAQ deleted" })
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
          <h1 className="text-2xl font-bold text-foreground">Landing FAQ</h1>
          <p className="text-muted-foreground">Manage FAQs shown on the public landing page</p>
        </div>
        <Button onClick={openCreate} className="gap-2">
          <Plus className="h-4 w-4" />
          Add FAQ
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <HelpCircle className="h-5 w-5" />
            Landing page FAQs
          </CardTitle>
          <CardDescription>Only active FAQs appear on the landing page. Order by sort order.</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center py-8">
              <span className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
            </div>
          ) : error ? (
            <p className="text-destructive py-4">Failed to load FAQs.</p>
          ) : faqs.length === 0 ? (
            <p className="text-muted-foreground py-8 text-center">
              No FAQs yet. Add one to show on the landing page (or the default set will be shown).
            </p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Question</TableHead>
                  <TableHead className="max-w-md">Answer</TableHead>
                  <TableHead className="w-20">Order</TableHead>
                  <TableHead className="w-24">Active</TableHead>
                  <TableHead className="w-32 text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {faqs.map((f) => (
                  <TableRow key={f.id}>
                    <TableCell className="font-medium">{f.question}</TableCell>
                    <TableCell className="max-w-md truncate text-muted-foreground">{f.answer}</TableCell>
                    <TableCell>{f.sortOrder}</TableCell>
                    <TableCell>{f.isActive ? "Yes" : "No"}</TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Button variant="ghost" size="sm" onClick={() => openEdit(f)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive" onClick={() => setDeleteTarget(f)}>
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
            <DialogTitle>{editing ? "Edit FAQ" : "Add FAQ"}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="question">Question</Label>
              <Input
                id="question"
                value={form.question}
                onChange={(e) => setForm((f) => ({ ...f, question: e.target.value }))}
                placeholder="How does the WhatsApp integration work?"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="answer">Answer</Label>
              <Textarea
                id="answer"
                value={form.answer}
                onChange={(e) => setForm((f) => ({ ...f, answer: e.target.value }))}
                placeholder="We use the official WhatsApp Business API..."
                rows={4}
              />
            </div>
            <div className="flex items-center gap-4">
              <div className="space-y-2 w-24">
                <Label htmlFor="sortOrder">Order</Label>
                <Input
                  id="sortOrder"
                  type="number"
                  min={0}
                  value={form.sortOrder}
                  onChange={(e) => setForm((f) => ({ ...f, sortOrder: parseInt(e.target.value, 10) || 0 }))}
                />
              </div>
              <div className="flex items-center gap-2 pt-6">
                <Switch
                  id="isActive"
                  checked={form.isActive}
                  onCheckedChange={(checked) => setForm((f) => ({ ...f, isActive: !!checked }))}
                />
                <Label htmlFor="isActive">Show on landing</Label>
              </div>
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
            <AlertDialogTitle>Delete FAQ?</AlertDialogTitle>
            <AlertDialogDescription>
              This will remove this question from the landing page. You can add it again later.
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
