'use client'

import { useRef, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DataTable, type Column } from '@/components/shared/data-table'
import { FormModal, ConfirmModal } from '@/components/shared/modal'
import { InputField, SwitchField } from '@/components/shared/form-field'
import { useDineInTables, useCompanySettings, useSubscription, type DineInTable } from '@/lib/api-hooks'
import { createDineInTable, updateDineInTable, deleteDineInTable, updateSettings } from '@/lib/api-actions'
import { PlanLimitBar, UpgradePrompt, LockedFeatureGate } from '@/components/shared/upgrade-prompt'
import { STARTER_LIMITS, isStarterPlan, isAtLimit, isNearLimit } from '@/lib/use-plan'
import { Plus, Edit, Trash2, Copy, QrCode, MessageSquare, Globe, ExternalLink, Printer, UtensilsCrossed, Settings2 } from 'lucide-react'
import { QRCodeSVG } from 'qrcode.react'
import { useSWRConfig } from 'swr'
import { toast } from 'sonner'

interface TableFormData {
  name: string
  code: string
  seats: string
  isActive: boolean
}

const initialForm: TableFormData = {
  name: '',
  code: '',
  seats: '',
  isActive: true,
}

const STEPS = [
  { n: 1, title: 'Name your tables', desc: 'Table 1, Patio 2 — whatever staff shout across the room.' },
  { n: 2, title: 'Pick scan behavior', desc: 'Web menu, WhatsApp chat, or let the guest choose. Below.' },
  { n: 3, title: 'Print & place the QRs', desc: 'One tent card per table. Print all runs off a single button.' },
]

