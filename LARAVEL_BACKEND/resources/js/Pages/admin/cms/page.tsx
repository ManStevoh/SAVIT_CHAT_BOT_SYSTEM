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
  hint,
}: {
  label: string
  value: string
  onChange: (v: string) => void
  multiline?: boolean
  hint?: string
}) {
  return (
    <div className="space-y-1.5">
      <Label className="text-xs">{label}</Label>
      {multiline ? (
        <Textarea value={value} onChange={(e) => onChange(e.target.value)} rows={3} className="text-sm" />
      ) : (
        <Input value={value} onChange={(e) => onChange(e.target.value)} className="text-sm" />
      )}
      {hint ? <p className="text-[11px] text-muted-foreground">{hint}</p> : null}
    </div>
  )
}

function ImageField({
  label,
  value,
  onChange,
  altValue,
  onAltChange,
}: {
  label: string
  value: string
  onChange: (v: string) => void
  altValue?: string
  onAltChange?: (v: string) => void
}) {
  const { toast } = useToast()
  const [uploading, setUploading] = useState(false)

  const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    const maxBytes = 10 * 1024 * 1024
    if (file.size > maxBytes) {
      toast({
        title: "Image too large",
        description: "Max 10 MB. Compress the image or export a smaller JPEG/WebP, then try again.",
        variant: "destructive",
      })
      e.target.value = ""
      return
    }
    setUploading(true)
    const res = await uploadCmsImage(file)
    setUploading(false)
    e.target.value = ""
    if (res.success && res.url) {
      onChange(res.url)
      toast({ title: "Image uploaded" })
    } else {
      toast({ title: res.message ?? "Upload failed", variant: "destructive" })
    }
  }

  return (
    <div className="space-y-1.5 sm:col-span-2">
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
      {onAltChange ? (
        <Input
          value={altValue ?? ""}
          onChange={(e) => onAltChange(e.target.value)}
          className="text-sm"
          placeholder="Image alt text (SEO / accessibility)"
        />
      ) : null}
      {value && <img src={value} alt={altValue || ""} className="mt-2 h-20 rounded border object-cover" />}
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
          <Field
            label="Copyright"
            value={str("copyright")}
            onChange={(v) => set("copyright", v)}
            multiline
            hint="Example: 2026 © Essem Digital Innovation Limited. RelayIQ is a product of Essem Digital Innovation Limited. All rights reserved."
          />
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

          <div className="rounded-lg border border-border p-4 space-y-4">
            <div className="flex items-center justify-between gap-3">
              <div>
                <p className="text-sm font-medium">Mobile app section</p>
                <p className="text-xs text-muted-foreground">
                  Show Google Play / App Store links in the site footer. Turn off until your apps are live.
                </p>
              </div>
              <Switch
                checked={content.showMobileApp === true}
                onCheckedChange={(v) => set("showMobileApp", v)}
              />
            </div>
            {content.showMobileApp === true && (
              <div className="grid gap-3 sm:grid-cols-2">
                <Field
                  label="Section title"
                  value={str("mobileAppTitle")}
                  onChange={(v) => set("mobileAppTitle", v)}
                  hint='Default: "Get the mobile app"'
                />
                <Field
                  label="Short description"
                  value={str("mobileAppDescription")}
                  onChange={(v) => set("mobileAppDescription", v)}
                  multiline
                />
                <Field
                  label="Google Play URL"
                  value={str("playStoreUrl")}
                  onChange={(v) => set("playStoreUrl", v)}
                  hint="Leave empty to hide the Play badge (shows Coming soon if both empty)."
                />
                <Field
                  label="App Store URL"
                  value={str("appStoreUrl")}
                  onChange={(v) => set("appStoreUrl", v)}
                  hint="Leave empty to hide the App Store badge."
                />
              </div>
            )}
          </div>
        </div>
      )
    }

    if (key === "auth_shell") {
      return (
        <div className="grid gap-3 sm:grid-cols-2">
          <ImageField
            label="Auth illustration"
            value={str("imageUrl")}
            onChange={(v) => set("imageUrl", v)}
            altValue={str("imageAlt")}
            onAltChange={(v) => set("imageAlt", v)}
          />
        </div>
      )
    }

    if (key === "hero" && slug === "contact") {
      return (
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Title" value={str("title")} onChange={(v) => set("title", v)} />
          <Field label="Description" value={str("description")} onChange={(v) => set("description", v)} multiline />
          <ImageField
            label="Image"
            value={str("imageUrl")}
            onChange={(v) => set("imageUrl", v)}
            altValue={str("imageAlt")}
            onAltChange={(v) => set("imageAlt", v)}
          />
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
              <div className="flex items-center justify-between rounded-lg border p-3 sm:col-span-2">
                <div>
                  <p className="text-sm font-medium">Animated flow simulation</p>
                  <p className="text-xs text-muted-foreground">Show live WhatsApp demo instead of hero image</p>
                </div>
                <Switch
                  checked={content.showFlowSimulation === true}
                  onCheckedChange={(v) => set("showFlowSimulation", v)}
                />
              </div>
            </>
          )}
          <ImageField
            label="Image (fallback)"
            value={str("imageUrl")}
            onChange={(v) => set("imageUrl", v)}
            altValue={str("imageAlt")}
            onAltChange={(v) => set("imageAlt", v)}
          />
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

    if (key === "intro_card" || key === "cta" || key === "growth_engine") {
      return (
        <div className="grid gap-3 sm:grid-cols-2">
          {key === "growth_engine" && (
            <Field label="Label" value={str("label")} onChange={(v) => set("label", v)} />
          )}
          <Field label="Title" value={str("title")} onChange={(v) => set("title", v)} />
          <Field label="Description" value={str("description")} onChange={(v) => set("description", v)} multiline />
          <Field label="Button text" value={str("ctaText")} onChange={(v) => set("ctaText", v)} />
          <Field label="Button link" value={str("ctaHref")} onChange={(v) => set("ctaHref", v)} />
          {key === "growth_engine" && (
            <Field
              label="Bullet points (one per line)"
              value={((content.points as string[]) ?? []).join("\n")}
              onChange={(v) =>
                set(
                  "points",
                  v.split("\n").map((line) => line.trim()).filter(Boolean)
                )
              }
              multiline
            />
          )}
          {(key === "cta" && slug === "home") || key === "intro_card" || key === "growth_engine" ? (
            <ImageField
              label="Image"
              value={str("imageUrl")}
              onChange={(v) => set("imageUrl", v)}
              altValue={str("imageAlt")}
              onAltChange={(v) => set("imageAlt", v)}
            />
          ) : null}
        </div>
      )
    }

    if (key === "capabilities") {
      const items = (content.items as Array<{ title: string; description?: string; icon?: string }>) ?? []
      return (
        <div className="space-y-3">
          <Field label="Title" value={str("title")} onChange={(v) => set("title", v)} />
          <Field label="Description" value={str("description")} onChange={(v) => set("description", v)} multiline />
          {items.map((item, i) => (
            <div key={i} className="rounded border p-3 space-y-2">
              <Field
                label={`Item ${i + 1} icon (bot, package, payment, booking, growth, inbox, mobile, sparkles)`}
                value={item.icon ?? ""}
                onChange={(v) => {
                  const next = [...items]
                  next[i] = { ...next[i], icon: v }
                  set("items", next)
                }}
              />
              <Field
                label={`Item ${i + 1} title`}
                value={item.title}
                onChange={(v) => {
                  const next = [...items]
                  next[i] = { ...next[i], title: v }
                  set("items", next)
                }}
              />
              <Field
                label={`Item ${i + 1} description`}
                value={item.description ?? ""}
                onChange={(v) => {
                  const next = [...items]
                  next[i] = { ...next[i], description: v }
                  set("items", next)
                }}
                multiline
              />
            </div>
          ))}
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => set("items", [...items, { title: "New capability", description: "", icon: "sparkles" }])}
          >
            Add capability
          </Button>
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
          <ImageField
            label="Image"
            value={str("imageUrl")}
            onChange={(v) => set("imageUrl", v)}
            altValue={str("imageAlt")}
            onAltChange={(v) => set("imageAlt", v)}
          />
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
          <ImageField
            label="Image"
            value={str("imageUrl")}
            onChange={(v) => set("imageUrl", v)}
            altValue={str("imageAlt")}
            onAltChange={(v) => set("imageAlt", v)}
          />
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
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Title" value={str("title")} onChange={(v) => set("title", v)} multiline />
          <Field label="Description" value={str("description")} onChange={(v) => set("description", v)} multiline />
          <Field label="CTA text" value={str("ctaText")} onChange={(v) => set("ctaText", v)} />
          <Field label="CTA link" value={str("ctaHref")} onChange={(v) => set("ctaHref", v)} />
        </div>
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
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => set("members", [...members, { name: "", role: "", imageUrl: "" }])}
          >
            Add team member
          </Button>
        </div>
      )
    }

    return (
      <Textarea
        value={JSON.stringify(content, null, 2)}
        onChange={(e) => {
          try {
            setContent(JSON.parse(e.target.value))
          } catch {
            /* ignore */
          }
        }}
        rows={8}
        className="font-mono text-xs"
      />
    )
  }

  return (
    <Collapsible open={open} onOpenChange={setOpen}>
      <div className="rounded-lg border">
        <div className="flex items-center justify-between gap-3 p-4">
          <div className="flex items-center gap-3 min-w-0">
            {dragHandle}
            <Switch checked={enabled} onCheckedChange={toggleEnabled} />
            <div className="min-w-0">
              <p className="font-medium truncate">{section.label}</p>
              <p className="text-xs text-muted-foreground">{section.key}</p>
            </div>
          </div>
          <CollapsibleTrigger asChild>
            <Button variant="ghost" size="sm">
              <ChevronDown className={cn("h-4 w-4 transition-transform", open && "rotate-180")} />
            </Button>
          </CollapsibleTrigger>
        </div>
        <CollapsibleContent>
          <div className="space-y-4 border-t p-4">
            {renderFields()}
            <Button onClick={save} disabled={saving} size="sm">
              <Save className="mr-1 h-4 w-4" />
              Save section
            </Button>
          </div>
        </CollapsibleContent>
      </div>
    </Collapsible>
  )
}

