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

    if (key === "footer") {
      return (
        <div className="space-y-4">
          <Field label="Copyright" value={str("copyright")} onChange={(v) => set("copyright", v)} multiline />
          <LinkListEditor
            label="Site links"
            links={(content.navLinks as CmsLink[]) ?? []}
            onChange={(links) => set("navLinks", links)}
          />
          <LinkListEditor
            label="Social links"
            links={(content.socialLinks as CmsLink[]) ?? []}
            onChange={(links) => set("socialLinks", links)}
          />
          <LinkListEditor
            label="Legal links"
            links={(content.legalLinks as CmsLink[]) ?? []}
            onChange={(links) => set("legalLinks", links)}
          />
        </div>
      )
    }

    if (key === "auth_shell") {
      return (
        <div className="grid gap-3 sm:grid-cols-2">
          <ImageField label="Auth illustration" value={str("imageUrl")} onChange={(v) => set("imageUrl", v)} />
          <Field label="Image alt text" value={str("imageAlt")} onChange={(v) => set("imageAlt", v)} />
        </div>
      )
    }

    if (key === "hero" && slug === "contact") {
      return (
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Title" value={str("title")} onChange={(v) => set("title", v)} />
          <Field label="Description" value={str("description")} onChange={(v) => set("description", v)} multiline />
          <ImageField label="Image" value={str("imageUrl")} onChange={(v) => set("imageUrl", v)} />
          <Field label="Submit button text" value={str("submitText")} onChange={(v) => set("submitText", v)} />
          <Field label="Success message" value={str("successMessage")} onChange={(v) => set("successMessage", v)} />
        </div>
      )
    }

    if (key === "hero") {
      return (
        <div className="grid gap-3 sm:grid-cols-2">
          {slug === "home" && (
            <Field label="Kicker" value={str("kicker")} onChange={(v) => set("kicker", v)} />
          )}
          <Field label="Title" value={str("title")} onChange={(v) => set("title", v)} />
          <Field label="Description" value={str("description")} onChange={(v) => set("description", v)} multiline />
          <Field label="Primary CTA text" value={str("primaryCtaText")} onChange={(v) => set("primaryCtaText", v)} />
          <Field label="Primary CTA link" value={str("primaryCtaHref")} onChange={(v) => set("primaryCtaHref", v)} />
          {slug === "home" && (
            <>
              <Field label="Secondary CTA text" value={str("secondaryCtaText")} onChange={(v) => set("secondaryCtaText", v)} />
              <Field label="Secondary CTA link" value={str("secondaryCtaHref")} onChange={(v) => set("secondaryCtaHref", v)} />
            </>
          )}
          <ImageField label="Image" value={str("imageUrl")} onChange={(v) => set("imageUrl", v)} />
        </div>
      )
    }

    if (key === "trusted_companies") {
      return (
        <div className="space-y-3">
          <Field label="Section title" value={str("title")} onChange={(v) => set("title", v)} multiline />
          <Field
            label="Companies (one per line)"
            value={((content.companies as Array<{ name: string }>) ?? []).map((c) => c.name).join("\n")}
            onChange={(v) =>
              set(
                "companies",
                v.split("\n").filter(Boolean).map((name) => ({ name: name.trim(), logoUrl: "" }))
              )
            }
            multiline
          />
        </div>
      )
    }

    if (key === "intro_card" || key === "cta") {
      return (
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Title" value={str("title")} onChange={(v) => set("title", v)} />
          <Field label="Description" value={str("description")} onChange={(v) => set("description", v)} multiline />
          <Field label="Button text" value={str("ctaText")} onChange={(v) => set("ctaText", v)} />
          <Field label="Button link" value={str("ctaHref")} onChange={(v) => set("ctaHref", v)} />
          {key === "cta" && slug === "home" && (
            <ImageField label="Image" value={str("imageUrl")} onChange={(v) => set("imageUrl", v)} />
          )}
          {key === "intro_card" && (
            <ImageField label="Image" value={str("imageUrl")} onChange={(v) => set("imageUrl", v)} />
          )}
        </div>
      )
    }

    if (key.startsWith("feature_")) {
      return (
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Label" value={str("label")} onChange={(v) => set("label", v)} />
          <Field label="Title" value={str("title")} onChange={(v) => set("title", v)} />
          <Field label="Description" value={str("description")} onChange={(v) => set("description", v)} multiline />
          <Field label="Button text" value={str("ctaText")} onChange={(v) => set("ctaText", v)} />
          <Field label="Button link" value={str("ctaHref")} onChange={(v) => set("ctaHref", v)} />
          <Field label="Image position (left/right)" value={str("imagePosition")} onChange={(v) => set("imagePosition", v)} />
          <ImageField label="Image" value={str("imageUrl")} onChange={(v) => set("imageUrl", v)} />
        </div>
      )
    }

    if (key === "how_to_join") {
      const steps = (content.steps as Array<{ title: string; description: string }>) ?? []
      return (
        <div className="space-y-3">
          <Field label="Title" value={str("title")} onChange={(v) => set("title", v)} />
          <Field label="Description" value={str("description")} onChange={(v) => set("description", v)} />
          <Field label="Button text" value={str("ctaText")} onChange={(v) => set("ctaText", v)} />
          <Field label="Button link" value={str("ctaHref")} onChange={(v) => set("ctaHref", v)} />
          <ImageField label="Image" value={str("imageUrl")} onChange={(v) => set("imageUrl", v)} />
          {steps.map((step, i) => (
            <div key={i} className="rounded border p-3 space-y-2">
              <Field
                label={`Step ${i + 1} title`}
                value={step.title}
                onChange={(v) => {
                  const next = [...steps]
                  next[i] = { ...next[i], title: v }
                  set("steps", next)
                }}
              />
              <Field
                label={`Step ${i + 1} description`}
                value={step.description}
                onChange={(v) => {
                  const next = [...steps]
                  next[i] = { ...next[i], description: v }
                  set("steps", next)
                }}
                multiline
              />
            </div>
          ))}
        </div>
      )
    }

    if (key === "testimonials" || key === "faq" || key === "pricing_plans") {
      return (
        <p className="text-sm text-muted-foreground">
          {key === "testimonials" && "Manage testimonial cards in Admin → Testimonials."}
          {key === "faq" && "Manage FAQ items in Admin → Landing FAQ."}
          {key === "pricing_plans" && "Manage plan cards in Admin → Plans."}
        </p>
      )
    }

    if (key === "compare_features") {
      return (
        <Field
          label="Feature columns (JSON)"
          value={JSON.stringify(content.columns ?? [], null, 2)}
          onChange={(v) => {
            try {
              set("columns", JSON.parse(v))
            } catch {
              /* ignore invalid json while typing */
            }
          }}
          multiline
        />
      )
    }

    if (key === "mission") {
      return (
        <div className="space-y-3">
          <Field label="Title" value={str("title")} onChange={(v) => set("title", v)} />
          <Field label="Description" value={str("description")} onChange={(v) => set("description", v)} multiline />
        </div>
      )
    }

    if (key === "efficiency") {
      return (
        <Field
          label="Title (use new lines for stacked text)"
          value={str("title")}
          onChange={(v) => set("title", v)}
          multiline
        />
      )
    }

    if (key === "legal_content") {
      return (
        <div className="space-y-3">
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Title" value={str("title")} onChange={(v) => set("title", v)} />
            <Field label="Last updated" value={str("lastUpdated")} onChange={(v) => set("lastUpdated", v)} />
          </div>
          <div className="space-y-1.5">
            <Label className="text-xs">Body (HTML — use &lt;h2&gt;, &lt;p&gt;, &lt;ul&gt;&lt;li&gt;)</Label>
            <Textarea
              value={str("body")}
              onChange={(e) => set("body", e.target.value)}
              rows={16}
              className="font-mono text-xs"
            />
            <p className="text-xs text-muted-foreground">
              Supports basic HTML tags. Leave empty to show the built-in default content.
            </p>
          </div>
        </div>
      )
    }

    if (key === "team") {
      const members = (content.members as Array<{ name: string; role: string; imageUrl?: string }>) ?? []
      return (
        <div className="space-y-3">
          <Field label="Title" value={str("title")} onChange={(v) => set("title", v)} />
          <Field label="Description" value={str("description")} onChange={(v) => set("description", v)} />
          {members.map((m, i) => (
            <div key={i} className="rounded border p-3 grid gap-2 sm:grid-cols-2">
              <Field
                label="Name"
                value={m.name}
                onChange={(v) => {
                  const next = [...members]
                  next[i] = { ...next[i], name: v }
                  set("members", next)
                }}
              />
              <Field
                label="Role"
                value={m.role}
                onChange={(v) => {
                  const next = [...members]
                  next[i] = { ...next[i], role: v }
                  set("members", next)
                }}
              />
              <ImageField
                label="Photo"
                value={m.imageUrl ?? ""}
                onChange={(v) => {
                  const next = [...members]
                  next[i] = { ...next[i], imageUrl: v }
                  set("members", next)
                }}
              />
            </div>
          ))}
