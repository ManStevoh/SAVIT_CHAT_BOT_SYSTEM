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
import { Plus, Pencil, Trash2, Newspaper, Upload, ExternalLink } from "lucide-react"
import { useAdminBlogPosts, type AdminBlogPost } from "@/lib/api-hooks"
import {
  createBlogPost,
  updateBlogPost,
  deleteBlogPost,
  uploadCmsImage,
  type BlogPostPayload,
} from "@/lib/api-actions"
import { useToast } from "@/hooks/use-toast"

const defaultForm: BlogPostPayload & { coverImage: string; ogImage: string; slug: string } = {
  title: "",
  slug: "",
  excerpt: "",
  body: "",
  coverImage: "",
  metaTitle: "",
  metaDescription: "",
  ogImage: "",
  publishedAt: "",
  isPublished: false,
}

export default function AdminBlogPage() {
  const { data: posts = [], isLoading, mutate } = useAdminBlogPosts()
  const { toast } = useToast()
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<AdminBlogPost | null>(null)
  const [form, setForm] = useState(defaultForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState("")
  const [deleteTarget, setDeleteTarget] = useState<AdminBlogPost | null>(null)
  const [deleting, setDeleting] = useState(false)
  const [uploading, setUploading] = useState<"cover" | "og" | null>(null)

  const openCreate = () => {
    setEditing(null)
    setForm(defaultForm)
    setFormError("")
    setDialogOpen(true)
  }

  const openEdit = (p: AdminBlogPost) => {
    setEditing(p)
    setForm({
      title: p.title,
      slug: p.slug,
      excerpt: p.excerpt ?? "",
      body: p.body,
      coverImage: p.coverImageRaw ?? p.coverImage ?? "",
      metaTitle: p.metaTitle ?? "",
      metaDescription: p.metaDescription ?? "",
      ogImage: p.ogImageRaw ?? p.ogImage ?? "",
      publishedAt: p.publishedAt ? p.publishedAt.slice(0, 16) : "",
      isPublished: p.isPublished,
    })
    setFormError("")
    setDialogOpen(true)
  }

  const uploadImage = async (file: File, field: "coverImage" | "ogImage") => {
    if (file.size > 10 * 1024 * 1024) {
      toast({
        title: "Image too large",
        description: "Max 10 MB. Compress the image or use a smaller file.",
        variant: "destructive",
      })
      return
    }
    setUploading(field === "coverImage" ? "cover" : "og")
    const res = await uploadCmsImage(file)
    setUploading(null)
    if (res.success && res.url) {
      setForm((f) => ({ ...f, [field]: res.url }))
      toast({ title: "Image uploaded" })
    } else {
      toast({ title: res.message ?? "Upload failed", variant: "destructive" })
    }
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setFormError("")
    if (!form.title.trim() || !form.body.trim()) {
      setFormError("Title and body are required.")
      return
    }
    const payload: BlogPostPayload = {
      title: form.title.trim(),
      slug: form.slug.trim() || undefined,
      excerpt: form.excerpt.trim() || undefined,
      body: form.body,
      coverImage: form.coverImage || null,
      metaTitle: form.metaTitle.trim() || null,
      metaDescription: form.metaDescription.trim() || null,
      ogImage: form.ogImage || null,
      publishedAt: form.publishedAt || null,
      isPublished: form.isPublished,
    }
    setSaving(true)
    try {
      const res = editing
        ? await updateBlogPost(editing.id, payload)
        : await createBlogPost(payload)
      if (res.success) {
        toast({ title: editing ? "Post updated" : "Post created" })
        setDialogOpen(false)
        mutate()
      } else {
        setFormError(res.message ?? "Save failed")
      }
    } catch {
      setFormError("Request failed")
    } finally {
      setSaving(false)
    }
  }

  const confirmDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    const res = await deleteBlogPost(deleteTarget.id)
    setDeleting(false)
    if (res.success) {
      toast({ title: "Post deleted" })
      setDeleteTarget(null)
      mutate()
    } else {
      toast({ title: res.message ?? "Delete failed", variant: "destructive" })
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
            <Newspaper className="h-6 w-6" />
            Blog
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Publish SEO articles with cover images, meta copy, and Open Graph previews.
          </p>
        </div>
        <Button onClick={openCreate}>
          <Plus className="mr-1 h-4 w-4" />
          New post
        </Button>
      </div>

      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="text-base">Posts</CardTitle>
          <CardDescription>Drafts stay hidden until you publish them.</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : posts.length === 0 ? (
            <p className="text-sm text-muted-foreground">No posts yet.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Title</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Published</TableHead>
                  <TableHead className="w-[120px]" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {posts.map((p) => (
                  <TableRow key={p.id}>
                    <TableCell>
                      <div className="font-medium">{p.title}</div>
                      <div className="text-xs text-muted-foreground">/blog/{p.slug}</div>
                    </TableCell>
                    <TableCell>{p.isPublished ? "Published" : "Draft"}</TableCell>
                    <TableCell className="text-xs text-muted-foreground">
                      {p.publishedAt ? new Date(p.publishedAt).toLocaleDateString() : "—"}
                    </TableCell>
                    <TableCell className="space-x-1 text-right">
                      {p.isPublished && (
                        <Button size="icon" variant="ghost" asChild>
                          <a href={`/blog/${p.slug}`} target="_blank" rel="noopener noreferrer">
                            <ExternalLink className="h-4 w-4" />
                          </a>
                        </Button>
                      )}
                      <Button size="icon" variant="ghost" onClick={() => openEdit(p)}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                      <Button size="icon" variant="ghost" onClick={() => setDeleteTarget(p)}>
                        <Trash2 className="h-4 w-4 text-destructive" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{editing ? "Edit post" : "New post"}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5 sm:col-span-2">
                <Label>Title</Label>
                <Input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
              </div>
              <div className="space-y-1.5">
                <Label>Slug (optional)</Label>
                <Input value={form.slug} onChange={(e) => setForm({ ...form, slug: e.target.value })} placeholder="auto-from-title" />
              </div>
              <div className="space-y-1.5">
                <Label>Publish date</Label>
                <Input
                  type="datetime-local"
                  value={form.publishedAt ?? ""}
                  onChange={(e) => setForm({ ...form, publishedAt: e.target.value })}
                />
              </div>
              <div className="space-y-1.5 sm:col-span-2">
                <Label>Excerpt</Label>
                <Textarea rows={2} value={form.excerpt ?? ""} onChange={(e) => setForm({ ...form, excerpt: e.target.value })} />
              </div>
              <div className="space-y-1.5 sm:col-span-2">
                <Label>Body (HTML)</Label>
                <Textarea
                  rows={12}
                  className="font-mono text-xs"
                  value={form.body}
                  onChange={(e) => setForm({ ...form, body: e.target.value })}
                  placeholder="<p>Write your article…</p>"
                />
              </div>
              <div className="space-y-1.5 sm:col-span-2">
                <Label>Cover image</Label>
                <div className="flex gap-2">
                  <Input value={form.coverImage ?? ""} onChange={(e) => setForm({ ...form, coverImage: e.target.value })} />
                  <Button type="button" variant="outline" size="sm" disabled={uploading === "cover"} asChild>
                    <label className="cursor-pointer">
                      <Upload className="h-4 w-4" />
                      <input
                        type="file"
                        accept="image/*"
                        className="hidden"
                        onChange={(e) => {
                          const f = e.target.files?.[0]
                          if (f) void uploadImage(f, "coverImage")
                        }}
                      />
                    </label>
                  </Button>
                </div>
              </div>
              <div className="space-y-1.5">
                <Label>Meta title</Label>
                <Input value={form.metaTitle ?? ""} onChange={(e) => setForm({ ...form, metaTitle: e.target.value })} />
              </div>
              <div className="space-y-1.5">
                <Label>OG image URL</Label>
                <div className="flex gap-2">
                  <Input value={form.ogImage ?? ""} onChange={(e) => setForm({ ...form, ogImage: e.target.value })} />
                  <Button type="button" variant="outline" size="sm" disabled={uploading === "og"} asChild>
                    <label className="cursor-pointer">
                      <Upload className="h-4 w-4" />
                      <input
                        type="file"
                        accept="image/*"
                        className="hidden"
                        onChange={(e) => {
                          const f = e.target.files?.[0]
                          if (f) void uploadImage(f, "ogImage")
                        }}
                      />
                    </label>
                  </Button>
                </div>
              </div>
              <div className="space-y-1.5 sm:col-span-2">
                <Label>Meta description</Label>
                <Textarea rows={2} value={form.metaDescription ?? ""} onChange={(e) => setForm({ ...form, metaDescription: e.target.value })} />
              </div>
              <div className="flex items-center justify-between rounded-lg border p-3 sm:col-span-2">
                <div>
                  <p className="text-sm font-medium">Published</p>
                  <p className="text-xs text-muted-foreground">Live on /blog and in the sitemap</p>
                </div>
                <Switch checked={!!form.isPublished} onCheckedChange={(v) => setForm({ ...form, isPublished: v })} />
              </div>
            </div>
            {formError && <p className="text-sm text-destructive">{formError}</p>}
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={saving}>
                {saving ? "Saving…" : "Save post"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!deleteTarget} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete post?</AlertDialogTitle>
            <AlertDialogDescription>
              This permanently removes “{deleteTarget?.title}”.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={confirmDelete} disabled={deleting}>
              {deleting ? "Deleting…" : "Delete"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