function SortableSectionList({
  slug,
  sections,
  onSaved,
}: {
  slug: string
  sections: AdminCmsSection[]
  onSaved: () => void
}) {
  const { toast } = useToast()
  const [items, setItems] = useState(sections)
  const [dragIndex, setDragIndex] = useState<number | null>(null)

  useEffect(() => {
    setItems(sections)
  }, [sections])

  const handleDrop = async (targetIndex: number) => {
    if (dragIndex === null || dragIndex === targetIndex) {
      setDragIndex(null)
      return
    }
    const next = [...items]
    const [moved] = next.splice(dragIndex, 1)
    next.splice(targetIndex, 0, moved)
    setItems(next)
    setDragIndex(null)

    const orders = next.map((s, i) => ({ key: s.key, sortOrder: i + 1 }))
    const res = await reorderCmsSections(slug, orders)
    if (res.success) {
      toast({ title: "Section order saved" })
      onSaved()
    } else {
      toast({ title: res.message ?? "Reorder failed", variant: "destructive" })
      setItems(sections)
    }
  }

  return (
    <div className="space-y-3">
      {items.map((section, index) => (
        <div
          key={section.id}
          draggable
          onDragStart={() => setDragIndex(index)}
          onDragOver={(e) => e.preventDefault()}
          onDrop={() => handleDrop(index)}
          className={cn(dragIndex === index && "opacity-50")}
        >
          <SectionEditor
            slug={slug}
            section={section}
            onSaved={onSaved}
            dragHandle={
              <button
                type="button"
                className="cursor-grab text-muted-foreground hover:text-foreground active:cursor-grabbing"
                aria-label="Drag to reorder"
              >
                <GripVertical className="h-4 w-4" />
              </button>
            }
          />
        </div>
      ))}
    </div>
  )
}

