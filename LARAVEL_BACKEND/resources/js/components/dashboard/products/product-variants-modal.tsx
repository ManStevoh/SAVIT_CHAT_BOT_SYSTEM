'use client'

import React, { useState, useRef, useEffect, useCallback } from 'react'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Spinner } from '@/components/ui/spinner'
import type { Product, ProductVariant } from '@/lib/mock-data'
import { resolveBackendMediaUrl } from '@/lib/api-client'
import {
  Layers,
  Plus,
  Trash2,
  Upload,
  Image as ImageIcon,
  Sparkles,
  MessageSquare,
  AlertCircle,
  CheckCircle2,
  Edit2,
  Check,
  X,
  Sliders,
  DollarSign,
  Package,
  Wand2,
} from 'lucide-react'

interface ProductVariantsModalProps {
  product: Product | null
  open: boolean
  onOpenChange: (open: boolean) => void
  formatCurrency: (amount: number) => string
  onAddVariant: (data: { label: string; price: number; stock: number; image?: File }) => Promise<boolean>
  onUpdateVariant: (variantId: string, data: { label?: string; price?: number; stock?: number; status?: 'active' | 'inactive' }) => Promise<boolean>
  onDeleteVariant: (variantId: string) => Promise<boolean>
  onUploadVariantImage: (variantId: string, file: File) => Promise<boolean>
}

function variantDisplayImage(variant: ProductVariant): string | null {
  const fromField = resolveBackendMediaUrl(variant.image ?? null)
  if (fromField) return fromField
  const imgs = variant.images ?? []
  const primary = imgs.find((i) => i.isPrimary) ?? imgs[0]
  return resolveBackendMediaUrl(primary?.url ?? null)
}

// Preset attributes for quick variant matrix builder
const PRESET_ATTRIBUTES = {
  sizes: ['S', 'M', 'L', 'XL', '2XL'],
  colors: ['Black', 'White', 'Blue', 'Red', 'Green', 'Grey', 'Navy'],
  storage: ['64GB', '128GB', '256GB', '512GB', '1TB'],
  packs: ['Single Item', 'Pack of 2', 'Pack of 3', 'Family Pack'],
}

