"use client"

import { useCallback, useEffect, useState } from "react"
import Link from "next/link"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Badge } from "@/components/ui/badge"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Field, FieldLabel } from "@/components/ui/field"
import {
  createWhatsAppCampaign,
  generateWhatsAppCampaignCaption,
  getWhatsAppCampaign,
  getWhatsAppCampaignAudience,
  getWhatsAppCampaignGrowthPosts,
  getWhatsAppCampaignLimits,
  getWhatsAppStatus,
  listWhatsAppCampaigns,
  listWhatsAppTemplates,
  sendWhatsAppCampaignWizard,
  testWhatsAppCampaign,
  updateWhatsAppCampaign,
  uploadWhatsAppCampaignPoster,
  type WhatsAppCampaignGrowthPost,
  type WhatsAppCampaignRecord,
  type WhatsAppCampaignSegment,
  type WhatsAppTemplate,
} from "@/lib/api-actions"
import { resolveBackendMediaUrl } from "@/lib/api-client"
import { toast } from "sonner"
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  ImagePlus,
  Loader2,
  Megaphone,
  Send,
  Sparkles,
} from "lucide-react"

const STEPS = ["Creative", "Audience", "Template", "Send"] as const
const SEGMENTS: { id: WhatsAppCampaignSegment; label: string }[] = [
  { id: "all", label: "All customers" },
  { id: "recent", label: "Active (last 30 days)" },
  { id: "inactive", label: "Inactive (30+ days)" },
  { id: "ordered", label: "Customers with orders" },
]