function PageEditor({ slug }: { slug: string }) {
  const { data, isLoading, mutate } = useAdminCmsPage(slug)
  const { toast } = useToast()
  const [metaTitle, setMetaTitle] = useState("")
  const [metaDescription, setMetaDescription] = useState("")
  const [ogImage, setOgImage] = useState("")
  const [ogTitle, setOgTitle] = useState("")
  const [ogDescription, setOgDescription] = useState("")
  const [canonicalUrl, setCanonicalUrl] = useState("")
  const [robots, setRobots] = useState("index, follow")
  const [isPublished, setIsPublished] = useState(true)

  useEffect(() => {
    if (data) {
      setMetaTitle(data.page.metaTitle ?? "")
      setMetaDescription(data.page.metaDescription ?? "")
      setOgImage(data.page.ogImage ?? "")
      setOgTitle(data.page.ogTitle ?? "")
      setOgDescription(data.page.ogDescription ?? "")
      setCanonicalUrl(data.page.canonicalUrl ?? "")
      setRobots(data.page.robots ?? "index, follow")
      setIsPublished(data.page.isPublished ?? true)
    }
  }, [
    data?.page.id,
    data?.page.metaTitle,
    data?.page.metaDescription,
    data?.page.ogImage,
    data?.page.ogTitle,
    data?.page.ogDescription,
    data?.page.canonicalUrl,
    data?.page.robots,
    data?.page.isPublished,
  ])

  const saveMeta = async () => {
    const res = await updateCmsPage(slug, {
      metaTitle,
      metaDescription,
      ogImage: ogImage || null,
      ogTitle: ogTitle || null,
      ogDescription: ogDescription || null,
      canonicalUrl: canonicalUrl || null,
      robots: robots || null,
      isPublished,
    })
    if (res.success) {
      toast({ title: "Page SEO saved" })
      mutate()
    } else {
      toast({ title: res.message ?? "Save failed", variant: "destructive" })
    }
  }

  const previewHref =
    slug === "global" ? "/" : slug === "home" ? "/" : `/${slug}`

  if (isLoading) return <p className="text-sm text-muted-foreground">Loading…</p>
  if (!data) return <p className="text-sm text-muted-foreground">Page not found.</p>

  const sections = [...data.sections].sort((a, b) => a.sortOrder - b.sortOrder)

  return (
    <div className="space-y-6">
      {slug !== "global" && (
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-base">Page SEO</CardTitle>
            <CardDescription>
              Search titles, descriptions, social share image, and indexing controls for this page.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="flex items-center justify-between rounded-lg border p-3">
              <div>
                <p className="text-sm font-medium">Published</p>
                <p className="text-xs text-muted-foreground">Unpublished pages are hidden from the public site and sitemap.</p>
              </div>
              <Switch checked={isPublished} onCheckedChange={setIsPublished} />
            </div>
            <Field label="Meta title" value={metaTitle} onChange={setMetaTitle} />
            <p className="text-[11px] text-muted-foreground -mt-2">{metaTitle.length}/60 recommended</p>
            <Field label="Meta description" value={metaDescription} onChange={setMetaDescription} multiline />
            <p className="text-[11px] text-muted-foreground -mt-2">{metaDescription.length}/155 recommended</p>
            <Field label="OG / social title (optional)" value={ogTitle} onChange={setOgTitle} />
            <Field label="OG / social description (optional)" value={ogDescription} onChange={setOgDescription} multiline />
            <ImageField label="SEO / Open Graph image" value={ogImage} onChange={setOgImage} />
            <p className="text-[11px] text-muted-foreground -mt-1">
              Recommended 1200×630px. Used when sharing on WhatsApp, Facebook, X, LinkedIn.
            </p>
            <Field label="Canonical URL (optional)" value={canonicalUrl} onChange={setCanonicalUrl} />
            <Field label="Robots" value={robots} onChange={setRobots} />
            <p className="text-[11px] text-muted-foreground -mt-2">Examples: index, follow · noindex, nofollow</p>
            <div className="flex gap-2">
              <Button size="sm" onClick={saveMeta}>
                Save SEO
              </Button>
              <Button size="sm" variant="outline" asChild>
                <a href={previewHref} target="_blank" rel="noopener noreferrer">
                  <ExternalLink className="mr-1 h-4 w-4" />
                  Preview page
                </a>
              </Button>
            </div>
          </CardContent>
        </Card>
      )}

      <div className="space-y-3">
        <h3 className="text-sm font-semibold">Sections</h3>
        <p className="text-xs text-muted-foreground">
          Drag sections to reorder. Toggle on/off and edit content below.
        </p>
        <SortableSectionList slug={slug} sections={sections} onSaved={() => mutate()} />
      </div>
    </div>
  )
}

export default function AdminCmsPage() {
  const [activeSlug, setActiveSlug] = useState("home")

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
          <Layout className="h-6 w-6" />
          Website CMS
        </h1>
        <p className="text-sm text-muted-foreground mt-1">
          Manage public pages: SEO write-ups, share images, content sections, and visibility.
        </p>
      </div>

      <Tabs value={activeSlug} onValueChange={setActiveSlug}>
        <TabsList className="flex flex-wrap h-auto gap-1">
          {PAGE_SLUGS.map((p) => (
            <TabsTrigger key={p.slug} value={p.slug} className="text-xs sm:text-sm">
              {p.label}
            </TabsTrigger>
          ))}
        </TabsList>
        {PAGE_SLUGS.map((p) => (
          <TabsContent key={p.slug} value={p.slug} className="mt-6">
            <PageEditor slug={p.slug} />
          </TabsContent>
        ))}
      </Tabs>
    </div>
  )
}