export default function DineInPage() {
  const { data, isLoading } = useDineInTables()
  const { data: settings } = useCompanySettings()
  const { data: subscription } = useSubscription()
  const { mutate } = useSWRConfig()
  const tables = data?.tables ?? []
  const planBlocked = data && data.allowed === false

  const starterTables = isStarterPlan(subscription?.plan)
  const tableLimit = data?.maxTables ?? (starterTables ? STARTER_LIMITS.tables : null)
  const tablesFull = isAtLimit(tables.length, tableLimit)
  const tablesNear = isNearLimit(tables.length, tableLimit)

  const dineInSwitchedOn =
    settings?.enableDineIn || settings?.dineInEnabled || settings?.businessMode === 'restaurant'
  const [enabling, setEnabling] = useState(false)

  const [savingSettings, setSavingSettings] = useState(false)
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<DineInTable | null>(null)
  const [form, setForm] = useState<TableFormData>(initialForm)
  const [submitting, setSubmitting] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<DineInTable | null>(null)
  const [qrTarget, setQrTarget] = useState<DineInTable | null>(null)
  const [printing, setPrinting] = useState(false)
  const qrRefs = useRef(new Map<string, SVGSVGElement | null>())

  const refresh = () => {
    mutate(['dine-in-tables'])
    mutate('company-settings')
  }

  const enableDineIn = async () => {
    setEnabling(true)
    try {
      const res = await updateSettings({ enableDineIn: true })
      if (res.success) {
        toast.success('Dine-in switched on.')
        refresh()
      } else {
        toast.error(res.message || 'Could not switch on dine-in.')
      }
    } finally {
      setEnabling(false)
    }
  }

  const handleUpdateDineInOptions = async (key: 'dineInQrTarget' | 'dineInPaymentTiming', value: string) => {
    setSavingSettings(true)
    try {
      const res = await updateSettings({ [key]: value })
      if (res.success) {
        toast.success('Dine-in settings updated')
        refresh()
      } else {
        toast.error(res.message || 'Failed to update settings')
      }
    } catch {
      toast.error('Failed to update settings')
    } finally {
      setSavingSettings(false)
    }
  }

  const openCreate = () => {
    setEditing(null)
    setForm(initialForm)
    setFormOpen(true)
  }

  const openEdit = (table: DineInTable) => {
    setEditing(table)
    setForm({
      name: table.name,
      code: table.code ?? '',
      seats: table.seats ? String(table.seats) : '',
      isActive: table.isActive,
    })
    setFormOpen(true)
  }

  const handleSubmit = async () => {
    if (!form.name.trim()) {
      toast.error('Enter a table name (e.g. "Table 5")')
      return
    }
    setSubmitting(true)
    try {
      const payload = {
        name: form.name.trim(),
        code: form.code.trim() || null,
        seats: form.seats ? parseInt(form.seats, 10) : null,
        isActive: form.isActive,
      }
      const res = editing
        ? await updateDineInTable(editing.id, payload)
        : await createDineInTable(payload)
      if (!res.success) {
        toast.error(res.message || 'Failed to save table')
        return
      }
      toast.success(editing ? 'Table updated' : 'Table created')
      setFormOpen(false)
      setEditing(null)
      setForm(initialForm)
      refresh()
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setSubmitting(true)
    try {
      const res = await deleteDineInTable(deleteTarget.id)
      if (!res.success) {
        toast.error(res.message || 'Failed to delete table')
        return
      }
      toast.success('Table deleted')
      setDeleteTarget(null)
      refresh()
    } finally {
      setSubmitting(false)
    }
  }

  const copyUrl = async (url: string) => {
    try {
      await navigator.clipboard.writeText(url)
      toast.success('QR order link copied')
    } catch {
      toast.error('Could not copy link')
    }
  }

  /** Print tent cards: serialize the rendered QR SVGs into a print window. */
  const printTables = (only?: DineInTable[]) => {
    const list = only ?? tables
    if (list.length === 0) {
      toast.error('Add a table first.')
      return
    }
    setPrinting(true)
    try {
      const cards = list
        .map((t) => {
          const el = qrRefs.current.get(t.id)
          const svg = el ? new XMLSerializer().serializeToString(el) : ''
          const esc = (s: string) =>
            s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
          return `<div class="card">
            <div class="biz">${esc(settings?.companyName || 'Our Restaurant')}</div>
            <div class="tbl">${esc(t.name)}</div>
            <div class="qr">${svg}</div>
            <div class="hint">Scan to view the menu & order</div>
            ${t.code ? `<div class="code">${esc(t.code)}</div>` : ''}
          </div>`
        })
        .join('')
      const win = window.open('', '_blank', 'width=900,height=700')
      if (!win) {
        toast.error('Popup blocked — allow popups to print.')
        return
      }
      win.document.write(`<!doctype html><html><head><title>Table QR codes</title><style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; padding: 16px; }
        .card { width: 340px; margin: 0 auto 24px; padding: 28px 20px; border: 2px dashed #999; border-radius: 12px; text-align: center; page-break-inside: avoid; }
        .biz { font-size: 13px; text-transform: uppercase; letter-spacing: 2px; color: #666; }
        .tbl { font-size: 34px; font-weight: 800; margin: 6px 0 12px; }
        .qr svg { width: 220px; height: 220px; }
        .hint { margin-top: 12px; font-size: 14px; color: #444; }
        .code { margin-top: 6px; font-family: monospace; font-size: 12px; color: #888; }
        @media print { body { padding: 0; } .card { border-style: solid; } }
      </style></head><body>${cards}<script>window.onload = () => window.print()</script></body></html>`)
      win.document.close()
    } finally {
      setPrinting(false)
    }
  }

  if (planBlocked) {
    return (
      <LockedFeatureGate
        icon={QrCode}
        title="Dine-in tables aren't on your current plan"
        description="Table QR ordering with web menus and WhatsApp scan-to-chat lives on plans that include dine-in. Talk to us or upgrade to switch it on."
        planLabel={subscription?.planName ?? 'Current plan'}
      />
    )
  }

  const columns: Column<DineInTable>[] = [
    {
      key: 'name',
      header: 'Table',
      cell: (t) => (
        <div className="flex items-center gap-2">
          <span className="font-medium">{t.name}</span>
          {t.code ? <Badge variant="outline">{t.code}</Badge> : null}
        </div>
      ),
    },
    {
      key: 'seats',
      header: 'Seats',
      cell: (t) => t.seats ?? '—',
    },
    {
      key: 'status',
      header: 'Status',
      cell: (t) => (
        <Badge variant={t.isActive ? 'default' : 'secondary'}>
          {t.isActive ? 'Active' : 'Inactive'}
        </Badge>
      ),
    },
    {
      key: 'orderUrl',
      header: 'Order links',
      cell: (t) => (
        <div className="space-y-1.5 py-1">
          <div className="flex items-center gap-1.5 text-xs">
            <Globe className="h-3.5 w-3.5 text-primary shrink-0" />
            <code className="max-w-[150px] truncate text-[11px]">{t.orderUrl}</code>
            <Button variant="ghost" size="icon" className="h-6 w-6" onClick={() => copyUrl(t.orderUrl)} title="Copy web menu link">
              <Copy className="h-3 w-3" />
            </Button>
            <Button variant="ghost" size="icon" className="h-6 w-6" asChild title="Open web menu">
              <a href={t.orderUrl} target="_blank" rel="noreferrer">
                <ExternalLink className="h-3 w-3" />
              </a>
            </Button>
          </div>
          {t.whatsappOrderUrl && (
            <div className="flex items-center gap-1.5 text-xs">
              <MessageSquare className="h-3.5 w-3.5 text-green-600 shrink-0" />
              <code className="max-w-[150px] truncate text-[11px]">{t.whatsappOrderUrl}</code>
              <Button variant="ghost" size="icon" className="h-6 w-6" onClick={() => copyUrl(t.whatsappOrderUrl!)} title="Copy WhatsApp link">
                <Copy className="h-3 w-3" />
              </Button>
            </div>
          )}
        </div>
      ),
    },
    {
      key: 'actions',
      header: '',
      cell: (t) => (
        <div className="flex justify-end gap-1">
          <Button variant="ghost" size="icon" onClick={() => setQrTarget(t)} aria-label="QR code" title="QR code & print">
            <QrCode className="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon" onClick={() => openEdit(t)} aria-label="Edit">
            <Edit className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            onClick={() => setDeleteTarget(t)}
            aria-label="Delete"
          >
            <Trash2 className="h-4 w-4 text-destructive" />
          </Button>
        </div>
      ),
    },
  ]

  return (
    <div className="w-full space-y-6">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-foreground">Dine-in tables</h1>
          <p className="mt-0.5 text-sm text-muted-foreground">
            Guests scan, order from their seats, pay or run a tab.
            {tableLimit != null ? ` ${tables.length} of ${tableLimit} tables used.` : ''}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {tables.length > 0 && (
            <Button variant="outline" size="sm" onClick={() => printTables()} disabled={printing}>
              <Printer className="mr-1.5 h-3.5 w-3.5" />
              Print all QRs
            </Button>
          )}
          <Button size="sm" onClick={openCreate} disabled={tablesFull} title={tablesFull ? `All ${tableLimit} tables in use — Growth unlocks 20` : undefined}>
            <Plus className="mr-1.5 h-3.5 w-3.5" />
            Add table
          </Button>
        </div>
      </div>

      {(tablesNear || tablesFull) && tableLimit != null && (
        <div className="max-w-xl space-y-3">
          <PlanLimitBar used={tables.length} limit={tableLimit} label="Dine-in tables" unit="tables" />
          {tablesFull && (
            <UpgradePrompt
              title="All your Starter tables are in use"
              description="Growth expands you to 20 tables with the same QR ordering and M-Pesa checkout — plus WhatsApp table ordering."
              compact
            />
          )}
        </div>
      )}

      {!dineInSwitchedOn && (
        <div className="flex flex-col gap-3 rounded-2xl border border-border bg-muted/40 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-start gap-3">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-background">
              <UtensilsCrossed className="h-4 w-4 text-primary" />
            </span>
            <div>
              <p className="text-sm font-semibold text-foreground">Dine-in is switched off</p>
              <p className="text-[13px] text-muted-foreground">
                Your tables and QRs are safe — flip it on to take orders, or manage them anyway below.
              </p>
            </div>
          </div>
          <Button size="sm" onClick={enableDineIn} disabled={enabling}>
            {enabling ? 'Switching on…' : 'Switch on dine-in'}
          </Button>
        </div>
      )}

      {tables.length === 0 && !isLoading ? (
        <Card>
          <CardContent className="p-6 sm:p-8">
            <p className="text-base font-semibold text-foreground">Set up dine-in in three steps</p>
            <p className="mt-1 text-sm text-muted-foreground">
              Five minutes, then every table orders for itself.
            </p>
            <ol className="mt-5 space-y-4">
              {STEPS.map((s) => (
                <li key={s.n} className="flex items-start gap-3">
                  <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground">
                    {s.n}
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-foreground">{s.title}</p>
                    <p className="text-[13px] text-muted-foreground">{s.desc}</p>
                    {s.n === 1 && (
                      <Button size="sm" className="mt-2" onClick={openCreate} disabled={tablesFull}>
                        <Plus className="mr-1.5 h-3.5 w-3.5" /> Add your first table
                      </Button>
                    )}
                    {s.n === 3 && (
                      <p className="mt-2 text-[13px] text-muted-foreground">
                        The <span className="font-medium text-foreground">Print all QRs</span> button appears up top once tables exist.
                      </p>
                    )}
                  </div>
                </li>
              ))}
            </ol>
          </CardContent>
        </Card>
      ) : (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <QrCode className="h-4 w-4" />
              Tables
            </CardTitle>
            <CardDescription>
              Tap the QR icon on any row for a printable code. Scanning connects the table to the order automatically.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <DataTable
              columns={columns}
              data={tables}
              isLoading={isLoading}
              emptyMessage="No dine-in tables yet. Add your first table to generate a QR order link."
            />
          </CardContent>
        </Card>
      )}

      <div className="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="flex items-center gap-2 text-sm font-medium">
              <Settings2 className="h-4 w-4 text-primary" /> What the scan does
            </CardTitle>
            <CardDescription className="text-xs">
              What happens when a guest scans your table QR.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <select
              className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
              value={settings?.dineInQrTarget ?? 'web_menu'}
              disabled={savingSettings}
              onChange={(e) => handleUpdateDineInOptions('dineInQrTarget', e.target.value)}
            >
              <option value="web_menu">Digital menu (table pre-tagged)</option>
              <option value="whatsapp_chat">WhatsApp chat (prefilled greeting)</option>
              <option value="dual_choice">Guest chooses menu or WhatsApp</option>
            </select>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="flex items-center gap-2 text-sm font-medium">
              <Settings2 className="h-4 w-4 text-primary" /> How tables pay
            </CardTitle>
            <CardDescription className="text-xs">
              How table bills get settled.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <select
              className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
              value={settings?.dineInPaymentTiming ?? 'pay_upfront'}
              disabled={savingSettings}
              onChange={(e) => handleUpdateDineInOptions('dineInPaymentTiming', e.target.value)}
            >
              <option value="pay_upfront">Pay now (M-Pesa / card per order)</option>
              <option value="open_tab">Open tab (pay staff after dining)</option>
              <option value="customer_choice">Guest chooses</option>
            </select>
          </CardContent>
        </Card>
      </div>

      {/* Hidden QR render targets for printing (SVGs serialized, never shown) */}
      <div aria-hidden className="hidden">
        {tables.map((t) => (
          <QRCodeSVG
            key={t.id}
            value={t.orderUrl}
            size={256}
            ref={(el) => {
              qrRefs.current.set(t.id, el)
            }}
          />
        ))}
      </div>

      {/* Per-table QR dialog */}
      <Dialog open={!!qrTarget} onOpenChange={(o) => !o && setQrTarget(null)}>
        <DialogContent className="max-w-xs text-center">
          <DialogHeader>
            <DialogTitle>{qrTarget?.name ?? 'Table QR'}</DialogTitle>
          </DialogHeader>
          {qrTarget && (
            <div className="space-y-3">
              <div className="mx-auto w-fit rounded-xl border bg-white p-4">
                <QRCodeSVG value={qrTarget.orderUrl} size={200} />
              </div>
              <p className="text-xs text-muted-foreground">Guests scan to open the menu for this table.</p>
              <div className="flex justify-center gap-2">
                <Button size="sm" variant="outline" onClick={() => copyUrl(qrTarget.orderUrl)}>
                  <Copy className="mr-1.5 h-3.5 w-3.5" /> Copy link
                </Button>
                <Button size="sm" onClick={() => printTables([qrTarget])}>
                  <Printer className="mr-1.5 h-3.5 w-3.5" /> Print
                </Button>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>

      <FormModal
        open={formOpen}
        onOpenChange={setFormOpen}
        title={editing ? 'Edit table' : 'Add table'}
        description="Give the table a name staff will recognize (e.g. 'Table 5' or 'Patio 2')."
        onSubmit={handleSubmit}
        isLoading={submitting}
        submitLabel={editing ? 'Save changes' : 'Create table'}
      >
        <div className="space-y-4">
          <InputField
            label="Table name"
            name="name"
            value={form.name}
            onChange={(v) => setForm((p) => ({ ...p, name: v }))}
            placeholder="Table 5"
          />
          <div className="grid grid-cols-2 gap-3">
            <InputField
              label="Code (optional)"
              name="code"
              value={form.code}
              onChange={(v) => setForm((p) => ({ ...p, code: v }))}
              placeholder="T5"
            />
            <InputField
              label="Seats (optional)"
              name="seats"
              type="number"
              value={form.seats}
              onChange={(v) => setForm((p) => ({ ...p, seats: v }))}
              placeholder="4"
            />
          </div>
          <SwitchField
            label="Active"
            name="isActive"
            checked={form.isActive}
            onCheckedChange={(v) => setForm((p) => ({ ...p, isActive: v }))}
          />
        </div>
      </FormModal>

      <ConfirmModal
        open={Boolean(deleteTarget)}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
        title="Delete table?"
        description="Its QR code will stop working immediately. Past orders placed from this table are unaffected."
        confirmLabel="Delete"
        onConfirm={handleDelete}
        isLoading={submitting}
        variant="destructive"
      />
    </div>
  )
}