export function ProductVariantsModal({
  product,
  open,
  onOpenChange,
  formatCurrency,
  onAddVariant,
  onUpdateVariant,
  onDeleteVariant,
  onUploadVariantImage,
}: ProductVariantsModalProps) {
  const [activeTab, setActiveTab] = useState<'catalog' | 'generator'>('catalog')
  
  // Single variant add state
  const [newLabel, setNewLabel] = useState('')
  const [newPrice, setNewPrice] = useState('')
  const [newStock, setNewStock] = useState('0')
  const [newImageFile, setNewImageFile] = useState<File | null>(null)
  const [isAdding, setIsAdding] = useState(false)
  const [singleAddError, setSingleAddError] = useState('')

  // In-line editing states
  const [editingVariantId, setEditingVariantId] = useState<string | null>(null)
  const [editForm, setEditForm] = useState<{ label: string; price: string; stock: string }>({ label: '', price: '', stock: '' })
  const [isSavingEdit, setIsSavingEdit] = useState(false)

  // Image drag & drop uploading states
  const [uploadingVariantId, setUploadingVariantId] = useState<string | null>(null)
  const [dragOverVariantId, setDragOverVariantId] = useState<string | null>(null)

  // Matrix Generator state
  const [selectedSizes, setSelectedSizes] = useState<string[]>([])
  const [selectedColors, setSelectedColors] = useState<string[]>([])
  const [selectedStorage, setSelectedStorage] = useState<string[]>([])
  const [customAttrCategory, setCustomAttrCategory] = useState('')
  const [customAttrValues, setCustomAttrValues] = useState('')
  const [genDefaultPrice, setGenDefaultPrice] = useState('')
  const [genDefaultStock, setGenDefaultStock] = useState('10')
  const [isGenerating, setIsGenerating] = useState(false)
  const [genSuccessMessage, setGenSuccessMessage] = useState('')

  const singleFileInputRef = useRef<HTMLInputElement>(null)

  // Pre-fill default price from product when opened
  useEffect(() => {
    if (product) {
      setGenDefaultPrice(product.price ? String(product.price) : '0')
      setNewPrice(product.price ? String(product.price) : '0')
    }
  }, [product?.id, product?.price])

  if (!product) return null

  const variants = product.variants ?? []

  // Single Add Submit
  const handleSingleAdd = async (e?: React.FormEvent) => {
    if (e) e.preventDefault()
    if (!newLabel.trim()) {
      setSingleAddError('Variant label is required')
      return
    }
    const priceNum = parseFloat(newPrice)
    if (Number.isNaN(priceNum) || priceNum < 0) {
      setSingleAddError('Enter a valid price')
      return
    }
    const stockNum = parseInt(newStock, 10) || 0

    setSingleAddError('')
    setIsAdding(true)
    try {
      const ok = await onAddVariant({
        label: newLabel.trim(),
        price: priceNum,
        stock: stockNum,
        image: newImageFile ?? undefined,
      })
      if (ok) {
        setNewLabel('')
        setNewPrice(product.price ? String(product.price) : '0')
        setNewStock('0')
        setNewImageFile(null)
      }
    } finally {
      setIsAdding(false)
    }
  }

  // Handle Edit Start
  const startEditVariant = (variant: ProductVariant) => {
    setEditingVariantId(variant.id)
    setEditForm({
      label: variant.label,
      price: String(variant.price),
      stock: String(variant.stock),
    })
  }

  // Save Edit
  const saveEditVariant = async (variantId: string) => {
    const priceNum = parseFloat(editForm.price)
    const stockNum = parseInt(editForm.stock, 10) || 0
    if (!editForm.label.trim() || Number.isNaN(priceNum) || priceNum < 0) return

    setIsSavingEdit(true)
    try {
      await onUpdateVariant(variantId, {
        label: editForm.label.trim(),
        price: priceNum,
        stock: stockNum,
      })
      setEditingVariantId(null)
    } finally {
      setIsSavingEdit(false)
    }
  }

  // Dropzone file handling
  const handleDropFile = async (variantId: string, file: File) => {
    setUploadingVariantId(variantId)
    setDragOverVariantId(null)
    try {
      await onUploadVariantImage(variantId, file)
    } finally {
      setUploadingVariantId(null)
    }
  }

  // Toggle attribute chip selection
  const toggleChip = (list: string[], setList: (v: string[]) => void, item: string) => {
    if (list.includes(item)) {
      setList(list.filter((x) => x !== item))
    } else {
      setList([...list, item])
    }
  }

  // Calculate generated combinations
  const buildCombinations = (): string[] => {
    const groups: string[][] = []
    if (selectedSizes.length > 0) groups.push(selectedSizes)
    if (selectedColors.length > 0) groups.push(selectedColors)
    if (selectedStorage.length > 0) groups.push(selectedStorage)

    if (customAttrCategory.trim() && customAttrValues.trim()) {
      const parsed = customAttrValues.split(',').map((s) => s.trim()).filter(Boolean)
      if (parsed.length > 0) groups.push(parsed)
    }

    if (groups.length === 0) return []

    // Cartesion product
    const result: string[][] = groups.reduce(
      (acc, curr) => acc.flatMap((d) => curr.map((e) => [...d, e])),
      [[]] as string[][]
    )

    return result.map((combo) => combo.join(' / '))
  }

  const generatedList = buildCombinations()

  // Execute Matrix Generation
  const handleRunGenerator = async () => {
    if (generatedList.length === 0) return
    const priceNum = parseFloat(genDefaultPrice) || 0
    const stockNum = parseInt(genDefaultStock, 10) || 0

    setIsGenerating(true)
    setGenSuccessMessage('')
    try {
      let createdCount = 0
      for (const label of generatedList) {
        // Skip if variant already exists with exact same label
        if (variants.some((v) => v.label.toLowerCase() === label.toLowerCase())) {
          continue
        }
        const ok = await onAddVariant({
          label,
          price: priceNum,
          stock: stockNum,
        })
        if (ok) createdCount++
      }
      setGenSuccessMessage(`Successfully generated ${createdCount} new option(s)!`)
      // Reset selections
      setSelectedSizes([])
      setSelectedColors([])
      setSelectedStorage([])
      setCustomAttrCategory('')
      setCustomAttrValues('')
      setTimeout(() => {
        setActiveTab('catalog')
        setGenSuccessMessage('')
      }, 1200)
    } finally {
      setIsGenerating(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex max-h-[92vh] w-full max-w-4xl flex-col gap-0 overflow-hidden bg-card border-border/60 p-0 shadow-2xl sm:rounded-xl">
        {/* Header Section */}
        <DialogHeader className="shrink-0 border-b border-border/50 bg-muted/30 px-6 py-5">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary border border-primary/20">
                <Layers className="h-6 w-6" />
              </div>
              <div>
                <DialogTitle className="text-xl font-bold text-foreground flex items-center gap-2">
                  <span>Product Options & Variants</span>
                  <Badge variant="secondary" className="text-xs font-normal">
                    {variants.length} Option{variants.length !== 1 ? 's' : ''}
                  </Badge>
                </DialogTitle>
                <DialogDescription className="text-xs text-muted-foreground mt-0.5">
                  Manage variants for <strong className="text-foreground">{product.name}</strong> (Base price: {formatCurrency(product.price)})
                </DialogDescription>
              </div>
            </div>

            {/* Quick Status Stats */}
            <div className="flex items-center gap-3 text-xs">
              <div className="flex items-center gap-1.5 rounded-md bg-background px-3 py-1.5 border border-border/60">
                <Package className="h-4 w-4 text-muted-foreground" />
                <span className="text-muted-foreground">Total Stock:</span>
                <span className="font-semibold text-foreground">
                  {variants.length > 0 ? variants.reduce((acc, v) => acc + v.stock, 0) : product.stock}
                </span>
              </div>
            </div>
          </div>

          {/* Interactive WhatsApp Conversational Sales Preview Banner */}
          <div className="mt-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 p-3 text-xs flex items-start gap-3">
            <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-600 dark:text-emerald-400">
              <MessageSquare className="h-4 w-4" />
            </div>
            <div className="flex-1 space-y-1">
              <div className="flex items-center justify-between font-medium text-emerald-700 dark:text-emerald-300">
                <span>WhatsApp AI Commerce Preview</span>
                <span className="text-[10px] uppercase tracking-wider opacity-80">Live WhatsApp Flow</span>
              </div>
              <p className="text-emerald-600/90 dark:text-emerald-400/90 leading-relaxed">
                Customers texting your WhatsApp bot for <strong>"{product.name}"</strong> will see:
              </p>
              <div className="rounded bg-background/80 p-2 font-mono text-[11px] text-foreground border border-emerald-500/20 mt-1">
                {variants.length > 0 ? (
                  <>
                    💬 <em>"Which option would you like for <strong>{product.name}</strong>? Reply with a number:"</em>
                    <div className="mt-1 space-y-0.5 text-muted-foreground">
                      {variants.slice(0, 3).map((v, i) => (
                        <div key={v.id}>
                          [{i + 1}] {v.label} — {formatCurrency(v.price)} ({v.stock > 0 ? 'In Stock' : 'Out of Stock'})
                        </div>
                      ))}
                      {variants.length > 3 && (
                        <div className="text-[10px] italic">...and {variants.length - 3} more options</div>
                      )}
                    </div>
                  </>
                ) : (
                  <span className="text-muted-foreground italic">
                    No options created yet. Customers will purchase the base product directly at {formatCurrency(product.price)}.
                  </span>
                )}
              </div>
            </div>
          </div>
        </DialogHeader>

        {/* Tab Navigation & Content Area */}
        <Tabs
          value={activeTab}
          onValueChange={(val) => setActiveTab(val as 'catalog' | 'generator')}
          className="flex min-h-0 flex-1 flex-col overflow-hidden"
        >
          <div className="shrink-0 border-b border-border/50 bg-background px-6 pt-2">
            <TabsList className="bg-muted/50 p-1">
              <TabsTrigger value="catalog" className="flex items-center gap-2 text-xs font-medium">
                <Sliders className="h-3.5 w-3.5" />
                Manage Options ({variants.length})
              </TabsTrigger>
              <TabsTrigger value="generator" className="flex items-center gap-2 text-xs font-medium">
                <Wand2 className="h-3.5 w-3.5 text-primary" />
                Smart Matrix Generator
              </TabsTrigger>
            </TabsList>
          </div>

          <div className="min-h-0 flex-1 overflow-y-auto p-6">
            {/* TAB 1: Catalog & In-Line Editor */}
            <TabsContent value="catalog" className="m-0 space-y-6">
              {/* Existing Variants List */}
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-semibold text-foreground flex items-center gap-2">
                    Active Product Options
                  </h3>
                  <span className="text-xs text-muted-foreground">
                    Tip: Double-click or edit values directly below.
                  </span>
                </div>

                {variants.length === 0 ? (
                  <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border/80 p-8 text-center bg-muted/10">
                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary mb-3">
                      <Layers className="h-6 w-6" />
                    </div>
                    <p className="text-sm font-medium text-foreground">No options added yet</p>
                    <p className="text-xs text-muted-foreground max-w-sm mt-1">
                      Add individual options using the form below or switch to the <strong>Smart Matrix Generator</strong> tab to auto-generate multiple combinations.
                    </p>
                  </div>
                ) : (
                  <div className="space-y-2.5">
                    {variants.map((v) => {
                      const thumb = variantDisplayImage(v)
                      const isEditing = editingVariantId === v.id
                      const isUploading = uploadingVariantId === v.id
                      const isDragOver = dragOverVariantId === v.id

                      return (
                        <div
                          key={v.id}
                          className={`group relative flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border p-3.5 transition-all ${
                            isDragOver
                              ? 'border-primary bg-primary/5 ring-2 ring-primary/20'
                              : 'border-border/60 bg-card hover:border-border hover:shadow-sm'
                          }`}
                          onDragOver={(e) => {
                            e.preventDefault()
                            setDragOverVariantId(v.id)
                          }}
                          onDragLeave={() => setDragOverVariantId(null)}
                          onDrop={(e) => {
                            e.preventDefault()
                            const file = e.dataTransfer.files?.[0]
                            if (file) handleDropFile(v.id, file)
                          }}
                        >
                          {/* Image & Main Info */}
                          <div className="flex items-center gap-3.5 flex-1 min-w-0">
                            {/* Drag-and-Drop Image Dropzone Thumbnail */}
                            <label
                              className={`group/img relative flex h-14 w-14 shrink-0 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-lg border border-border/60 bg-muted/40 transition-all hover:border-primary/50 hover:bg-muted ${
                                isUploading ? 'opacity-50' : ''
                              }`}
                              title="Click or drag an image here to upload"
                            >
                              {thumb ? (
                                <img src={thumb} alt={v.label} className="h-full w-full object-cover" />
                              ) : (
                                <div className="flex flex-col items-center text-muted-foreground">
                                  <ImageIcon className="h-5 w-5" />
                                  <span className="text-[9px] font-medium mt-0.5">Upload</span>
                                </div>
                              )}
                              <div className="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity group-hover/img:opacity-100">
                                <Upload className="h-4 w-4 text-white" />
                              </div>
                              {isUploading && (
                                <div className="absolute inset-0 flex items-center justify-center bg-background/80">
                                  <Spinner className="h-4 w-4 text-primary" />
                                </div>
                              )}
                              <input
                                type="file"
                                accept="image/*"
                                className="hidden"
                                disabled={isUploading}
                                onChange={(e) => {
                                  const file = e.target.files?.[0]
                                  if (file) handleDropFile(v.id, file)
                                  e.target.value = ''
                                }}
                              />
                            </label>

                            {/* Label & Price Details (Editing vs Normal) */}
                            <div className="flex-1 min-w-0">
                              {isEditing ? (
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                  <Input
                                    size={1}
                                    value={editForm.label}
                                    onChange={(e) => setEditForm((prev) => ({ ...prev, label: e.target.value }))}
                                    placeholder="Option label"
                                    className="h-8 text-xs"
                                  />
                                  <Input
                                    type="number"
                                    value={editForm.price}
                                    onChange={(e) => setEditForm((prev) => ({ ...prev, price: e.target.value }))}
                                    placeholder="Price"
                                    className="h-8 text-xs"
                                  />
                                  <Input
                                    type="number"
                                    value={editForm.stock}
                                    onChange={(e) => setEditForm((prev) => ({ ...prev, stock: e.target.value }))}
                                    placeholder="Stock"
                                    className="h-8 text-xs"
                                  />
                                </div>
                              ) : (
                                <div>
                                  <div className="flex items-center gap-2">
                                    <span className="font-semibold text-foreground text-sm truncate">
                                      {v.label}
                                    </span>
                                    <Badge
                                      variant={v.stock > 0 ? 'outline' : 'destructive'}
                                      className={`text-[10px] px-1.5 py-0 font-normal ${
                                        v.stock > 10
                                          ? 'border-emerald-500/40 text-emerald-600 bg-emerald-500/10 dark:text-emerald-400'
                                          : v.stock > 0
                                          ? 'border-amber-500/40 text-amber-600 bg-amber-500/10 dark:text-amber-400'
                                          : ''
                                      }`}
                                    >
                                      {v.stock > 0 ? `Stock: ${v.stock}` : 'Out of Stock'}
                                    </Badge>
                                  </div>
                                  <div className="flex items-center gap-2 mt-0.5 text-xs text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                      {formatCurrency(v.price)}
                                    </span>
                                    <span>·</span>
                                    <span className="capitalize">{v.status ?? 'active'}</span>
                                  </div>
                                </div>
                              )}
                            </div>
                          </div>

                          {/* Actions */}
                          <div className="flex items-center gap-2 shrink-0 self-end sm:self-center">
                            {isEditing ? (
                              <>
                                <Button
                                  size="sm"
                                  variant="default"
                                  className="h-8 px-2.5 text-xs gap-1"
                                  disabled={isSavingEdit}
                                  onClick={() => saveEditVariant(v.id)}
                                >
                                  {isSavingEdit ? <Spinner className="h-3.5 w-3.5" /> : <Check className="h-3.5 w-3.5" />}
                                  Save
                                </Button>
                                <Button
                                  size="sm"
                                  variant="ghost"
                                  className="h-8 px-2 text-xs"
                                  onClick={() => setEditingVariantId(null)}
                                >
                                  <X className="h-3.5 w-3.5" />
                                </Button>
                              </>
                            ) : (
                              <>
                                <Button
                                  type="button"
                                  variant="outline"
                                  size="sm"
                                  className="h-8 text-xs gap-1 border-border/60"
                                  onClick={() => startEditVariant(v)}
                                >
                                  <Edit2 className="h-3.5 w-3.5 text-muted-foreground" />
                                  Edit
                                </Button>
                                <Button
                                  type="button"
                                  variant="ghost"
                                  size="sm"
                                  className="h-8 w-8 p-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                  onClick={() => onDeleteVariant(v.id)}
                                  title="Delete option"
                                >
                                  <Trash2 className="h-4 w-4" />
                                </Button>
                              </>
                            )}
                          </div>
                        </div>
                      )
                    })}
                  </div>
                )}
              </div>

              {/* Add Single Option Card Form */}
              <div className="rounded-xl border border-border/70 bg-muted/20 p-4 space-y-4">
                <div className="flex items-center justify-between">
                  <h4 className="text-xs font-semibold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                    <Plus className="h-3.5 w-3.5 text-primary" />
                    Add Single Option
                  </h4>
                  <span className="text-[11px] text-muted-foreground">Manual option entry</span>
                </div>

                {singleAddError && (
                  <div className="rounded-md bg-destructive/15 p-2.5 text-xs text-destructive font-medium border border-destructive/20">
                    {singleAddError}
                  </div>
                )}

                <form onSubmit={handleSingleAdd} className="space-y-3">
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div className="space-y-1 md:col-span-1">
                      <Label className="text-xs text-foreground">Option Label *</Label>
                      <Input
                        value={newLabel}
                        onChange={(e) => setNewLabel(e.target.value)}
                        placeholder="e.g. Blue / Large"
                        className="h-9 text-xs bg-background"
                      />
                    </div>
                    <div className="space-y-1">
                      <Label className="text-xs text-foreground">Price *</Label>
                      <Input
                        type="number"
                        step="0.01"
                        value={newPrice}
                        onChange={(e) => setNewPrice(e.target.value)}
                        placeholder="0.00"
                        className="h-9 text-xs bg-background"
                      />
                    </div>
                    <div className="space-y-1">
                      <Label className="text-xs text-foreground">Stock Quantity</Label>
                      <Input
                        type="number"
                        value={newStock}
                        onChange={(e) => setNewStock(e.target.value)}
                        placeholder="0"
                        className="h-9 text-xs bg-background"
                      />
                    </div>
                  </div>

                  {/* Optional File Picker & Add Action */}
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                    <div className="flex items-center gap-2">
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-8 text-xs gap-1.5 bg-background border-border/60"
                        onClick={() => singleFileInputRef.current?.click()}
                      >
                        <ImageIcon className="h-3.5 w-3.5 text-muted-foreground" />
                        {newImageFile ? newImageFile.name : 'Attach Image (Optional)'}
                      </Button>
                      {newImageFile && (
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          className="h-8 w-8 p-0 text-muted-foreground"
                          onClick={() => setNewImageFile(null)}
                        >
                          <X className="h-3.5 w-3.5" />
                        </Button>
                      )}
                      <input
                        type="file"
                        accept="image/*"
                        ref={singleFileInputRef}
                        className="hidden"
                        onChange={(e) => setNewImageFile(e.target.files?.[0] ?? null)}
                      />
                    </div>

                    <Button
                      type="submit"
                      disabled={isAdding || !newLabel.trim() || newPrice === ''}
                      size="sm"
                      className="h-9 text-xs px-4 gap-1.5"
                    >
                      {isAdding ? <Spinner className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
                      Add Option
                    </Button>
                  </div>
                </form>
              </div>
            </TabsContent>

            {/* TAB 2: Smart Matrix Generator */}
            <TabsContent value="generator" className="m-0 space-y-6">
              <div className="rounded-xl border border-primary/20 bg-primary/5 p-4 text-xs space-y-1">
                <div className="flex items-center gap-2 font-semibold text-primary">
                  <Sparkles className="h-4 w-4" />
                  <span>Smart Variant Combination Engine</span>
                </div>
                <p className="text-muted-foreground leading-relaxed">
                  Select common attribute tags below (like Sizes & Colors) to instantly generate all product combinations at once.
                </p>
              </div>

              {genSuccessMessage && (
                <div className="flex items-center gap-2 rounded-lg bg-emerald-500/15 border border-emerald-500/30 p-3 text-xs text-emerald-700 dark:text-emerald-300 font-medium">
                  <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                  <span>{genSuccessMessage}</span>
                </div>
              )}

              {/* Attribute Pickers */}
              <div className="space-y-4">
                {/* Sizes */}
                <div className="space-y-2">
                  <Label className="text-xs font-semibold text-foreground">Sizes</Label>
                  <div className="flex flex-wrap gap-1.5">
                    {PRESET_ATTRIBUTES.sizes.map((s) => {
                      const active = selectedSizes.includes(s)
                      return (
                        <button
                          key={s}
                          type="button"
                          onClick={() => toggleChip(selectedSizes, setSelectedSizes, s)}
                          className={`rounded-lg px-3 py-1.5 text-xs font-medium transition-all ${
                            active
                              ? 'bg-primary text-primary-foreground shadow-sm ring-2 ring-primary/30'
                              : 'bg-muted/70 text-foreground hover:bg-muted border border-border/50'
                          }`}
                        >
                          {s}
                        </button>
                      )
                    })}
                  </div>
                </div>

                {/* Colors */}
                <div className="space-y-2">
                  <Label className="text-xs font-semibold text-foreground">Colors</Label>
                  <div className="flex flex-wrap gap-1.5">
                    {PRESET_ATTRIBUTES.colors.map((c) => {
                      const active = selectedColors.includes(c)
                      return (
                        <button
                          key={c}
                          type="button"
                          onClick={() => toggleChip(selectedColors, setSelectedColors, c)}
                          className={`rounded-lg px-3 py-1.5 text-xs font-medium transition-all ${
                            active
                              ? 'bg-primary text-primary-foreground shadow-sm ring-2 ring-primary/30'
                              : 'bg-muted/70 text-foreground hover:bg-muted border border-border/50'
                          }`}
                        >
                          {c}
                        </button>
                      )
                    })}
                  </div>
                </div>

                {/* Storage / Capacity */}
                <div className="space-y-2">
                  <Label className="text-xs font-semibold text-foreground">Storage / Capacity</Label>
                  <div className="flex flex-wrap gap-1.5">
                    {PRESET_ATTRIBUTES.storage.map((st) => {
                      const active = selectedStorage.includes(st)
                      return (
                        <button
                          key={st}
                          type="button"
                          onClick={() => toggleChip(selectedStorage, setSelectedStorage, st)}
                          className={`rounded-lg px-3 py-1.5 text-xs font-medium transition-all ${
                            active
                              ? 'bg-primary text-primary-foreground shadow-sm ring-2 ring-primary/30'
                              : 'bg-muted/70 text-foreground hover:bg-muted border border-border/50'
                          }`}
                        >
                          {st}
                        </button>
                      )
                    })}
                  </div>
                </div>

                {/* Custom Attribute Input */}
                <div className="rounded-lg border border-border/60 bg-muted/20 p-3 space-y-2">
                  <Label className="text-xs font-semibold text-foreground">Custom Attribute (Optional)</Label>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <Input
                      value={customAttrCategory}
                      onChange={(e) => setCustomAttrCategory(e.target.value)}
                      placeholder="Category e.g. Material"
                      className="h-8 text-xs bg-background"
                    />
                    <Input
                      value={customAttrValues}
                      onChange={(e) => setCustomAttrValues(e.target.value)}
                      placeholder="Values separated by comma e.g. Leather, Canvas, Cotton"
                      className="h-8 text-xs bg-background"
                    />
                  </div>
                </div>
              </div>

              {/* Default Price & Stock for Generator */}
              <div className="grid grid-cols-2 gap-4 border-t border-border/50 pt-4">
                <div className="space-y-1">
                  <Label className="text-xs text-foreground">Default Price per generated variant</Label>
                  <Input
                    type="number"
                    step="0.01"
                    value={genDefaultPrice}
                    onChange={(e) => setGenDefaultPrice(e.target.value)}
                    placeholder="0.00"
                    className="h-9 text-xs"
                  />
                </div>
                <div className="space-y-1">
                  <Label className="text-xs text-foreground">Default Initial Stock</Label>
                  <Input
                    type="number"
                    value={genDefaultStock}
                    onChange={(e) => setGenDefaultStock(e.target.value)}
                    placeholder="10"
                    className="h-9 text-xs"
                  />
                </div>
              </div>

              {/* Matrix Preview Box */}
              {generatedList.length > 0 && (
                <div className="rounded-xl border border-border/70 bg-card p-4 space-y-3">
                  <div className="flex items-center justify-between text-xs">
                    <span className="font-semibold text-foreground">
                      Preview Combinations to be Created ({generatedList.length})
                    </span>
                    <span className="text-muted-foreground">
                      Default price: {formatCurrency(parseFloat(genDefaultPrice) || 0)}
                    </span>
                  </div>
                  <div className="max-h-40 overflow-y-auto rounded-lg border border-border/50 bg-muted/20 p-2.5 space-y-1">
                    {generatedList.map((combo, idx) => (
                      <div key={idx} className="flex items-center justify-between text-xs py-1 px-2 rounded bg-background">
                        <span className="font-medium text-foreground">{combo}</span>
                        <span className="text-muted-foreground">Stock: {genDefaultStock}</span>
                      </div>
                    ))}
                  </div>

                  <Button
                    type="button"
                    onClick={handleRunGenerator}
                    disabled={isGenerating}
                    className="w-full h-10 text-xs font-semibold gap-2 shadow-md"
                  >
                    {isGenerating ? <Spinner className="h-4 w-4" /> : <Wand2 className="h-4 w-4" />}
                    Generate & Add {generatedList.length} Option{generatedList.length !== 1 ? 's' : ''}
                  </Button>
                </div>
              )}
            </TabsContent>
          </div>
        </Tabs>
      </DialogContent>
    </Dialog>
  )
}
