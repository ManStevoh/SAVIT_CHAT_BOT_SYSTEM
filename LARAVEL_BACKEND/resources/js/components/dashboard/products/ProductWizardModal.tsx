'use client'

import { useEffect, useId, useMemo, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { cn } from '@/lib/utils'
import {
  ArrowLeft,
  ArrowRight,
  CalendarClock,
  Check,
  ChevronDown,
  Download,
  Handshake,
  ImagePlus,
  Link2,
  Loader2,
  Lock,
  Package,
  Truck,
  X,
} from 'lucide-react'
import type { TaxRate } from '@/lib/api-hooks'

export interface ProductFormFields {
  name: string
  description: string
  metaTitle: string
  metaDescription: string
  slug: string
  price: string
  compareAtPrice: string
  taxRateId: string
  category: string
  productType: 'physical' | 'digital' | 'service'
  fulfillmentType: 'shipping' | 'download' | 'link' | 'booking' | 'manual'
  trackInventory: boolean
  requiresDeliveryAddress: boolean
  accessUrl: string
  serviceBookingUrl: string
  fulfillmentInstructions: string
  licenseKeyMode: 'none' | 'auto' | 'pool'
  licenseKeyPrefix: string
  accessExpiresDays: string
  maxDownloads: string
  bookable: boolean
  bookingDurationMinutes: string
  licenseKeys: string
  stock: string
}

export const emptyProductFields: ProductFormFields = {
  name: '',
  description: '',
  metaTitle: '',
  metaDescription: '',
  slug: '',
  price: '',
  compareAtPrice: '',
  taxRateId: 'none',
  category: '',
  productType: 'physical',
  fulfillmentType: 'shipping',
  trackInventory: true,
  requiresDeliveryAddress: true,
  accessUrl: '',
  serviceBookingUrl: '',
  fulfillmentInstructions: '',
  licenseKeyMode: 'none',
  licenseKeyPrefix: '',
  accessExpiresDays: '',
  maxDownloads: '',
  bookable: false,
  bookingDurationMinutes: '',
  licenseKeys: '',
  stock: '',
}

/** Delivery cards — one choice replaces the old item-type + fulfillment dropdowns. */
type CardId = 'delivered' | 'download' | 'link' | 'appointment' | 'manual'

const CARDS: { id: CardId; title: string; desc: string; icon: typeof Truck }[] = [
  { id: 'delivered', title: 'Delivered', desc: 'Shipped or handed over', icon: Truck },
  { id: 'download', title: 'Download', desc: 'File after payment', icon: Download },
  { id: 'link', title: 'Access link', desc: 'Course, portal, drive', icon: Link2 },
  { id: 'appointment', title: 'Appointment', desc: 'Bookable service', icon: CalendarClock },
  { id: 'manual', title: 'Manual', desc: 'You arrange delivery', icon: Handshake },
]

export function cardFor(productType: string, fulfillmentType: string): CardId {
  if (fulfillmentType === 'download') return 'download'
  if (fulfillmentType === 'link') return 'link'
  if (fulfillmentType === 'booking') return 'appointment'
  if (fulfillmentType === 'manual') return 'manual'
  if (productType === 'service') return 'appointment'
  if (productType === 'digital') return 'download'
  return 'delivered'
}

function validPrice(v: string) {
  return v !== '' && !Number.isNaN(parseFloat(v)) && parseFloat(v) >= 0
}

export function validateProductFields(d: ProductFormFields): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!d.name.trim()) errors.name = 'Give your product a name'
  if (!validPrice(d.price)) errors.price = 'Enter a price of 0 or more'
  if (!d.category.trim()) errors.category = 'Pick or type a category'
  if (d.maxDownloads.trim() !== '' && (!Number.isInteger(Number(d.maxDownloads)) || Number(d.maxDownloads) < 1)) {
    errors.maxDownloads = 'Must be a whole number of at least 1'
  }
  if (d.bookable && d.bookingDurationMinutes.trim() !== '') {
    const n = Number(d.bookingDurationMinutes)
    if (!Number.isInteger(n) || n < 5 || n > 480) errors.bookingDurationMinutes = 'Between 5 and 480 minutes'
  }
  if (d.stock.trim() !== '' && (Number.isNaN(parseInt(d.stock, 10)) || parseInt(d.stock, 10) < 0)) {
    errors.stock = 'Must be 0 or more'
  }
  return errors
}

