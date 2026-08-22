'use client'

import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { DataTable, type Column } from '@/components/shared/data-table'
import { FormModal, ConfirmModal } from '@/components/shared/modal'
import { InputField, SwitchField } from '@/components/shared/form-field'
import { useDineInTables, useCompanySettings, type DineInTable } from '@/lib/api-hooks'
import { createDineInTable, updateDineInTable, deleteDineInTable, updateSettings } from '@/lib/api-actions'
import { Plus, Edit, Trash2, Copy, QrCode, MessageSquare, Globe, ExternalLink } from 'lucide-react'
import { useSWRConfig } from 'swr'
import { toast } from 'sonner'
import { Label } from '@/components/ui/label'

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

export default function DineInPage() {
  const { data, isLoading } = useDineInTables()
  const { data: settings } = useCompanySettings()
  const { mutate } = useSWRConfig()
  const tables = data?.tables ?? []

  const [savingSettings, setSavingSettings] = useState(false)
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<DineInTable | null>(null)
  const [form, setForm] = useState<TableFormData>(initialForm)
  const [submitting, setSubmitting] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<DineInTable | null>(null)

  const refresh = () => {
    mutate(['dine-in-tables'])
    mutate('company-settings')
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
      header: 'QR Order Destinations',
      cell: (t) => (
        <div className="space-y-1.5 py-1">
          <div className="flex items-center gap-1.5 text-xs">
            <Globe className="h-3.5 w-3.5 text-primary shrink-0" />
            <span className="text-[11px] font-medium text-muted-foreground w-14 shrink-0">Web Menu:</span>
            <code className="max-w-[150px] truncate text-[11px]">{t.orderUrl}</code>
            <Button variant="ghost" size="icon" className="h-6 w-6" onClick={() => copyUrl(t.orderUrl)} title="Copy Web Menu Link">
              <Copy className="h-3 w-3" />
            </Button>
            <Button variant="ghost" size="icon" className="h-6 w-6" asChild title="Open Web Menu">
              <a href={t.orderUrl} target="_blank" rel="noreferrer">
                <ExternalLink className="h-3 w-3" />
              </a>
            </Button>
          </div>
          {t.whatsappOrderUrl && (
            <div className="flex items-center gap-1.5 text-xs">
              <MessageSquare className="h-3.5 w-3.5 text-green-600 shrink-0" />
              <span className="text-[11px] font-medium text-muted-foreground w-14 shrink-0">WhatsApp:</span>
              <code className="max-w-[150px] truncate text-[11px]">{t.whatsappOrderUrl}</code>
              <Button variant="ghost" size="icon" className="h-6 w-6" onClick={() => copyUrl(t.whatsappOrderUrl!)} title="Copy WhatsApp Scan-to-Chat Link">
                <Copy className="h-3 w-3" />
              </Button>
              <Button variant="ghost" size="icon" className="h-6 w-6" asChild title="Open WhatsApp Chat">
                <a href={t.whatsappOrderUrl} target="_blank" rel="noreferrer">
                  <ExternalLink className="h-3 w-3" />
                </a>
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
    <div className="space-y-6 p-6">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Dine-in tables</h1>
          <p className="text-sm text-muted-foreground">
            Create tables, print QR codes, and let guests order straight from their seats via Web or WhatsApp.
          </p>
        </div>
        <Button onClick={openCreate}>
          <Plus className="mr-2 h-4 w-4" />
          Add table
        </Button>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-sm font-medium">Table QR Code Behavior</CardTitle>
            <CardDescription className="text-xs">
              Controls what happens when a dining customer scans your table QR code.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-2">
            <select
              className="w-full h-9 rounded-md border border-input bg-background px-3 text-sm"
              value={settings?.dineInQrTarget ?? 'web_menu'}
              disabled={savingSettings}
              onChange={(e) => handleUpdateDineInOptions('dineInQrTarget', e.target.value)}
            >
              <option value="web_menu">Digital Web Menu (Opens Storefront with Table pre-tagged)</option>
              <option value="whatsapp_chat">WhatsApp Chat (Opens WhatsApp with prefilled Table greeting)</option>
              <option value="dual_choice">Dual Choice Landing (Guest chooses Web Menu or WhatsApp)</option>
            </select>
            <p className="text-[11px] text-muted-foreground">
              WhatsApp Scan-to-Chat enables instant table orders with zero address entry.
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-sm font-medium">Table Billing & Payment Timing</CardTitle>
            <CardDescription className="text-xs">
              Determine how table dining bills are settled.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-2">
            <select
              className="w-full h-9 rounded-md border border-input bg-background px-3 text-sm"
              value={settings?.dineInPaymentTiming ?? 'pay_upfront'}
              disabled={savingSettings}
              onChange={(e) => handleUpdateDineInOptions('dineInPaymentTiming', e.target.value)}
            >
              <option value="pay_upfront">Pay Upfront (Instant M-Pesa / Card checkout per order)</option>
              <option value="open_tab">Open Table Tab (Kitchen prepares order; guest pays staff after dining)</option>
              <option value="customer_choice">Customer Choice (Guest chooses pay now or pay later)</option>
            </select>
            <p className="text-[11px] text-muted-foreground">
              With Open Tab, table orders are routed directly to the kitchen/dashboard without blocking for payment.
            </p>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <QrCode className="h-4 w-4" />
            Tables
          </CardTitle>
          <CardDescription>
            Each table gets a unique QR link. Scanning it connects the table to the order automatically.
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

      <FormModal
        open={formOpen}
        onOpenChange={setFormOpen}
        title={editing ? 'Edit table' : 'Add table'}
        description="Give the table a name customers will recognize (e.g. 'Table 5' or 'Patio 2')."
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
