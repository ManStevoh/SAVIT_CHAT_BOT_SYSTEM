"use client"

import { useState } from "react"
import useSWR, { useSWRConfig } from "swr"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Badge } from "@/components/ui/badge"
import { Progress } from "@/components/ui/progress"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
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
import { FormModal } from "@/components/shared/modal"
import { apiRequest } from "@/lib/api-client"
import {
  getPlatformSettings,
  updatePlatformSettings,
  updateAdminCompany,
  type CommissionOverview,
  type CommissionCompanyRow,
  type CommissionInvoiceRow,
} from "@/lib/api-actions"
import { cn } from "@/lib/utils"
import { toast } from "sonner"
import {
  AlertCircle,
  BadgePercent,
  Building2,
  FileText,
  Loader2,
  Percent,
  Settings2,
  Wallet,
} from "lucide-react"

function kes(n: number) {
  return `KES ${(Number(n) || 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}`
}

type Tab = "overview" | "companies" | "invoices" | "settings"

export default function AdminCommissionsPage() {
  const { mutate } = useSWRConfig()
  const [tab, setTab] = useState<Tab>("overview")
  const { data: overview, error, isLoading } = useSWR<CommissionOverview>(
    "admin-commissions-overview",
    () => apiRequest<CommissionOverview>("/api/admin/commissions/overview"),
    { revalidateOnFocus: false }
  )
  const { data: companiesData } = useSWR<{ companies: CommissionCompanyRow[] }>(
    tab === "companies" || tab === "overview" ? "admin-commissions-companies" : null,
    () => apiRequest("/api/admin/commissions/companies"),
    { revalidateOnFocus: false }
  )
  const { data: invoicesData } = useSWR<{ invoices: CommissionInvoiceRow[] }>(
    tab === "invoices" ? "admin-commissions-invoices" : null,
    () => apiRequest("/api/admin/commissions/invoices"),
    { revalidateOnFocus: false }
  )
  const { data: platform } = useSWR("admin-platform-settings", getPlatformSettings, {
    revalidateOnFocus: false,
  })

  const refreshAll = () => {
    mutate("admin-commissions-overview")
    mutate("admin-commissions-companies")
    mutate("admin-commissions-invoices")
  }

  // Edit terms dialog
  const [editing, setEditing] = useState<CommissionCompanyRow | null>(null)
  const [termModel, setTermModel] = useState("")
  const [termRate, setTermRate] = useState("")
  const [termBasis, setTermBasis] = useState("total")
  const [termThreshold, setTermThreshold] = useState("")
  const [termSaving, setTermSaving] = useState(false)

  const openTerms = (c: CommissionCompanyRow) => {
    setEditing(c)
    setTermModel(c.modelOverridden ? c.model : "")
    setTermRate(c.rateOverridden ? String(c.rate) : "")
    setTermBasis(c.basis || "total")
    setTermThreshold(String(c.threshold ?? ""))
  }

  const saveTerms = async () => {
    if (!editing) return
    setTermSaving(true)
    const res = await updateAdminCompany(editing.id, {
      billingModel: termModel || null,
      commissionRate: termRate.trim() === "" ? null : Number(termRate),
      commissionBasis: termBasis,
      commissionInvoiceThreshold: termThreshold.trim() === "" ? null : Number(termThreshold),
    } as Parameters<typeof updateAdminCompany>[1])
    setTermSaving(false)
    if (!res.success) {
      toast.error(res.message ?? "Could not save terms.")
      return
    }
    toast.success("Commission terms updated.")
    setEditing(null)
    refreshAll()
  }

  // Mark paid dialog
  const [paying, setPaying] = useState<CommissionInvoiceRow | null>(null)
  const [payRef, setPayRef] = useState("")
  const [payMethod, setPayMethod] = useState("")
  const [paySaving, setPaySaving] = useState(false)

  const confirmPaid = async () => {
    if (!paying) return
    setPaySaving(true)
    try {
      const res = await apiRequest<{ success: boolean; message?: string }>(
        `/api/admin/commissions/invoices/${paying.id}/mark-paid`,
        { method: "POST", body: { payment_reference: payRef.trim() || undefined, payment_method: payMethod.trim() || undefined } }
      )
      if (!res.success) {
        toast.error(res.message ?? "Could not mark paid.")
        return
      }
      toast.success(res.message ?? "Invoice marked paid.")
      setPaying(null)
      setPayRef("")
      setPayMethod("")
      refreshAll()
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Could not mark paid.")
    } finally {
      setPaySaving(false)
    }
  }

  const [generating, setGenerating] = useState(false)
  const runGenerate = async () => {
    setGenerating(true)
    try {
      const res = await apiRequest<{ success: boolean; created?: number; message?: string }>(
        "/api/admin/commissions/invoices/generate",
        { method: "POST" }
      )
      toast.success(res.message ?? "Invoices generated.")
      refreshAll()
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Generation failed.")
    } finally {
      setGenerating(false)
    }
  }

  // Platform settings form
  const [platEnabled, setPlatEnabled] = useState<boolean | null>(null)
  const [platRate, setPlatRate] = useState("")
  const [platThreshold, setPlatThreshold] = useState("")
  const [platGrace, setPlatGrace] = useState("")
  const [platPublic, setPlatPublic] = useState<boolean | null>(null)
  const [platSaving, setPlatSaving] = useState(false)
  const [platTouched, setPlatTouched] = useState(false)

  const effEnabled = platEnabled ?? overview?.enabled ?? false
  const effRate = platTouched ? platRate : String(platform?.defaultCommissionRate ?? overview?.defaults.rate ?? 5)
  const effThreshold = platTouched ? platThreshold : String(platform?.defaultCommissionThreshold ?? overview?.defaults.threshold ?? 50)
  const effGrace = platTouched ? platGrace : String(platform?.commissionGracePeriodDays ?? overview?.defaults.graceDays ?? 7)
  const effPublic = platPublic ?? platform?.allowPublicCommissionSignup ?? overview?.defaults.publicSignup ?? false

  const markTouched = () => setPlatTouched(true)

  const savePlatform = async () => {
    setPlatSaving(true)
    const res = await updatePlatformSettings({
      defaultBillingModel: effEnabled ? "commission" : "subscription",
      defaultCommissionRate: effRate.trim() === "" ? 5 : Number(effRate),
      defaultCommissionThreshold: effThreshold.trim() === "" ? 50 : Number(effThreshold),
      commissionGracePeriodDays: effGrace.trim() === "" ? 7 : parseInt(effGrace, 10),
      allowPublicCommissionSignup: effPublic,
    })
    setPlatSaving(false)
    if (!res.success) {
      toast.error(res.message ?? "Could not save.")
      return
    }
    toast.success("Commission defaults saved.")
    setPlatTouched(false)
    mutate("admin-platform-settings")
    refreshAll()
  }

  if (isLoading && !overview) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Commissions</h1>
          <p className="text-muted-foreground">Commission-on-sales billing across the platform</p>
        </div>
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          {[1, 2, 3, 4].map((i) => (
            <Card key={i}>
              <CardContent className="p-6">
                <div className="h-16 animate-pulse rounded bg-muted" />
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    )
  }

  if (error || !overview) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Commissions</h1>
          <p className="text-muted-foreground">Commission-on-sales billing across the platform</p>
        </div>
        <Card className="border-destructive/50">
          <CardContent className="p-6">
            <p className="text-destructive">Failed to load commission data.</p>
            <Button variant="outline" className="mt-2" onClick={() => mutate("admin-commissions-overview")}>
              Retry
            </Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  const companies = companiesData?.companies ?? []
  const invoices = invoicesData?.invoices ?? []

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex items-center gap-2.5">
            <h1 className="text-2xl font-bold text-foreground">Commissions</h1>
            <Badge variant={overview.enabled ? "default" : "secondary"}>
              {overview.enabled ? "Enabled platform-wide" : "Disabled platform-wide"}
            </Badge>
          </div>
          <p className="mt-1 text-muted-foreground">
            Take-rate billing: companies pay a % of sales instead of a subscription.
          </p>
        </div>
        <Button variant="outline" onClick={runGenerate} disabled={generating}>
          {generating ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <FileText className="mr-2 h-4 w-4" />}
          Generate invoices
        </Button>
      </div>

      {/* Money strip */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card className={overview.owedNow > 0 ? "border-amber-500/40" : undefined}>
          <CardContent className="p-5">
            <div className="flex items-center gap-2 text-muted-foreground">
              <Wallet className="h-4 w-4" />
              <p className="text-xs font-semibold uppercase tracking-wide">Owed right now</p>
            </div>
            <p className="mt-1 text-2xl font-bold text-foreground">{kes(overview.owedNow)}</p>
            <p className="mt-0.5 text-xs text-muted-foreground">
              {overview.companyCount} {overview.companyCount === 1 ? "company" : "companies"} on commission
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-5">
            <div className="flex items-center gap-2 text-muted-foreground">
              <BadgePercent className="h-4 w-4" />
              <p className="text-xs font-semibold uppercase tracking-wide">Earned this month</p>
            </div>
            <p className="mt-1 text-2xl font-bold text-foreground">{kes(overview.monthCommission)}</p>
            <p className="mt-0.5 text-xs text-muted-foreground">on {kes(overview.monthSales)} paid sales</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-5">
            <div className="flex items-center gap-2 text-muted-foreground">
              <Percent className="h-4 w-4" />
              <p className="text-xs font-semibold uppercase tracking-wide">Collected at source</p>
            </div>
            <p className="mt-1 text-2xl font-bold text-foreground">{kes(overview.settledTotal)}</p>
            <p className="mt-0.5 text-xs text-muted-foreground">{kes(overview.accruedTotal)} still accruing</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-5">
            <div className="flex items-center gap-2 text-muted-foreground">
              <FileText className="h-4 w-4" />
              <p className="text-xs font-semibold uppercase tracking-wide">Open invoices</p>
            </div>
            <p className="mt-1 text-2xl font-bold text-foreground">{overview.pendingInvoices}</p>
            <p className="mt-0.5 text-xs text-muted-foreground">{kes(overview.pendingInvoicesTotal)} outstanding</p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <div className="overflow-x-auto pb-1">
        <div className="flex min-w-max gap-1 rounded-2xl border border-border bg-muted/50 p-1.5 sm:inline-flex sm:min-w-0">
          {([
            { id: "overview", label: "Overview" },
            { id: "companies", label: `Companies (${companies.length || overview.companyCount})` },
            { id: "invoices", label: `Invoices (${invoices.length || overview.pendingInvoices})` },
            { id: "settings", label: "Platform settings" },
          ] as { id: Tab; label: string }[]).map((t) => (
            <button
              key={t.id}
              type="button"
              onClick={() => setTab(t.id)}
              className={cn(
                "rounded-xl px-3.5 py-2 text-[13px] font-medium transition-all",
                tab === t.id ? "bg-background text-foreground shadow-sm ring-1 ring-border" : "text-muted-foreground hover:text-foreground"
              )}
            >
              {t.label}
            </button>
          ))}
        </div>
      </div>

      {tab === "overview" && (
        <div className="grid gap-4 lg:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Top balances due</CardTitle>
              <CardDescription>Who owes the most right now</CardDescription>
            </CardHeader>
            <CardContent>
              {overview.topDebtors.length === 0 ? (
                <p className="py-4 text-center text-sm text-muted-foreground">Nobody owes anything. 🎉</p>
              ) : (
                <div className="space-y-3">
                  {overview.topDebtors.map((d) => (
                    <div key={d.id} className="flex items-center justify-between gap-3">
                      <div className="min-w-0">
                        <p className="truncate text-sm font-medium text-foreground">{d.name}</p>
                        <p className="text-xs text-muted-foreground">{d.rate}% take rate</p>
                      </div>
                      <p className="shrink-0 text-sm font-bold text-foreground">{kes(d.balanceDue)}</p>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">How commission works here</CardTitle>
              <CardDescription>The rules, in order</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2.5 text-[13px] leading-relaxed text-muted-foreground">
              <p><span className="font-semibold text-foreground">1. Who pays:</span> a company's own terms win; otherwise the platform default below applies.</p>
              <p><span className="font-semibold text-foreground">2. When it accrues:</span> every paid order records a take ({overview.defaults.rate}% default) — split gateways settle instantly, M-Pesa/manual accrues to the balance.</p>
              <p><span className="font-semibold text-foreground">3. When it's due:</span> balances past the invoice threshold ({kes(overview.defaults.threshold)}) get invoiced with {overview.defaults.graceDays} days to pay.</p>
              <p><span className="font-semibold text-foreground">4. What the customer sees:</span> commission companies get a banner instead of subscription bills; monthly fees are waived.</p>
            </CardContent>
          </Card>
        </div>
      )}

      {tab === "companies" && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Companies on commission</CardTitle>
            <CardDescription>Terms, this month's sales, and live balances</CardDescription>
          </CardHeader>
          <CardContent>
            {companies.length === 0 ? (
              <p className="py-6 text-center text-sm text-muted-foreground">
                No companies on commission yet. Enable it platform-wide or set a company's billing model to commission.
              </p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Company</TableHead>
                    <TableHead>Model</TableHead>
                    <TableHead className="text-right">Rate</TableHead>
                    <TableHead className="text-right">Sales (30d)</TableHead>
                    <TableHead>Balance vs threshold</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {companies.map((c) => {
                    const pct = c.threshold > 0 ? Math.min(100, (c.balanceDue / c.threshold) * 100) : 0
                    return (
                      <TableRow key={c.id}>
                        <TableCell>
                          <div className="flex items-center gap-2.5">
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                              <Building2 className="h-3.5 w-3.5" />
                            </span>
                            <div>
                              <p className="text-sm font-medium text-foreground">{c.name}</p>
                              <p className="text-xs text-muted-foreground">{c.monthOrders} paid orders · {c.basis}</p>
                            </div>
                          </div>
                        </TableCell>
                        <TableCell>
                          <Badge variant={c.model === "commission" ? "default" : "secondary"} className="capitalize">
                            {c.model}
                          </Badge>
                          {!c.modelOverridden && (
                            <p className="mt-0.5 text-[11px] text-muted-foreground">platform default</p>
                          )}
                        </TableCell>
                        <TableCell className="text-right text-sm font-medium text-foreground">
                          {c.rate}%{!c.rateOverridden && <span className="text-muted-foreground">*</span>}
                        </TableCell>
                        <TableCell className="text-right text-sm text-foreground">{kes(c.monthSales)}</TableCell>
                        <TableCell className="min-w-[160px]">
                          <div className="flex items-center justify-between text-xs">
                            <span className="font-medium text-foreground">{kes(c.balanceDue)}</span>
                            <span className="text-muted-foreground">of {kes(c.threshold)}</span>
                          </div>
                          <Progress value={pct} className={cn("mt-1 h-1.5", c.overdue && "[&>div]:bg-destructive")} />
                          {c.overdue && (
                            <p className="mt-1 flex items-center gap-1 text-[11px] font-medium text-destructive">
                              <AlertCircle className="h-3 w-3" /> Over threshold — invoice them
                            </p>
                          )}
                        </TableCell>
                        <TableCell className="text-right">
                          <Button variant="outline" size="sm" onClick={() => openTerms(c)}>
                            Edit terms
                          </Button>
                        </TableCell>
                      </TableRow>
                    )
                  })}
                </TableBody>
              </Table>
            )}
            <p className="mt-3 text-xs text-muted-foreground">* rate from platform default. Companies page holds the full profile.</p>
          </CardContent>
        </Card>
      )}

      {tab === "invoices" && (
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <div>
              <CardTitle className="text-base">Commission invoices</CardTitle>
              <CardDescription>Bill companies for accrued balances</CardDescription>
            </div>
            <Button size="sm" onClick={runGenerate} disabled={generating}>
              {generating ? <Loader2 className="mr-2 h-3.5 w-3.5 animate-spin" /> : <FileText className="mr-2 h-3.5 w-3.5" />}
              Generate
            </Button>
          </CardHeader>
          <CardContent>
            {invoices.length === 0 ? (
              <p className="py-6 text-center text-sm text-muted-foreground">
                No invoices yet. Generate creates one open invoice per company that owes money.
              </p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Invoice</TableHead>
                    <TableHead>Company</TableHead>
                    <TableHead className="text-right">Orders</TableHead>
                    <TableHead className="text-right">Due</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {invoices.map((inv) => (
                    <TableRow key={inv.id}>
                      <TableCell>
                        <p className="font-mono text-sm font-medium text-foreground">{inv.number}</p>
                        <p className="text-xs text-muted-foreground">
                          {inv.periodStart ?? "—"} → {inv.periodEnd ?? "—"}
                          {inv.dueDate ? ` · due ${inv.dueDate}` : ""}
                        </p>
                      </TableCell>
                      <TableCell className="text-sm text-foreground">{inv.companyName}</TableCell>
                      <TableCell className="text-right text-sm text-foreground">
                        {inv.ordersCount} <span className="text-muted-foreground">({kes(inv.grossSales)})</span>
                      </TableCell>
                      <TableCell className="text-right text-sm font-semibold text-foreground">{kes(inv.amountDue)}</TableCell>
                      <TableCell>
                        <Badge variant={inv.status === "paid" ? "default" : "secondary"} className="capitalize">
                          {inv.status}
                        </Badge>
                        {inv.paidAt && <p className="mt-0.5 text-[11px] text-muted-foreground">paid {inv.paidAt}</p>}
                      </TableCell>
                      <TableCell className="text-right">
                        {inv.status === "open" && (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                              setPaying(inv)
                              setPayRef("")
                              setPayMethod("")
                            }}
                          >
                            Mark paid
                          </Button>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>
      )}

      {tab === "settings" && (
        <div className="space-y-4">
          <Card className={effEnabled ? "border-emerald-500/30" : undefined}>
            <CardHeader>
              <div className="flex items-center justify-between gap-3">
                <div>
                  <CardTitle className="text-base">Commission billing {effEnabled ? "is on" : "is off"}</CardTitle>
                  <CardDescription>
                    Master switch for the platform default. Companies with their own terms are never affected.
                  </CardDescription>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-xs font-medium text-muted-foreground">Enabled</span>
                  <Switch
                    checked={effEnabled}
                    onCheckedChange={() => {
                      markTouched()
                      setPlatEnabled(!effEnabled)
                    }}
                  />
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="space-y-1.5">
                  <Label>Default take rate %</Label>
                  <Input
                    type="number"
                    min={0}
                    max={100}
                    value={effRate}
                    onChange={(e) => {
                      markTouched()
                      setPlatRate(e.target.value)
                    }}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label>Invoice threshold (KES)</Label>
                  <Input
                    type="number"
                    min={0}
                    value={effThreshold}
                    onChange={(e) => {
                      markTouched()
                      setPlatThreshold(e.target.value)
                    }}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label>Grace days to pay</Label>
                  <Input
                    type="number"
                    min={1}
                    max={365}
                    value={effGrace}
                    onChange={(e) => {
                      markTouched()
                      setPlatGrace(e.target.value)
                    }}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label>Public commission signup</Label>
                  <div className="flex h-10 items-center gap-2">
                    <Switch
                      checked={effPublic}
                      onCheckedChange={() => {
                        markTouched()
                        setPlatPublic(!effPublic)
                      }}
                    />
                    <span className="text-xs text-muted-foreground">Let new companies choose it</span>
                  </div>
                </div>
              </div>
              <div className="flex items-center gap-3 border-t border-border pt-4">
                <Button size="sm" onClick={savePlatform} disabled={platSaving}>
                  {platSaving ? <Loader2 className="mr-2 h-3.5 w-3.5 animate-spin" /> : null}
                  {platSaving ? "Saving…" : "Save defaults"}
                </Button>
                <p className="text-xs text-muted-foreground">
                  Company overrides (Companies → Edit terms) always win over these defaults.
                </p>
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <Settings2 className="h-4 w-4 text-muted-foreground" /> What turning it off does
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-[13px] leading-relaxed text-muted-foreground">
              <p>New companies default to subscriptions. Existing commission companies keep their terms and balances — nothing is forgiven or deleted.</p>
              <p>To move a company off commission individually, open Companies → edit → set billing model to subscription.</p>
            </CardContent>
          </Card>
        </div>
      )}

      {/* Edit terms dialog */}
      <FormModal
        open={!!editing}
        onOpenChange={(o) => !o && setEditing(null)}
        title={editing ? `Terms — ${editing.name}` : "Terms"}
        description="Leave model or rate empty to fall back to the platform default."
        onSubmit={saveTerms}
        isLoading={termSaving}
        submitLabel="Save terms"
      >
        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Billing model</Label>
            <Select
              value={termModel || "__default"}
              onValueChange={(v) => setTermModel(v === "__default" ? "" : v)}
            >
              <SelectTrigger><SelectValue placeholder="Platform default" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="__default">Platform default</SelectItem>
                <SelectItem value="subscription">Subscription</SelectItem>
                <SelectItem value="commission">Commission-on-sales</SelectItem>
                <SelectItem value="hybrid">Hybrid</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label>Take rate %</Label>
              <Input type="number" min={0} max={100} value={termRate} onChange={(e) => setTermRate(e.target.value)} placeholder="Default" />
            </div>
            <div className="space-y-1.5">
              <Label>Basis</Label>
              <Select value={termBasis} onValueChange={setTermBasis}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="total">Order total</SelectItem>
                  <SelectItem value="subtotal">Subtotal</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <div className="space-y-1.5">
            <Label>Invoice threshold (KES)</Label>
            <Input type="number" min={0} value={termThreshold} onChange={(e) => setTermThreshold(e.target.value)} placeholder="Default" />
          </div>
        </div>
      </FormModal>

      {/* Mark paid dialog */}
      <FormModal
        open={!!paying}
        onOpenChange={(o) => !o && setPaying(null)}
        title={paying ? `Mark ${paying.number} paid` : "Mark paid"}
        description="Clears the company balance by the invoice amount and settles its orders."
        onSubmit={confirmPaid}
        isLoading={paySaving}
        submitLabel="Confirm payment"
      >
        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Payment reference (optional)</Label>
            <Input value={payRef} onChange={(e) => setPayRef(e.target.value)} placeholder="e.g. MPESA QHX12AB34C" />
          </div>
          <div className="space-y-1.5">
            <Label>Method (optional)</Label>
            <Input value={payMethod} onChange={(e) => setPayMethod(e.target.value)} placeholder="e.g. M-Pesa, bank transfer" />
          </div>
        </div>
      </FormModal>
    </div>
  )
}