export interface WizardFiles {
  image: File | null
  digital: File | null
}

interface EditExtras {
  existingImageUrl?: string | null
  digitalFileName?: string | null
  licenseKeysAvailable?: number
  onClearDigitalFile: () => void
  clearingFile: boolean
  onUploadExtraImage: (f: File) => void
  extraUploading: boolean
}

function FieldError({ message }: { message?: string }) {
  if (!message) return null
  return <p className="mt-1 text-xs text-destructive">{message}</p>
}

export function ProductWizardModal({
  open,
  onOpenChange,
  mode,
  initial,
  resetKey,
  categories,
  taxRates,
  currencyCode,
  allowService,
  editExtras,
  isSubmitting,
  submitLabel,
  onSubmit,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  mode: 'add' | 'edit'
  initial: ProductFormFields
  resetKey: string | number
  categories: string[]
  taxRates: TaxRate[]
  currencyCode?: string
  allowService: boolean
  editExtras?: EditExtras
  isSubmitting: boolean
  submitLabel: string
  onSubmit: (data: ProductFormFields, files: WizardFiles) => Promise<{ ok: boolean; message?: string }>
}) {
  const [step, setStep] = useState(1)
  const [data, setData] = useState<ProductFormFields>(initial)
  const [imageFile, setImageFile] = useState<File | null>(null)
  const [digitalFile, setDigitalFile] = useState<File | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [serverMessage, setServerMessage] = useState<string | null>(null)
  const [seoOpen, setSeoOpen] = useState(false)
  const [digitalAdvancedOpen, setDigitalAdvancedOpen] = useState(false)
  const datalistId = useId()

  // Reset whenever (re)opened
  useEffect(() => {
    if (open) {
      setData({ ...initial })
      setImageFile(null)
      setDigitalFile(null)
      setErrors({})
      setServerMessage(null)
      setStep(1)
      setSeoOpen(!!(initial.metaTitle || initial.metaDescription || initial.slug))
      setDigitalAdvancedOpen(
        initial.licenseKeyMode !== 'none' || !!initial.accessExpiresDays || !!initial.maxDownloads
      )
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, resetKey])

  const set = <K extends keyof ProductFormFields>(field: K, value: ProductFormFields[K]) => {
    setData((prev) => ({ ...prev, [field]: value }))
    setErrors((prev) => {
      if (!prev[field as string]) return prev
      const next = { ...prev }
      delete next[field as string]
      return next
    })
  }

  const card = cardFor(data.productType, data.fulfillmentType)
  const showAppointment = allowService || card === 'appointment'

  const applyCard = (id: CardId) => {
    setErrors((prev) => {
      const next = { ...prev }
      delete next.stock
      return next
    })
    if (id === 'delivered') {
      setData((p) => ({ ...p, productType: 'physical', fulfillmentType: 'shipping', trackInventory: true, requiresDeliveryAddress: true }))
    } else if (id === 'download') {
      setData((p) => ({ ...p, productType: 'digital', fulfillmentType: 'download', trackInventory: false, requiresDeliveryAddress: false, stock: '0', bookable: false }))
    } else if (id === 'link') {
      setData((p) => ({ ...p, productType: 'digital', fulfillmentType: 'link', trackInventory: false, requiresDeliveryAddress: false, stock: '0', bookable: false }))
    } else if (id === 'appointment') {
      setData((p) => ({ ...p, productType: 'service', fulfillmentType: 'booking', trackInventory: false, requiresDeliveryAddress: false, stock: '0', bookable: true }))
    } else {
      setData((p) => ({ ...p, productType: 'physical', fulfillmentType: 'manual', trackInventory: true, requiresDeliveryAddress: true }))
    }
  }

  const step1Valid = !validateProductFields({ ...data, maxDownloads: '', bookingDurationMinutes: '', stock: '' })
  const fullErrors = useMemo(() => validateProductFields(data), [data])

  const goNext = () => {
    if (step === 1) {
      const errs = validateProductFields({ ...data, maxDownloads: '', bookingDurationMinutes: '', stock: '' })
      setErrors(errs)
      if (Object.keys(errs).length === 0) setStep(2)
    } else if (step === 2) {
      setStep(3)
    }
  }

  const finish = async () => {
    setErrors(fullErrors)
    if (Object.keys(fullErrors).length > 0) {
      // jump to the step holding the first error
      if (fullErrors.name || fullErrors.price || fullErrors.category) setStep(1)
      else setStep(3)
      return
    }
    setServerMessage(null)
    const res = await onSubmit(data, { image: imageFile, digital: digitalFile })
    if (!res.ok) setServerMessage(res.message ?? 'Could not save. Check the fields and try again.')
  }

  const imagePreview = useMemo(() => {
    if (imageFile) return URL.createObjectURL(imageFile)
    return editExtras?.existingImageUrl ?? null
  }, [imageFile, editExtras?.existingImageUrl])

  const steps = [
    { n: 1, label: 'Basics' },
    { n: 2, label: 'Delivery' },
    { n: 3, label: 'Finish' },
  ]

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex max-h-[92vh] w-[calc(100vw-2rem)] max-w-2xl flex-col p-0">
        <DialogHeader className="border-b px-5 pb-4 pt-5 sm:px-6">
          <DialogTitle>{mode === 'add' ? 'Add product' : 'Edit product'}</DialogTitle>
          <DialogDescription>
            {step === 1 && 'Name it, price it, show it.'}
            {step === 2 && 'How does the customer receive it?'}
            {step === 3 && 'Everything else is optional.'}
          </DialogDescription>
          <ol className="mt-3 flex items-center gap-1.5">
            {steps.map((s, i) => {
              const done = step > s.n
              const active = step === s.n
              const clickable = s.n < step || (s.n === 2 && step1Valid)
              return (
                <li key={s.n} className="flex flex-1 items-center gap-1.5 last:flex-none">
                  <button
                    type="button"
                    disabled={!clickable || isSubmitting}
                    onClick={() => setStep(s.n)}
                    className={cn(
                      'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold transition-colors',
                      active
                        ? 'bg-primary text-primary-foreground'
                        : done
                          ? 'bg-primary/15 text-primary'
                          : 'bg-muted text-muted-foreground',
                      !clickable && 'opacity-50'
                    )}
                  >
                    {done ? <Check className="h-3 w-3" /> : s.n}
                  </button>
                  <span className={cn('hidden text-xs sm:block', active ? 'font-semibold text-foreground' : 'text-muted-foreground')}>
                    {s.label}
                  </span>
                  {i < steps.length - 1 && <span className="mx-1 h-px flex-1 bg-border" />}
                </li>
              )
            })}
          </ol>
        </DialogHeader>

        <div className="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6">
          {serverMessage && (
            <p className="mb-4 rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-[13px] text-destructive">
              {serverMessage}
            </p>
          )}

          {/* STEP 1 — BASICS */}
          {step === 1 && (
            <div className="space-y-4">
              <div className="flex items-start gap-4">
                <label className="group relative flex h-24 w-24 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-xl border border-dashed border-border bg-muted/40 transition-colors hover:border-primary/50">
                  {imagePreview ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={imagePreview} alt="" className="h-full w-full object-cover" />
                  ) : (
                    <ImagePlus className="h-6 w-6 text-muted-foreground" />
                  )}
                  <span className="absolute inset-x-0 bottom-0 bg-background/85 py-1 text-center text-[10px] font-medium text-foreground opacity-0 transition-opacity group-hover:opacity-100">
                    {imagePreview ? 'Replace' : 'Add photo'}
                  </span>
                  <input
                    type="file"
                    accept="image/*"
                    className="hidden"
                    disabled={isSubmitting}
                    onChange={(e) => setImageFile(e.target.files?.[0] ?? null)}
                  />
                </label>
                <div className="min-w-0 flex-1 space-y-3">
                  <div className="space-y-1.5">
                    <Label htmlFor="pw-name">Product name</Label>
                    <Input
                      id="pw-name"
                      value={data.name}
                      onChange={(e) => set('name', e.target.value)}
                      placeholder="e.g. Kenyan AA Coffee 500g"
                      autoFocus
                    />
                    <FieldError message={errors.name} />
                  </div>
                  {mode === 'edit' && editExtras && (
                    <div className="space-y-1.5">
                      <Label>More photos</Label>
                      <Input
                        type="file"
                        accept="image/*"
                        disabled={editExtras.extraUploading || isSubmitting}
                        onChange={(e) => {
                          const f = e.target.files?.[0]
                          if (f) editExtras.onUploadExtraImage(f)
                          e.currentTarget.value = ''
                        }}
                        className="h-9 text-xs"
                      />
                    </div>
                  )}
                </div>
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label htmlFor="pw-price">Price{currencyCode ? ` (${currencyCode})` : ''}</Label>
                  <Input
                    id="pw-price"
                    type="number"
                    min={0}
                    value={data.price}
                    onChange={(e) => set('price', e.target.value)}
                    placeholder="0.00"
                  />
                  <FieldError message={errors.price} />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="pw-compare">Sale price <span className="font-normal text-muted-foreground">(optional)</span></Label>
                  <Input
                    id="pw-compare"
                    type="number"
                    min={0}
                    value={data.compareAtPrice}
                    onChange={(e) => set('compareAtPrice', e.target.value)}
                    placeholder="Was…"
                  />
                  <p className="text-[11px] text-muted-foreground">Higher than price shows a Sale badge.</p>
                </div>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="pw-category">Category</Label>
                <Input
                  id="pw-category"
                  list={datalistId}
                  value={data.category}
                  onChange={(e) => set('category', e.target.value)}
                  placeholder="e.g. Coffee, Coaching, Templates"
                />
                <datalist id={datalistId}>
                  {categories.map((c) => (
                    <option key={c} value={c} />
                  ))}
                </datalist>
                <FieldError message={errors.category} />
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="pw-desc">Description <span className="font-normal text-muted-foreground">(optional)</span></Label>
                <Textarea
                  id="pw-desc"
                  value={data.description}
                  onChange={(e) => set('description', e.target.value)}
                  rows={3}
                  placeholder="What makes it great? Shown to customers and the AI."
                />
              </div>
            </div>
          )}

          {/* STEP 2 — DELIVERY */}
          {step === 2 && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {CARDS.map((c) => {
                  if (c.id === 'appointment' && !showAppointment) {
                    return (
                      <div
                        key={c.id}
                        title="Services are on Growth and above"
                        className="flex cursor-not-allowed flex-col items-start gap-1 rounded-xl border border-border bg-muted/30 p-3 opacity-60"
                      >
                        <Lock className="h-4 w-4 text-muted-foreground" />
                        <p className="text-[13px] font-semibold text-muted-foreground">{c.title}</p>
                        <p className="text-[11px] text-muted-foreground">Growth plan</p>
                      </div>
                    )
                  }
                  const active = card === c.id
                  return (
                    <button
                      key={c.id}
                      type="button"
                      disabled={isSubmitting}
                      onClick={() => applyCard(c.id)}
                      className={cn(
                        'flex flex-col items-start gap-1 rounded-xl border p-3 text-left transition-colors',
                        active ? 'border-primary bg-primary/[0.06]' : 'border-border hover:border-muted-foreground/40 hover:bg-muted/40'
                      )}
                    >
                      <c.icon className={cn('h-4 w-4', active ? 'text-primary' : 'text-muted-foreground')} />
                      <p className="text-[13px] font-semibold text-foreground">{c.title}</p>
                      <p className="text-[11px] text-muted-foreground">{c.desc}</p>
                    </button>
                  )
                })}
              </div>

              {card === 'delivered' && (
                <div className="space-y-3 rounded-xl bg-muted/40 p-4">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="text-sm font-medium text-foreground">Track stock</p>
                      <p className="text-xs text-muted-foreground">Off means unlimited.</p>
                    </div>
                    <Switch checked={data.trackInventory} onCheckedChange={(v) => set('trackInventory', v)} />
                  </div>
                  {data.trackInventory && (
                    <div className="max-w-[200px] space-y-1.5">
                      <Label>Stock on hand</Label>
                      <Input
                        type="number"
                        min={0}
                        value={data.stock}
                        onChange={(e) => set('stock', e.target.value)}
                        placeholder="0"
                      />
                      <FieldError message={errors.stock} />
                    </div>
                  )}
                  <div className="flex items-center justify-between gap-3 border-t border-border/60 pt-3">
                    <div>
                      <p className="text-sm font-medium text-foreground">Ask for delivery address</p>
                      <p className="text-xs text-muted-foreground">At checkout.</p>
                    </div>
                    <Switch checked={data.requiresDeliveryAddress} onCheckedChange={(v) => set('requiresDeliveryAddress', v)} />
                  </div>
                </div>
              )}

              {card === 'manual' && (
                <div className="space-y-3 rounded-xl bg-muted/40 p-4">
                  <div className="space-y-1.5">
                    <Label>Fulfillment instructions</Label>
                    <Textarea
                      value={data.fulfillmentInstructions}
                      onChange={(e) => set('fulfillmentInstructions', e.target.value)}
                      rows={2}
                      placeholder="How do you and the customer arrange handover?"
                    />
                  </div>
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="text-sm font-medium text-foreground">Ask for delivery address</p>
                    </div>
                    <Switch checked={data.requiresDeliveryAddress} onCheckedChange={(v) => set('requiresDeliveryAddress', v)} />
                  </div>
                </div>
              )}

              {(card === 'download' || card === 'link') && (
                <div className="space-y-3 rounded-xl bg-muted/40 p-4">
                  {card === 'link' && (
                    <div className="grid gap-3 sm:grid-cols-2">
                      <div className="space-y-1.5">
                        <Label>Access link</Label>
                        <Input
                          value={data.accessUrl}
                          onChange={(e) => set('accessUrl', e.target.value)}
                          placeholder="https://…"
                        />
                      </div>
                      <div className="space-y-1.5">
                        <Label>Instructions <span className="font-normal text-muted-foreground">(sent after payment)</span></Label>
                        <Input
                          value={data.fulfillmentInstructions}
                          onChange={(e) => set('fulfillmentInstructions', e.target.value)}
                          placeholder="How to use the link"
                        />
                      </div>
                    </div>
                  )}
                  {card === 'download' && (
                    <div className="space-y-1.5">
                      <Label>Digital file</Label>
                      {mode === 'edit' && editExtras?.digitalFileName && !digitalFile && (
                        <p className="text-xs text-muted-foreground">Current: {editExtras.digitalFileName} (private, signed link after payment)</p>
                      )}
                      {digitalFile && <p className="text-xs text-muted-foreground">Selected: {digitalFile.name}</p>}
                      <Input
                        type="file"
                        accept=".pdf,.epub,.txt,.csv,.zip,.doc,.docx"
                        disabled={isSubmitting}
                        onChange={(e) => setDigitalFile(e.target.files?.[0] ?? null)}
                        className="h-9 text-xs"
                      />
                      {mode === 'edit' && editExtras?.digitalFileName && !digitalFile && (
                        <button
                          type="button"
                          className="text-xs text-destructive underline"
                          disabled={editExtras.clearingFile || isSubmitting}
                          onClick={editExtras.onClearDigitalFile}
                        >
                          {editExtras.clearingFile ? 'Removing…' : 'Remove current file'}
                        </button>
                      )}
                      <div className="space-y-1.5 pt-1">
                        <Label>Instructions <span className="font-normal text-muted-foreground">(sent after payment)</span></Label>
                        <Input
                          value={data.fulfillmentInstructions}
                          onChange={(e) => set('fulfillmentInstructions', e.target.value)}
                          placeholder="Anything the buyer should know"
                        />
                      </div>
                    </div>
                  )}
                  <div className="rounded-lg border border-border">
                    <button
                      type="button"
                      onClick={() => setDigitalAdvancedOpen((v) => !v)}
                      className="flex w-full items-center justify-between px-3 py-2.5 text-left text-[13px] font-medium text-foreground"
                    >
                      Advanced digital options
                      <ChevronDown className={cn('h-4 w-4 text-muted-foreground transition-transform', digitalAdvancedOpen && 'rotate-180')} />
                    </button>
                    {digitalAdvancedOpen && (
                      <div className="grid gap-3 border-t border-border/60 p-3 sm:grid-cols-2">
                        {card === 'download' && (
                          <div className="space-y-1.5">
                            <Label>Download limit</Label>
                            <Input
                              type="number"
                              min={1}
                              value={data.maxDownloads}
                              onChange={(e) => set('maxDownloads', e.target.value)}
                              placeholder="Unlimited"
                            />
                            <FieldError message={errors.maxDownloads} />
                          </div>
                        )}
                        <div className="space-y-1.5">
                          <Label>Link expires (days)</Label>
                          <Input
                            type="number"
                            min={1}
                            value={data.accessExpiresDays}
                            onChange={(e) => set('accessExpiresDays', e.target.value)}
                            placeholder="Never"
                          />
                        </div>
                        <div className="space-y-1.5">
                          <Label>License keys</Label>
                          <Select value={data.licenseKeyMode} onValueChange={(v) => set('licenseKeyMode', v as ProductFormFields['licenseKeyMode'])}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                              <SelectItem value="none">None</SelectItem>
                              <SelectItem value="auto">Auto-generate</SelectItem>
                              <SelectItem value="pool">From key pool</SelectItem>
                            </SelectContent>
                          </Select>
                        </div>
                        <div className="space-y-1.5">
                          <Label>Key prefix</Label>
                          <Input
                            value={data.licenseKeyPrefix}
                            onChange={(e) => set('licenseKeyPrefix', e.target.value)}
                            placeholder="e.g. COURSE"
                          />
                        </div>
                        {data.licenseKeyMode === 'pool' && (
                          <div className="space-y-1.5 sm:col-span-2">
                            <Label>Import keys (one per line)</Label>
                            {(editExtras?.licenseKeysAvailable ?? 0) === 0 && !data.licenseKeys.trim() && mode === 'edit' && (
                              <p className="text-xs text-amber-700">Pool is empty — add keys before selling, or checkout is blocked.</p>
                            )}
                            <Textarea
                              value={data.licenseKeys}
                              onChange={(e) => set('licenseKeys', e.target.value)}
                              rows={3}
                              placeholder={'KEY-001\nKEY-002'}
                              className="font-mono text-xs"
                            />
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                </div>
              )}

              {card === 'appointment' && (
                <div className="space-y-3 rounded-xl bg-muted/40 p-4">
                  <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1.5">
                      <Label>Booking link</Label>
                      <Input
                        value={data.serviceBookingUrl}
                        onChange={(e) => set('serviceBookingUrl', e.target.value)}
                        placeholder="https://calendly.com/…"
                      />
                    </div>
                    <div className="space-y-1.5">
                      <Label>Access link <span className="font-normal text-muted-foreground">(optional)</span></Label>
                      <Input
                        value={data.accessUrl}
                        onChange={(e) => set('accessUrl', e.target.value)}
                        placeholder="Meeting room, portal…"
                      />
                    </div>
                  </div>
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="text-sm font-medium text-foreground">Let customers pick a time</p>
                      <p className="text-xs text-muted-foreground">Uses your booking page defaults.</p>
                    </div>
                    <Switch checked={data.bookable} onCheckedChange={(v) => set('bookable', v)} />
                  </div>
                  {data.bookable && (
                    <div className="max-w-[220px] space-y-1.5">
                      <Label>Session length (min)</Label>
                      <Input
                        type="number"
                        min={5}
                        max={480}
                        value={data.bookingDurationMinutes}
                        onChange={(e) => set('bookingDurationMinutes', e.target.value)}
                        placeholder="Default"
                      />
                      <FieldError message={errors.bookingDurationMinutes} />
                    </div>
                  )}
                  <div className="space-y-1.5">
                    <Label>What should they know? <span className="font-normal text-muted-foreground">(sent after booking)</span></Label>
                    <Textarea
                      value={data.fulfillmentInstructions}
                      onChange={(e) => set('fulfillmentInstructions', e.target.value)}
                      rows={2}
                      placeholder="Preparation, location, cancellation policy…"
                    />
                  </div>
                </div>
              )}
            </div>
          )}

          {/* STEP 3 — FINISH */}
          {step === 3 && (
            <div className="space-y-4">
              <div className="flex items-center gap-3 rounded-xl bg-muted/40 p-3.5">
                {imagePreview ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={imagePreview} alt="" className="h-12 w-12 rounded-lg object-cover" />
                ) : (
                  <span className="flex h-12 w-12 items-center justify-center rounded-lg bg-muted">
                    <Package className="h-5 w-5 text-muted-foreground" />
                  </span>
                )}
                <div className="min-w-0">
                  <p className="truncate text-sm font-semibold text-foreground">{data.name || 'Untitled product'}</p>
                  <p className="text-xs text-muted-foreground">
                    {data.category || 'No category'} · {CARDS.find((c) => c.id === card)?.title}
                    {data.price !== '' ? ` · ${currencyCode ? `${currencyCode} ` : ''}${data.price}` : ''}
                  </p>
                </div>
              </div>

              <div className="space-y-1.5">
                <Label>Tax</Label>
                <Select value={data.taxRateId || 'none'} onValueChange={(v) => set('taxRateId', v)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">Company default</SelectItem>
                    {taxRates
                      .filter((r) => r.isActive)
                      .map((r) => (
                        <SelectItem key={r.id} value={r.id}>
                          {r.name}{r.code ? ` (${r.code})` : ''} — {r.rate}%
                        </SelectItem>
                      ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="rounded-lg border border-border">
                <button
                  type="button"
                  onClick={() => setSeoOpen((v) => !v)}
                  className="flex w-full items-center justify-between px-3 py-2.5 text-left text-[13px] font-medium text-foreground"
                >
                  Search & sharing <span className="font-normal text-muted-foreground">(optional)</span>
                  <ChevronDown className={cn('h-4 w-4 text-muted-foreground transition-transform', seoOpen && 'rotate-180')} />
                </button>
                {seoOpen && (
                  <div className="space-y-3 border-t border-border/60 p-3">
                    <div className="space-y-1.5">
                      <Label>Page address</Label>
                      <Input value={data.slug} onChange={(e) => set('slug', e.target.value)} placeholder="auto-from-name" className="font-mono text-sm" />
                    </div>
                    <div className="space-y-1.5">
                      <Label>Google title</Label>
                      <Input value={data.metaTitle} onChange={(e) => set('metaTitle', e.target.value)} placeholder="Blank = product name" />
                    </div>
                    <div className="space-y-1.5">
                      <Label>Google description</Label>
                      <Textarea value={data.metaDescription} onChange={(e) => set('metaDescription', e.target.value)} rows={2} placeholder="Blank = product description" />
                    </div>
                    <div className="rounded-lg border bg-muted/40 p-3">
                      <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Google preview</p>
                      <p className="mt-1 line-clamp-1 text-sm font-medium text-[#1a0dab] dark:text-[#8ab4f8]">
                        {data.metaTitle || data.name || 'Product title'}
                      </p>
                      <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                        {data.metaDescription || data.description || 'Your product description shows here.'}
                      </p>
                    </div>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>

        <div className="border-t px-5 py-4 sm:px-6">
          <div className="flex items-center justify-between gap-3">
            <div>
              {step > 1 ? (
                <Button type="button" variant="ghost" size="sm" disabled={isSubmitting} onClick={() => setStep(step - 1)}>
                  <ArrowLeft className="mr-1.5 h-3.5 w-3.5" /> Back
                </Button>
              ) : (
                <Button type="button" variant="ghost" size="sm" disabled={isSubmitting} onClick={() => onOpenChange(false)}>
                  <X className="mr-1.5 h-3.5 w-3.5" /> Cancel
                </Button>
              )}
            </div>
            <p className="hidden text-xs text-muted-foreground sm:block">
              {step === 1 && !step1Valid && 'Add a name, price and category to continue.'}
              {step === 1 && step1Valid && 'Looking good — pick delivery next.'}
              {step === 2 && 'Choose how the customer receives it.'}
              {step === 3 && 'Tax and search extras are optional.'}
            </p>
            <div>
              {step < 3 ? (
                <Button type="button" size="sm" disabled={isSubmitting || (step === 1 && !step1Valid)} onClick={goNext}>
                  Continue <ArrowRight className="ml-1.5 h-3.5 w-3.5" />
                </Button>
              ) : (
                <Button type="button" size="sm" disabled={isSubmitting} onClick={finish}>
                  {isSubmitting ? <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" /> : <Check className="mr-1.5 h-3.5 w-3.5" />}
                  {isSubmitting ? 'Saving…' : submitLabel}
                </Button>
              )}
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}
