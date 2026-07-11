"use client"

import { useState, useEffect } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible"
import { ChevronDown, Layout, Save, Upload, ExternalLink, GripVertical } from "lucide-react"
import { useAdminCmsPage } from "@/lib/api-hooks"
import { updateCmsPage, updateCmsSection, uploadCmsImage, reorderCmsSections } from "@/lib/api-actions"
import { useToast } from "@/hooks/use-toast"
import type { AdminCmsSection } from "@/components/lando/types"
import type { CmsLink } from "@/components/lando/types"
import { LinkListEditor } from "@/components/lando/link-list-editor"
import { cn } from "@/lib/utils"

const PAGE_SLUGS = [
  { slug: "global", label: "Global (Nav & Footer)" },
  { slug: "home", label: "Home" },
  { slug: "pricing", label: "Pricing" },
  { slug: "about", label: "About" },
  { slug: "contact", label: "Contact" },
  { slug: "privacy", label: "Privacy" },
  { slug: "terms", label: "Terms" },
]

function Field({
  label,
  value,
  onChange,
  multiline = false,
}: {
  label: string
  value: string
  onChange: (v: string) => void
  multiline?: boolean
}) {
  return (
    <div className="space-y-1.5">
      <Label className="text-xs">{label}</Label>
      {multiline ? (
        <Textarea value={value} onChange={(e) => onChange(e.target.value)} rows={3} className="text-sm" />
      ) : (
        <Input value={value} onChange={(e) => onChange(e.target.value)} className="text-sm" />
      )}
    </div>
  )
}

function ImageField({
  label,
  value,
  onChange,
}: {
  label: string
  value: string
  onChange: (v: string) => void
}) {
  const { toast } = useToast()
  const [uploading, setUploading] = useState(false)

  const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    setUploading(true)
    const res = await uploadCmsImage(file)
    setUploading(false)
    if (res.success && res.url) {
      onChange(res.url)
      toast({ title: "Image uploaded" })
    } else {
      toast({ title: res.message ?? "Upload failed", variant: "destructive" })
    }
  }

  return (
    <div className="space-y-1.5">
      <Label className="text-xs">{label}</Label>
      <div className="flex gap-2">
        <Input value={value} onChange={(e) => onChange(e.target.value)} className="text-sm" placeholder="/images/..." />
        <Button type="button" variant="outline" size="sm" disabled={uploading} asChild>
          <label className="cursor-pointer">
            <Upload className="h-4 w-4" />
            <input type="file" accept="image/*" className="hidden" onChange={handleUpload} />
          </label>
        </Button>
      </div>
      {value && <img src={value} alt="" className="mt-2 h-20 rounded border object-cover" />}
    </div>
  )
}

function SectionEditor({
  slug,
  section,
  onSaved,
  dragHandle,
}: {
  slug: string
  section: AdminCmsSection
  onSaved: () => void
  dragHandle?: React.ReactNode
}) {
  const { toast } = useToast()
  const [open, setOpen] = useState(false)
  const [enabled, setEnabled] = useState(section.isEnabled)
  const [content, setContent] = useState<Record<string, unknown>>(section.content ?? {})
  const [saving, setSaving] = useState(false)

  const set = (key: string, value: unknown) => setContent((c) => ({ ...c, [key]: value }))
  const str = (key: string) => String(content[key] ?? "")

  const save = async () => {
    setSaving(true)
    const res = await updateCmsSection(slug, section.key, { isEnabled: enabled, content })
    setSaving(false)
    if (res.success) {
      toast({ title: `${section.label} saved` })
      onSaved()
    } else {
      toast({ title: res.message ?? "Save failed", variant: "destructive" })
    }
  }

  const toggleEnabled = async (checked: boolean) => {
    setEnabled(checked)
    await updateCmsSection(slug, section.key, { isEnabled: checked })
    onSaved()
  }

  const renderFields = () => {
    const key = section.key

    if (key === "navbar") {
      const navLinks = (content.links as CmsLink[]) ?? []
      return (
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Login label" value={str("loginLabel")} onChange={(v) => set("loginLabel", v)} />
          <Field label="Login link" value={str("loginHref")} onChange={(v) => set("loginHref", v)} />
          <Field label="Sign up label" value={str("signupLabel")} onChange={(v) => set("signupLabel", v)} />
          <Field label="Sign up link" value={str("signupHref")} onChange={(v) => set("signupHref", v)} />
          <div className="sm:col-span-2">
            <LinkListEditor
              label="Navigation links"
              links={navLinks}
              onChange={(links) => set("links", links)}
            />
          </div>
        </div>
      )
    }

