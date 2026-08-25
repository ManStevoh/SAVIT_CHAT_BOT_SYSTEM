'use client'

import { useCallback, useEffect, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { DataTable, type Column } from '@/components/shared/data-table'
import { FormModal, ConfirmModal } from '@/components/shared/modal'
import { InputField, SwitchField } from '@/components/shared/form-field'
import { useCompanySettings, useDeliveryZones, type DeliveryZone } from '@/lib/api-hooks'
import {
  createDeliveryZone,
  updateDeliveryZone,
  deleteDeliveryZone,
  updateSettings,
} from '@/lib/api-actions'
import { Plus, Edit, Trash2, Truck, Loader2 } from 'lucide-react'
import { useSWRConfig } from 'swr'
import { toast } from 'sonner'

interface ZoneFormData {
  name: string
  fee: string
  keywords: string
  minOrderAmount: string
  isActive: boolean
  sortOrder: string
}

const initialForm: ZoneFormData = {
  name: '',
  fee: '',
  keywords: '',
  minOrderAmount: '',
  isActive: true,
  sortOrder: '0',
}

export default function DeliveryPage() {
  const { data: zones, isLoading } = useDeliveryZones()
  const { data: settings } = useCompanySettings()
  const { mutate } = useSWRConfig()

  const [feesEnabled, setFeesEnabled] = useState(false)
  const [defaultFee, setDefaultFee] = useState('0')
  const [freeAbove, setFreeAbove] = useState('')
  const [savingSettings, setSavingSettings] = useState(false)
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<DeliveryZone | null>(null)
  const [form, setForm] = useState<ZoneFormData>(initialForm)
  const [submitting, setSubmitting] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<DeliveryZone | null>(null)

  useEffect(() => {
    if (!settings) return
    setFeesEnabled(Boolean(settings.deliveryFeesEnabled))
    setDefaultFee(String(settings.defaultDeliveryFee ?? 0))
    setFreeAbove(settings.freeDeliveryAbove != null ? String(settings.freeDeliveryAbove) : '')
  }, [settings])

  const refresh = useCallback(() => {
    mutate(['delivery-zones'])
    mutate('company-settings')
  }, [mutate])

  const openCreate = () => {
    setEditing(null)
    setForm(initialForm)
    setFormOpen(true)
  }

  const openEdit = (zone: DeliveryZone) => {
    setEditing(zone)
    setForm({
      name: zone.name,
      fee: String(zone.fee),
      keywords: (zone.keywords || []).join(', '),
      minOrderAmount: zone.minOrderAmount != null ? String(zone.minOrderAmount) : '',
      isActive: zone.isActive,
      sortOrder: String(zone.sortOrder ?? 0),
    })
    setFormOpen(true)
  }

  const saveFeeSettings = async () => {
    setSavingSettings(true)
    try {
      const res = await updateSettings({
        deliveryFeesEnabled: feesEnabled,
        defaultDeliveryFee: parseFloat(defaultFee) || 0,
        freeDeliveryAbove: freeAbove.trim() === '' ? null : parseFloat(freeAbove),
      })
      if (!res.success) {
        toast.error(res.message || 'Failed to save delivery settings')
        return
      }
      toast.success('Delivery settings saved')
      refresh()
    } finally {
      setSavingSettings(false)
    }
  }

  const handleSubmit = async () => {
    const feeNum = parseFloat(form.fee)
    if (!form.name.trim() || Number.isNaN(feeNum) || feeNum < 0) {
      toast.error('Enter a zone name and a fee of 0 or more')
      return
    }
    setSubmitting(true)
    try {
      const keywords = form.keywords
        .split(',')
        .map((k) => k.trim())
        .filter(Boolean)
      const payload = {
        name: form.name.trim(),
        fee: feeNum,
        keywords,
        minOrderAmount: form.minOrderAmount.trim() === '' ? null : parseFloat(form.minOrderAmount),
        isActive: form.isActive,
        sortOrder: parseInt(form.sortOrder, 10) || 0,
      }
      const res = editing
        ? await updateDeliveryZone(editing.id, payload)
        : await createDeliveryZone(payload)
      if (!res.success) {
        toast.error(res.message || 'Failed to save zone')
        return
      }
      toast.success(editing ? 'Zone updated' : 'Zone created')
      setFormOpen(false)
      refresh()
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setSubmitting(true)
    try {
      const res = await deleteDeliveryZone(deleteTarget.id)
      if (!res.success) {
        toast.error(res.message || 'Failed to delete zone')
        return
      }
      toast.success('Zone deleted')
      setDeleteTarget(null)
      refresh()
    } finally {
      setSubmitting(false)
    }
  }

  const columns: Column<DeliveryZone>[] = [
    {
      key: 'name',
      header: 'Zone',
      cell: (row) => (
        <div>
          <p className="font-medium">{row.name}</p>
          <p className="text-xs text-muted-foreground">
            {(row.keywords || []).join(', ') || 'No address keywords'}
          </p>
        </div>
      ),
    },
    {
      key: 'fee',
      header: 'Fee',
      cell: (row) => <span>{Number(row.fee).toFixed(2)}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      cell: (row) => (
        <Badge variant={row.isActive ? 'default' : 'secondary'}>
          {row.isActive ? 'Active' : 'Off'}
        </Badge>
      ),
    },
    {
      key: 'actions',
      header: '',
      cell: (row) => (
        <div className="flex justify-end gap-1">
          <Button variant="ghost" size="icon" onClick={() => openEdit(row)}>
            <Edit className="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon" onClick={() => setDeleteTarget(row)}>
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      ),
    },
  ]

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Delivery</h1>
          <p className="text-sm text-muted-foreground">
            Set default fees, free-delivery thresholds, and zones matched by address keywords.
          </p>
        </div>
        <Button onClick={openCreate}>
          <Plus className="mr-2 h-4 w-4" />
          Add zone
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Truck className="h-5 w-5" />
            Fee settings
          </CardTitle>
          <CardDescription>
            Applied during WhatsApp checkout and public storefront when fulfillment is delivery.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <SwitchField
            label="Enable delivery fees"
            name="deliveryFeesEnabled"
            checked={feesEnabled}
            onCheckedChange={setFeesEnabled}
          />
          <div className="grid gap-4 sm:grid-cols-2">
            <InputField
              label="Default fee"
              name="defaultDeliveryFee"
              type="number"
              value={defaultFee}
              onChange={setDefaultFee}
              placeholder="0"
            />
            <InputField
              label="Free delivery above (optional)"
              name="freeDeliveryAbove"
              type="number"
              value={freeAbove}
              onChange={setFreeAbove}
              placeholder="e.g. 50"
            />
          </div>
          <Button onClick={() => void saveFeeSettings()} disabled={savingSettings}>
            {savingSettings ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
            Save fee settings
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Delivery zones</CardTitle>
          <CardDescription>
            Match customer addresses by keyword (e.g. &quot;Westlands&quot;) and charge a zone fee.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={columns}
            data={zones || []}
            isLoading={isLoading}
            emptyMessage="No delivery zones yet. Add one to charge by area."
          />
        </CardContent>
      </Card>

      <FormModal
        open={formOpen}
        onOpenChange={setFormOpen}
        title={editing ? 'Edit zone' : 'Add zone'}
        onSubmit={() => void handleSubmit()}
        isLoading={submitting}
      >
        <div className="space-y-4">
          <InputField
            label="Name"
            name="name"
            value={form.name}
            onChange={(value) => setForm((f) => ({ ...f, name: value }))}
            placeholder="Westlands"
          />
          <InputField
            label="Fee"
            name="fee"
            type="number"
            value={form.fee}
            onChange={(value) => setForm((f) => ({ ...f, fee: value }))}
          />
          <InputField
            label="Address keywords (comma-separated)"
            name="keywords"
            value={form.keywords}
            onChange={(value) => setForm((f) => ({ ...f, keywords: value }))}
            placeholder="westlands, parklands"
          />
          <InputField
            label="Min order amount (optional)"
            name="minOrderAmount"
            type="number"
            value={form.minOrderAmount}
            onChange={(value) => setForm((f) => ({ ...f, minOrderAmount: value }))}
          />
          <InputField
            label="Sort order"
            name="sortOrder"
            type="number"
            value={form.sortOrder}
            onChange={(value) => setForm((f) => ({ ...f, sortOrder: value }))}
          />
          <SwitchField
            label="Active"
            name="isActive"
            checked={form.isActive}
            onCheckedChange={(checked) => setForm((f) => ({ ...f, isActive: checked }))}
          />
        </div>
      </FormModal>

      <ConfirmModal
        open={!!deleteTarget}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
        title="Delete delivery zone?"
        description={`Remove "${deleteTarget?.name ?? ''}"? Orders will fall back to the default fee.`}
        onConfirm={() => void handleDelete()}
        isLoading={submitting}
        variant="destructive"
      />
    </div>
  )
}
