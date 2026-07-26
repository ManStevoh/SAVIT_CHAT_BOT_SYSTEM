'use client'

import { useCallback, useEffect, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { DataTable, type Column } from '@/components/shared/data-table'
import { FormModal, ConfirmModal } from '@/components/shared/modal'
import { InputField, SelectField, SwitchField } from '@/components/shared/form-field'
import { useCompanySettings, useTaxRates, type TaxRate } from '@/lib/api-hooks'
import {
  createTaxRate,
  updateTaxRate,
  deleteTaxRate,
  updateSettings,
} from '@/lib/api-actions'
import { Plus, Edit, Trash2, Percent, Loader2 } from 'lucide-react'
import { useSWRConfig } from 'swr'
import { toast } from 'sonner'

interface TaxFormData {
  name: string
  code: string
  rate: string
  isInclusive: boolean
  isDefault: boolean
  isActive: boolean
}

const initialForm: TaxFormData = {
  name: '',
  code: '',
  rate: '',
  isInclusive: false,
  isDefault: false,
  isActive: true,
}

export default function TaxesPage() {
  const { data: rates, isLoading } = useTaxRates()
  const { data: settings } = useCompanySettings()
  const { mutate } = useSWRConfig()

  const [taxEnabled, setTaxEnabled] = useState(false)
  const [savingEnabled, setSavingEnabled] = useState(false)
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<TaxRate | null>(null)
  const [form, setForm] = useState<TaxFormData>(initialForm)
  const [submitting, setSubmitting] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<TaxRate | null>(null)

  useEffect(() => {
    if (settings?.taxEnabled != null) {
      setTaxEnabled(Boolean(settings.taxEnabled))
    }
  }, [settings?.taxEnabled])

  const refresh = useCallback(() => {
    mutate(['tax-rates'])
    mutate('company-settings')
  }, [mutate])

  const openCreate = () => {
    setEditing(null)
    setForm(initialForm)
    setFormOpen(true)
  }

  const openEdit = (rate: TaxRate) => {
    setEditing(rate)
    setForm({
      name: rate.name,
      code: rate.code ?? '',
      rate: String(rate.rate),
      isInclusive: rate.isInclusive,
      isDefault: rate.isDefault,
      isActive: rate.isActive,
    })
    setFormOpen(true)
  }

  const handleSaveEnabled = async (enabled: boolean) => {
    setTaxEnabled(enabled)
    setSavingEnabled(true)
    try {
      const res = await updateSettings({ taxEnabled: enabled })
      if (!res.success) {
        setTaxEnabled(!enabled)
        toast.error(res.message || 'Failed to update tax setting')
        return
      }
      toast.success(enabled ? 'Tax calculation enabled' : 'Tax calculation disabled')
      refresh()
    } finally {
      setSavingEnabled(false)
    }
  }

  const handleSubmit = async () => {
    const rateNum = parseFloat(form.rate)
    if (!form.name.trim() || Number.isNaN(rateNum) || rateNum < 0 || rateNum > 100) {
      toast.error('Enter a name and a rate between 0 and 100')
      return
    }
    setSubmitting(true)
    try {
      const payload = {
        name: form.name.trim(),
        code: form.code.trim() || null,
        rate: rateNum,
        isInclusive: form.isInclusive,
        isDefault: form.isDefault,
        isActive: form.isActive,
      }
      const res = editing
        ? await updateTaxRate(editing.id, payload)
        : await createTaxRate(payload)
      if (!res.success) {
        toast.error(res.message || 'Failed to save tax rate')
        return
      }
      toast.success(editing ? 'Tax rate updated' : 'Tax rate created')
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
      const res = await deleteTaxRate(deleteTarget.id)
      if (!res.success) {
        toast.error(res.message || 'Failed to delete')
        return
      }
      toast.success('Tax rate deleted')
      setDeleteTarget(null)
      refresh()
    } finally {
      setSubmitting(false)
    }
  }

  const columns: Column<TaxRate>[] = [
    {
      key: 'name',
      header: 'Name',
      cell: (rate) => (
        <div className="flex items-center gap-2">
          <span className="font-medium">{rate.name}</span>
          {rate.code ? <Badge variant="outline">{rate.code}</Badge> : null}
          {rate.isDefault ? <Badge>Default</Badge> : null}
        </div>
      ),
    },
    {
      key: 'rate',
      header: 'Rate',
      cell: (rate) => `${rate.rate}%`,
    },
    {
      key: 'inclusive',
      header: 'Prices',
      cell: (rate) => (rate.isInclusive ? 'Tax included' : 'Tax added'),
    },
    {
      key: 'status',
      header: 'Status',
      cell: (rate) => (
        <Badge variant={rate.isActive ? 'default' : 'secondary'}>
          {rate.isActive ? 'Active' : 'Inactive'}
        </Badge>
      ),
    },
    {
      key: 'actions',
      header: '',
      cell: (rate) => (
        <div className="flex justify-end gap-1">
          <Button variant="ghost" size="icon" onClick={() => openEdit(rate)} aria-label="Edit">
            <Edit className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            onClick={() => setDeleteTarget(rate)}
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
          <h1 className="text-2xl font-semibold tracking-tight">Taxes</h1>
          <p className="text-sm text-muted-foreground">
            Define VAT/GST or sales tax rates for your catalog. Map them on products or set a company default.
          </p>
        </div>
        <Button onClick={openCreate} disabled={!taxEnabled}>
          <Plus className="mr-2 h-4 w-4" />
          Add tax rate
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Percent className="h-4 w-4" />
            Tax calculation
          </CardTitle>
          <CardDescription>
            When enabled, checkout adds (or extracts) tax using your rates. Payment gateways charge the grand total.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <SwitchField
            label="Enable tax on orders"
            name="taxEnabled"
            checked={taxEnabled}
            onCheckedChange={handleSaveEnabled}
            disabled={savingEnabled}
          />
          {savingEnabled ? (
            <p className="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
              <Loader2 className="h-3 w-3 animate-spin" /> Saving…
            </p>
          ) : null}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Tax rates</CardTitle>
          <CardDescription>
            Products without a specific rate use the default. Historical orders keep a frozen tax snapshot.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <DataTable
            columns={columns}
            data={rates ?? []}
            isLoading={isLoading}
            emptyMessage={
              taxEnabled
                ? 'No tax rates yet. Add VAT, GST, or a local sales tax.'
                : 'Enable tax calculation above, then add rates.'
            }
          />
        </CardContent>
      </Card>

      <FormModal
        open={formOpen}
        onOpenChange={setFormOpen}
        title={editing ? 'Edit tax rate' : 'Add tax rate'}
        description="Rate is a percentage (e.g. 16 for 16% VAT)."
        onSubmit={handleSubmit}
        isLoading={submitting}
        submitLabel={editing ? 'Save changes' : 'Create rate'}
      >
        <div className="space-y-4">
          <InputField
            label="Name"
            name="name"
            value={form.name}
            onChange={(v) => setForm((p) => ({ ...p, name: v }))}
            placeholder="VAT"
          />
          <InputField
            label="Code (optional)"
            name="code"
            value={form.code}
            onChange={(v) => setForm((p) => ({ ...p, code: v }))}
            placeholder="VAT"
          />
          <InputField
            label="Rate (%)"
            name="rate"
            type="number"
            value={form.rate}
            onChange={(v) => setForm((p) => ({ ...p, rate: v }))}
            placeholder="16"
          />
          <SelectField
            label="Price basis"
            name="isInclusive"
            value={form.isInclusive ? 'inclusive' : 'exclusive'}
            onChange={(v) => setForm((p) => ({ ...p, isInclusive: v === 'inclusive' }))}
            options={[
              { value: 'exclusive', label: 'Tax exclusive — tax is added on top of catalog price' },
              { value: 'inclusive', label: 'Tax inclusive — catalog price already includes tax' },
            ]}
          />
          <SwitchField
            label="Default rate for products without a mapped tax"
            name="isDefault"
            checked={form.isDefault}
            onCheckedChange={(v) => setForm((p) => ({ ...p, isDefault: v }))}
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
        title="Delete tax rate?"
        description="Products using this rate will fall back to the company default (or no tax). Past orders keep their snapshot."
        confirmLabel="Delete"
        onConfirm={handleDelete}
        isLoading={submitting}
        variant="destructive"
      />
    </div>
  )
}