export default function WhatsAppCampaignsPage() {
  const [step, setStep] = useState(0)
  const [waConnected, setWaConnected] = useState(false)
  const [limits, setLimits] = useState({ campaignsUsed: 0, campaignsLimit: 2, recipientsLimit: 100 })
  const [campaign, setCampaign] = useState<WhatsAppCampaignRecord | null>(null)
  const [history, setHistory] = useState<WhatsAppCampaignRecord[]>([])
  const [templates, setTemplates] = useState<WhatsAppTemplate[]>([])
  const [growthPosts, setGrowthPosts] = useState<WhatsAppCampaignGrowthPost[]>([])
  const [audienceCount, setAudienceCount] = useState(0)
  const [segment, setSegment] = useState<WhatsAppCampaignSegment>("all")
  const [caption, setCaption] = useState("")
  const [topic, setTopic] = useState("")
  const [templateName, setTemplateName] = useState("")
  const [testPhone, setTestPhone] = useState("")
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [generatingCaption, setGeneratingCaption] = useState(false)

  const refreshCampaign = useCallback(async (id: string) => {
    const c = await getWhatsAppCampaign(id)
    setCampaign(c)
    return c
  }, [])

  const loadInitial = useCallback(async () => {
    setLoading(true)
    try {
      const [status, lim, tpls, posts, hist] = await Promise.all([
        getWhatsAppStatus(),
        getWhatsAppCampaignLimits(),
        listWhatsAppTemplates(),
        getWhatsAppCampaignGrowthPosts(),
        listWhatsAppCampaigns(),
      ])
      setWaConnected(!!status.connected)
      setLimits(lim)
      setTemplates(tpls.filter((t) => t.status === "approved"))
      setGrowthPosts(posts)
      setHistory(hist)
    } catch {
      toast.error("Failed to load campaign data")
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadInitial()
  }, [loadInitial])

  useEffect(() => {
    getWhatsAppCampaignAudience(segment)
      .then((a) => setAudienceCount(a.uniqueCustomers))
      .catch(() => setAudienceCount(0))
  }, [segment])

  const ensureDraft = async (): Promise<WhatsAppCampaignRecord | null> => {
    if (campaign) return campaign
    const res = await createWhatsAppCampaign({ segment, caption: caption || undefined })
    if (!res.success || !res.campaign) {
      toast.error(res.message ?? "Could not create campaign draft")
      return null
    }
    setCampaign(res.campaign)
    return res.campaign
  }

  const handleUploadPoster = async (file: File) => {
    setBusy(true)
    const draft = await ensureDraft()
    if (!draft) {
      setBusy(false)
      return
    }
    const res = await uploadWhatsAppCampaignPoster(draft.id, file)
    setBusy(false)
    if (res.success && res.campaign) {
      setCampaign(res.campaign)
      toast.success("Poster uploaded")
    } else toast.error(res.message ?? "Upload failed")
  }

  const handlePickGrowthPost = async (post: WhatsAppCampaignGrowthPost) => {
    setBusy(true)
    const draft = await ensureDraft()
    if (!draft) {
      setBusy(false)
      return
    }
    const poster = post.mediaUrls[0]
    const res = await updateWhatsAppCampaign(draft.id, {
      socialPostId: post.id,
      caption: post.content,
      posterUrl: poster,
    })
    setBusy(false)
    if (res.success && res.campaign) {
      setCampaign(res.campaign)
      setCaption(res.campaign.caption ?? post.content)
      toast.success("Growth post linked")
    } else toast.error(res.message ?? "Failed to link post")
  }

  const handleGenerateCaption = async () => {
    setGeneratingCaption(true)
    const res = await generateWhatsAppCampaignCaption({ topic: topic || "promotion", posterHint: campaign?.posterUrl ? "promotional poster" : undefined })
    setGeneratingCaption(false)
    if (res.success && res.caption) {
      setCaption(res.caption)
      toast.success("Caption generated")
    } else toast.error(res.message ?? "Caption generation failed")
  }

  const saveStep = async (nextStep: number) => {
    setBusy(true)
    const draft = await ensureDraft()
    if (!draft) {
      setBusy(false)
      return
    }
    const res = await updateWhatsAppCampaign(draft.id, {
      segment,
      caption,
      templateName: templateName || undefined,
    })
    setBusy(false)
    if (res.success && res.campaign) {
      setCampaign(res.campaign)
      setStep(nextStep)
    } else toast.error(res.message ?? "Save failed")
  }

  const handleTestSend = async () => {
    if (!campaign || !testPhone) return
    setBusy(true)
    await updateWhatsAppCampaign(campaign.id, { caption, templateName })
    const res = await testWhatsAppCampaign(campaign.id, testPhone)
    setBusy(false)
    if (res.success) toast.success(res.message ?? "Test sent")
    else toast.error(res.message ?? "Test failed")
  }

  const handleSend = async () => {
    if (!campaign) return
    setBusy(true)
    await updateWhatsAppCampaign(campaign.id, { segment, caption, templateName })
    const res = await sendWhatsAppCampaignWizard(campaign.id)
    setBusy(false)
    if (res.success) {
      toast.success(res.message ?? "Campaign queued")
      if (res.campaign) setCampaign(res.campaign)
      setStep(3)
      listWhatsAppCampaigns().then(setHistory).catch(() => {})
    } else toast.error(res.message ?? "Send failed")
  }

  const posterSrc = campaign?.posterUrl ? resolveBackendMediaUrl(campaign.posterUrl) ?? campaign.posterUrl : null

  if (loading) {
    return (
      <div className="flex items-center justify-center py-24 text-muted-foreground">
        <Loader2 className="h-6 w-6 animate-spin mr-2" /> Loading campaigns…
      </div>
    )
  }

  if (!waConnected) {
    return (
      <div className="mx-auto max-w-lg space-y-4 py-12 text-center">
        <Megaphone className="mx-auto h-12 w-12 text-muted-foreground" />
        <h1 className="text-2xl font-bold">WhatsApp Campaigns</h1>
        <p className="text-muted-foreground">Connect WhatsApp in Settings before sending campaigns.</p>
        <Button asChild>
          <Link href="/dashboard/settings?tab=whatsapp">Connect WhatsApp</Link>
        </Button>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-4xl space-y-6 p-4 md:p-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold flex items-center gap-2">
            <Megaphone className="h-7 w-7 text-primary" />
            WhatsApp Campaigns
          </h1>
          <p className="text-muted-foreground mt-1">
            Poster + caption + segment + Meta template. {limits.campaignsUsed}/{limits.campaignsLimit} campaigns this month.
          </p>
        </div>
        <Badge variant="outline">Max {limits.recipientsLimit} recipients / campaign</Badge>
      </div>

      <div className="flex gap-2 flex-wrap">
        {STEPS.map((label, i) => (
          <Badge key={label} variant={step === i ? "default" : step > i ? "secondary" : "outline"}>
            {i + 1}. {label}
          </Badge>
        ))}
      </div>

      {step === 0 && (
        <Card>
          <CardHeader>
            <CardTitle>Creative</CardTitle>
            <CardDescription>Upload a poster, pick from Growth, and write or AI-generate a caption.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="flex flex-wrap gap-4 items-start">
              {posterSrc ? (
                <img src={posterSrc} alt="Poster" className="h-40 w-40 rounded-lg border object-cover" />
              ) : (
                <div className="h-40 w-40 rounded-lg border border-dashed flex items-center justify-center text-muted-foreground text-sm text-center p-2">
                  No poster yet
                </div>
              )}
              <div className="space-y-3 flex-1 min-w-[200px]">
                <label className="inline-flex">
                  <input type="file" accept="image/*" className="hidden" onChange={(e) => { const f = e.target.files?.[0]; if (f) handleUploadPoster(f) }} />
                  <Button type="button" variant="outline" disabled={busy} asChild>
                    <span><ImagePlus className="h-4 w-4 mr-2" />Upload poster</span>
                  </Button>
                </label>
                {growthPosts.filter((p) => p.mediaUrls.length > 0).slice(0, 5).map((p) => (
                  <Button key={p.id} type="button" variant="ghost" size="sm" className="block w-full justify-start text-left h-auto py-2" disabled={busy} onClick={() => handlePickGrowthPost(p)}>
                    Use Growth: {p.title || p.content.slice(0, 40)}…
                  </Button>
                ))}
              </div>
            </div>
            <Field>
              <FieldLabel>Promo topic (for AI caption)</FieldLabel>
              <Input value={topic} onChange={(e) => setTopic(e.target.value)} placeholder="Weekend sale, new menu…" />
            </Field>
            <Field>
              <FieldLabel>Caption (template body variable)</FieldLabel>
              <Textarea value={caption} onChange={(e) => setCaption(e.target.value)} rows={4} placeholder="Hi! Check out our offer…" />
              <Button type="button" variant="outline" size="sm" className="mt-2" disabled={generatingCaption} onClick={handleGenerateCaption}>
                {generatingCaption ? <Loader2 className="h-3 w-3 mr-1 animate-spin" /> : <Sparkles className="h-3 w-3 mr-1" />}
                Generate caption with AI
              </Button>
            </Field>
            <div className="flex justify-end">
              <Button onClick={() => saveStep(1)} disabled={busy}>
                Next: Audience <ArrowRight className="h-4 w-4 ml-1" />
              </Button>
            </div>
          </CardContent>
        </Card>
      )}

      {step === 1 && (
        <Card>
          <CardHeader>
            <CardTitle>Audience</CardTitle>
            <CardDescription>Choose who receives this campaign.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <Field>
              <FieldLabel>Segment</FieldLabel>
              <Select value={segment} onValueChange={(v) => setSegment(v as WhatsAppCampaignSegment)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  {SEGMENTS.map((s) => (
                    <SelectItem key={s.id} value={s.id}>{s.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
            <p className="text-sm">
              <strong>{audienceCount}</strong> unique phone numbers
              {audienceCount > limits.recipientsLimit && (
                <span className="text-destructive ml-2">Exceeds plan limit ({limits.recipientsLimit})</span>
              )}
            </p>
            <div className="flex justify-between">
              <Button variant="outline" onClick={() => setStep(0)}><ArrowLeft className="h-4 w-4 mr-1" /> Back</Button>
              <Button onClick={() => saveStep(2)} disabled={busy || audienceCount === 0 || audienceCount > limits.recipientsLimit}>
                Next: Template <ArrowRight className="h-4 w-4 ml-1" />
              </Button>
            </div>
          </CardContent>
        </Card>
      )}

      {step === 2 && (
        <Card>
          <CardHeader>
            <CardTitle>Template</CardTitle>
            <CardDescription>
              Use an approved Meta template with an IMAGE header for poster campaigns.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <Field>
              <FieldLabel>Approved template</FieldLabel>
              <Select value={templateName} onValueChange={setTemplateName}>
                <SelectTrigger><SelectValue placeholder="Select template" /></SelectTrigger>
                <SelectContent>
                  {templates.map((t) => (
                    <SelectItem key={t.id} value={t.name}>{t.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
            {templates.length === 0 && (
              <p className="text-sm text-muted-foreground">
                No approved templates. <Link href="/dashboard/settings?tab=whatsapp" className="underline">Create or sync templates</Link>.
              </p>
            )}
            <Field>
              <FieldLabel>Test phone (your number)</FieldLabel>
              <div className="flex gap-2">
                <Input value={testPhone} onChange={(e) => setTestPhone(e.target.value)} placeholder="2547…" />
                <Button type="button" variant="outline" disabled={busy || !templateName || !testPhone} onClick={handleTestSend}>
                  Test send
                </Button>
              </div>
            </Field>
            <div className="flex justify-between">
              <Button variant="outline" onClick={() => setStep(1)}><ArrowLeft className="h-4 w-4 mr-1" /> Back</Button>
              <Button onClick={() => setStep(3)} disabled={!templateName}>
                Review <ArrowRight className="h-4 w-4 ml-1" />
              </Button>
            </div>
          </CardContent>
        </Card>
      )}

      {step === 3 && (
        <Card>
          <CardHeader>
            <CardTitle>Review &amp; send</CardTitle>
            <CardDescription>Messages are queued (~1/sec) to respect Meta rate limits.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-3 text-sm sm:grid-cols-2">
              <div><span className="text-muted-foreground">Segment:</span> {SEGMENTS.find((s) => s.id === segment)?.label}</div>
              <div><span className="text-muted-foreground">Recipients:</span> {audienceCount}</div>
              <div><span className="text-muted-foreground">Template:</span> {templateName || "—"}</div>
              <div className="sm:col-span-2"><span className="text-muted-foreground">Caption:</span> {caption || "—"}</div>
            </div>
            {campaign?.status === "sending" && (
              <p className="text-sm text-primary flex items-center gap-2">
                <Loader2 className="h-4 w-4 animate-spin" /> Sending… {campaign.sentCount}/{campaign.totalRecipients}
              </p>
            )}
            {campaign?.status === "completed" && (
              <p className="text-sm text-primary flex items-center gap-2">
                <CheckCircle2 className="h-4 w-4" /> Completed — {campaign.sentCount} sent, {campaign.failedCount} failed
              </p>
            )}
            <div className="flex justify-between">
              <Button variant="outline" onClick={() => setStep(2)}><ArrowLeft className="h-4 w-4 mr-1" /> Back</Button>
              <Button onClick={handleSend} disabled={busy || !templateName || campaign?.status === "sending"}>
                {busy ? <Loader2 className="h-4 w-4 mr-1 animate-spin" /> : <Send className="h-4 w-4 mr-1" />}
                Send campaign
              </Button>
            </div>
            {campaign && (campaign.status === "sending" || campaign.status === "completed") && (
              <Button type="button" variant="ghost" size="sm" onClick={() => refreshCampaign(campaign.id)}>
                Refresh status
              </Button>
            )}
          </CardContent>
        </Card>
      )}

      {history.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>Recent campaigns</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {history.slice(0, 8).map((c) => (
              <div key={c.id} className="flex flex-wrap items-center justify-between gap-2 border-b pb-2 text-sm last:border-0">
                <span className="font-medium">{c.name}</span>
                <Badge variant={c.status === "completed" ? "secondary" : c.status === "failed" ? "destructive" : "outline"}>
                  {c.status}
                </Badge>
                <span className="text-muted-foreground">{c.sentCount}/{c.totalRecipients} sent</span>
              </div>
            ))}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
