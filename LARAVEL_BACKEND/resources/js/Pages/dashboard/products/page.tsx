'use client'

import { useState, useCallback, useRef, useEffect, useMemo } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { StatsCard, StatsGrid } from '@/components/shared/stats-card'
import { DataTable, type Column, type Filter } from '@/components/shared/data-table'
import { StatusBadge } from '@/components/shared/status-badge'
import { ConfirmModal } from '@/components/shared/modal'
import { ProductWizardModal } from '@/components/dashboard/products/ProductWizardModal'
import type { ProductFormFields, WizardFiles } from '@/components/dashboard/products/ProductWizardModal'
import { emptyProductFields, validateProductFields } from '@/components/dashboard/products/ProductWizardModal'
import { useProducts, useCompanySettings, useTaxRates, useSubscription } from '@/lib/api-hooks'
import { PlanLimitBar, UpgradePrompt } from '@/components/shared/upgrade-prompt'
import { STARTER_LIMITS, isStarterPlan, isAtLimit, isNearLimit } from '@/lib/use-plan'
import { formatCurrencyAmount, normalizeCurrencyCode, currencyDisplayFromSettings } from '@/lib/format-currency'
import {
  createProduct,
  updateProduct,
  deleteProduct,
  companyExportData,
  importProducts,
  createProductVariant,
  updateProductVariant,
  deleteProductVariant,
  uploadProductImage,
  uploadVariantImage,
} from '@/lib/api-actions'
import { ProductVariantsModal } from '@/components/dashboard/products/product-variants-modal'
import { downloadFile, resolveBackendMediaUrl } from '@/lib/api-client'
import type { Product, ProductVariant } from '@/lib/mock-data'
import {
  Plus,
  MoreVertical,
  Package,
  TrendingUp,
  AlertCircle,
  Edit,
  Trash2,
  BarChart3,
  Download,
  Upload,
  Loader2,
  Layers,
} from 'lucide-react'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
  TooltipProvider,
} from '@/components/ui/tooltip'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useSWRConfig } from 'swr'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'

const initialFormData: ProductFormFields = emptyProductFields

function variantDisplayImage(variant: ProductVariant): string | null {
  const fromField = resolveBackendMediaUrl(variant.image ?? null)
  if (fromField) return fromField
  const imgs = variant.images ?? []
  const primary = imgs.find((i) => i.isPrimary) ?? imgs[0]
  return resolveBackendMediaUrl(primary?.url ?? null)
}

function productPrimaryDisplayImage(product: Product): string | null {
  const r = (u: string | null | undefined) => resolveBackendMediaUrl(u ?? null)
  const direct = r(product.image)
  if (direct) return direct
  const imgs = product.images ?? []
  const primary = imgs.find((i) => i.isPrimary) ?? imgs[0]
  const fromGallery = r(primary?.url)
  if (fromGallery) return fromGallery
  for (const v of product.variants ?? []) {
    const vImg = variantDisplayImage(v)
    if (vImg) return vImg
  }
  return null
}

function ProductThumbImg({
  src,
  alt,
  className = 'h-full w-full rounded-lg object-cover',
}: {
  src: string
  alt: string
  className?: string
}) {
  const [failed, setFailed] = useState(false)
  if (failed) {
    return (
      <div className="flex h-full w-full items-center justify-center">
        <Package className="h-5 w-5 shrink-0 text-primary" />
      </div>
    )
  }
  return <img src={src} alt={alt} className={className} onError={() => setFailed(true)} />
}

export default function ProductsPage() {
  const router = useRouter()
  const { mutate } = useSWRConfig()
  const [searchQuery, setSearchQuery] = useState('')
  const [categoryFilter, setCategoryFilter] = useState('all')
  const [statusFilter, setStatusFilter] = useState('all')
  
  // Modal states
  const [isAddModalOpen, setIsAddModalOpen] = useState(false)
  const [isEditModalOpen, setIsEditModalOpen] = useState(false)
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false)
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null)
  const [formData, setFormData] = useState<ProductFormFields>(initialFormData)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [wizardKey, setWizardKey] = useState(0)
  const [exportOpen, setExportOpen] = useState(false)
  const [exportFormat, setExportFormat] = useState<'csv' | 'json'>('csv')
  const [exporting, setExporting] = useState(false)
  const [importing, setImporting] = useState(false)
  const [importResult, setImportResult] = useState<{ created: number; errors?: { row: number; errors: string[] }[] } | null>(null)
  const importInputRef = useRef<HTMLInputElement>(null)
  const [variantsSheetProduct, setVariantsSheetProduct] = useState<Product | null>(null)
  const [productExtraImageUploading, setProductExtraImageUploading] = useState(false)

  const { data: companySettings } = useCompanySettings()
  const { data: taxRates } = useTaxRates()
  const catalogCurrency = normalizeCurrencyCode(companySettings?.displayCurrency)
  const formatCurrency = (value: number) =>
    formatCurrencyAmount(value, catalogCurrency, currencyDisplayFromSettings(companySettings))

  // API: GET /api/company/products (useProducts)
  const { data: products, isLoading, error } = useProducts({
    category: categoryFilter,
    status: statusFilter,
    search: searchQuery,
  })

  useEffect(() => {
    if (!isEditModalOpen || !selectedProduct || !products) return
    const next = products.find((p) => p.id === selectedProduct.id)
    if (next) setSelectedProduct(next)
  }, [products, isEditModalOpen, selectedProduct?.id])

  useEffect(() => {
    if (!variantsSheetProduct || !products) return
    const next = products.find((p) => p.id === variantsSheetProduct.id)
    if (next) setVariantsSheetProduct(next)
  }, [products, variantsSheetProduct?.id])

  // Calculate stats from data
  const stats = {
    total: products?.length || 0,
    inStock: products?.filter((p) => p.stock > 10).length || 0,
    lowStock: products?.filter((p) => p.stock > 0 && p.stock <= 10).length || 0,
    outOfStock: products?.filter((p) => p.stock === 0).length || 0,
  }

  // Existing categories for the wizard's suggestions
  const existingCategories = useMemo(() => {
    const set = new Set<string>()
    for (const p of products ?? []) {
      if (p.category?.trim()) set.add(p.category.trim())
    }
    return [...set].sort()
  }, [products])

  // Starter plan catalog cap (KSh 0: 20 physical or digital products).
  const { data: subscription } = useSubscription()
  const starterCatalog = isStarterPlan(subscription?.plan)
  const productLimit = starterCatalog ? STARTER_LIMITS.products : null
  const catalogFull = isAtLimit(stats.total, productLimit)
  const catalogNear = isNearLimit(stats.total, productLimit)

  // Build the API payload from wizard data — shared by create + edit
  const buildPayload = (d: ProductFormFields, files: WizardFiles) => ({
    name: d.name,
    description: d.description,
    metaTitle: d.metaTitle || null,
    metaDescription: d.metaDescription || null,
    slug: d.slug || null,
    price: parseFloat(d.price),
    compareAtPrice: d.compareAtPrice.trim() === '' ? null : parseFloat(d.compareAtPrice),
    taxRateId: d.taxRateId === 'none' ? null : d.taxRateId,
    category: d.category,
    productType: d.productType,
    fulfillmentType: d.fulfillmentType,
    trackInventory: d.trackInventory,
    requiresDeliveryAddress: d.requiresDeliveryAddress,
    accessUrl: d.accessUrl,
    serviceBookingUrl: d.serviceBookingUrl,
    fulfillmentInstructions: d.fulfillmentInstructions,
    licenseKeyMode: d.licenseKeyMode,
    licenseKeyPrefix: d.licenseKeyPrefix,
    accessExpiresDays: d.accessExpiresDays ? parseInt(d.accessExpiresDays, 10) : null,
    maxDownloads: d.maxDownloads ? parseInt(d.maxDownloads, 10) : null,
    bookable: d.bookable,
    bookingDurationMinutes: d.bookingDurationMinutes ? parseInt(d.bookingDurationMinutes, 10) : null,
    licenseKeys: d.licenseKeys || undefined,
    stock: Number.isNaN(parseInt(d.stock, 10)) ? 0 : parseInt(d.stock, 10),
    image: files.image ?? undefined,
    digitalFile: files.digital ?? undefined,
  })

  // Handle create product — api-actions.createProduct → POST /api/company/products
  const handleCreateProduct = async (d: ProductFormFields, files: WizardFiles): Promise<{ ok: boolean; message?: string }> => {
    if (Object.keys(validateProductFields(d)).length > 0) {
      return { ok: false, message: 'Please complete the highlighted fields.' }
    }

    setIsSubmitting(true)
    try {
      const result = await createProduct(buildPayload(d, files))

      if (result.success) {
        // Revalidate products data
        mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
        setIsAddModalOpen(false)
        setFormData(initialFormData)
        return { ok: true }
      }
      return { ok: false, message: result.message || 'Failed to create product. Please check form inputs.' }
    } catch (error) {
      console.error('Failed to create product:', error)
      return { ok: false, message: 'An unexpected error occurred while creating product.' }
    } finally {
      setIsSubmitting(false)
    }
  }

  // Handle edit product — api-actions.updateProduct → PUT /api/company/products/:productId
  const handleEditProduct = async (d: ProductFormFields, files: WizardFiles): Promise<{ ok: boolean; message?: string }> => {
    if (!selectedProduct) return { ok: false }
    if (Object.keys(validateProductFields(d)).length > 0) {
      return { ok: false, message: 'Please complete the highlighted fields.' }
    }

    setIsSubmitting(true)
    try {
      const result = await updateProduct(selectedProduct.id, buildPayload(d, files))

      if (result.success) {
        await mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
        setIsEditModalOpen(false)
        setSelectedProduct(null)
        setFormData(initialFormData)
        return { ok: true }
      }
      return { ok: false, message: result.message || 'Failed to update product. Please check form inputs.' }
    } catch (error) {
      console.error('Failed to update product:', error)
      return { ok: false, message: 'An unexpected error occurred while updating product.' }
    } finally {
      setIsSubmitting(false)
    }
  }

  // Handle delete product — api-actions.deleteProduct → DELETE /api/company/products/:productId
  const handleDeleteProduct = useCallback(async () => {
    if (!selectedProduct) return

    setIsSubmitting(true)
    try {
      const result = await deleteProduct(selectedProduct.id)

      if (result.success) {
        mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
        setIsDeleteModalOpen(false)
        setSelectedProduct(null)
      }
    } catch (error) {
      console.error('Failed to delete product:', error)
    } finally {
      setIsSubmitting(false)
    }
  }, [selectedProduct, mutate, categoryFilter, statusFilter, searchQuery])

  // Open edit modal with product data
  const openEditModal = (product: Product) => {
    setSelectedProduct(product)
    setFormData({
      name: product.name,
      description: product.description,
      metaTitle: product.metaTitle ?? '',
      metaDescription: product.metaDescription ?? '',
      slug: product.slug ?? '',
      price: product.price.toString(),
      compareAtPrice: product.compareAtPrice != null ? String(product.compareAtPrice) : '',
      taxRateId: product.taxRateId ?? 'none',
      category: product.category,
      productType: product.productType ?? 'physical',
      fulfillmentType: product.fulfillmentType ?? 'shipping',
      trackInventory: product.trackInventory ?? true,
      requiresDeliveryAddress: product.requiresDeliveryAddress ?? ((product.productType ?? 'physical') === 'physical'),
      accessUrl: product.accessUrl ?? '',
      serviceBookingUrl: product.serviceBookingUrl ?? '',
      fulfillmentInstructions: product.fulfillmentInstructions ?? '',
      licenseKeyMode: product.licenseKeyMode ?? 'none',
      licenseKeyPrefix: product.licenseKeyPrefix ?? '',
      accessExpiresDays: product.accessExpiresDays != null ? String(product.accessExpiresDays) : '',
      maxDownloads: product.maxDownloads != null ? String(product.maxDownloads) : '',
      bookable: product.bookable ?? false,
      bookingDurationMinutes: product.bookingDurationMinutes != null ? String(product.bookingDurationMinutes) : '',
      licenseKeys: '',
      stock: product.stock.toString(),
    })
    setIsEditModalOpen(true)
  }

  // Remove the attached digital file (edit mode) — api-actions.updateProduct
  const handleClearDigitalFile = async () => {
    if (!selectedProduct) return
    setIsSubmitting(true)
    try {
      await updateProduct(selectedProduct.id, { clearDigitalFile: true })
      mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
      setSelectedProduct({ ...selectedProduct, digitalFileName: null, hasDigitalFile: false })
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleExportProducts = async () => {
    setExporting(true)
    try {
      const result = await companyExportData('products', exportFormat)
      if (result.success && result.downloadUrl && result.filename) {
        await downloadFile(result.downloadUrl, result.filename)
        setExportOpen(false)
      }
    } finally {
      setExporting(false)
    }
  }

  const handleExportFormatChange = (value: string) => {
    setExportFormat(value === 'json' ? 'json' : 'csv')
  }

  const handleAddVariantModal = useCallback(
    async (data: { label: string; price: number; stock: number; image?: File }) => {
      if (!variantsSheetProduct) return false
      const res = await createProductVariant(variantsSheetProduct.id, data)
      if (res.success) {
        mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
        if (res.variant) {
          setVariantsSheetProduct((prev) =>
            prev ? { ...prev, variants: [...(prev.variants ?? []), res.variant!] } : null
          )
        }
        return true
      }
      return false
    },
    [variantsSheetProduct, mutate, categoryFilter, statusFilter, searchQuery]
  )

  const handleUpdateVariantModal = useCallback(
    async (variantId: string, data: { label?: string; price?: number; stock?: number; status?: 'active' | 'inactive' }) => {
      const res = await updateProductVariant(variantId, data)
      if (res.success) {
        mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
        setVariantsSheetProduct((prev) =>
          prev
            ? {
                ...prev,
                variants: (prev.variants ?? []).map((v) => (v.id === variantId ? { ...v, ...data } : v)),
              }
            : null
        )
        return true
      }
      return false
    },
    [mutate, categoryFilter, statusFilter, searchQuery]
  )

  const handleDeleteVariantModal = useCallback(
    async (variantId: string) => {
      const res = await deleteProductVariant(variantId)
      if (res.success) {
        mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
        setVariantsSheetProduct((prev) =>
          prev ? { ...prev, variants: (prev.variants ?? []).filter((v) => v.id !== variantId) } : null
        )
        return true
      }
      return false
    },
    [mutate, categoryFilter, statusFilter, searchQuery]
  )

  const handleUploadVariantImageModal = useCallback(
    async (variantId: string, file: File) => {
      const res = await uploadVariantImage(variantId, { image: file, isPrimary: true })
      if (res.success) {
        mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
        return true
      }
      return false
    },
    [mutate, categoryFilter, statusFilter, searchQuery]
  )

  const handleUploadExtraProductImage = useCallback(
    async (file: File) => {
      if (!selectedProduct) return
      setProductExtraImageUploading(true)
      try {
        const res = await uploadProductImage(selectedProduct.id, { image: file })
        if (res.success) {
          mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
        }
      } finally {
        setProductExtraImageUploading(false)
      }
    },
    [selectedProduct, mutate, categoryFilter, statusFilter, searchQuery]
  )

  const handleImportProducts = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    setImportResult(null)
    setImporting(true)
    try {
      const result = await importProducts(file)
      if (result.success) {
        setImportResult({ created: result.created ?? 0, errors: result.errors })
        mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
      }
    } finally {
      setImporting(false)
    }
    e.target.value = ''
  }

  // Get product stock status
  const getStockStatus = (stock: number): string => {
    if (stock === 0) return 'inactive'
    if (stock <= 10) return 'warning'
    return 'active'
  }

  // Table columns definition
  const columns: Column<Product>[] = [
    {
      key: 'name',
      header: 'Product',
      cell: (product) => {
        const thumb = productPrimaryDisplayImage(product)
        return (
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
              {thumb ? (
                <ProductThumbImg src={thumb} alt={product.name} />
              ) : (
                <Package className="h-5 w-5 text-primary" />
              )}
            </div>
            <div>
              <span className="font-medium text-foreground">{product.name}</span>
              <p className="text-xs text-muted-foreground line-clamp-1">
                {product.description}
              </p>
              <p className="text-xs text-muted-foreground">
                {(product.productType ?? 'physical')} · {(product.fulfillmentType ?? 'shipping')}
              </p>
            </div>
          </div>
        )
      },
    },
    {
      key: 'category',
      header: 'Category',
      cell: (product) => (
        <span className="text-muted-foreground">{product.category}</span>
      ),
    },
    {
      key: 'price',
      header: 'Price',
      cell: (product) => (
        <div className="flex flex-col">
          <span className="font-medium text-foreground">
            {product.variants && product.variants.length > 0
              ? `From ${formatCurrency(Math.min(...product.variants.map((v) => v.price)))}`
              : formatCurrency(product.price)}
          </span>
          {product.variants && product.variants.length > 0 && (
            <span className="text-xs text-muted-foreground">{product.variants.length} option(s)</span>
          )}
        </div>
      ),
    },
    {
      key: 'stock',
      header: 'Stock',
      cell: (product) => (
        <div className="flex items-center gap-2">
          <span className="text-foreground">{product.stock}</span>
          {product.stock <= 10 && product.stock > 0 && (
            <AlertCircle className="h-4 w-4 text-yellow-500" />
          )}
        </div>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      cell: (product) => <StatusBadge status={product.status} />,
    },
    {
      key: 'actions',
      header: '',
      cell: (product) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon">
              <MoreVertical className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onClick={() => openEditModal(product)}>
              <Edit className="mr-2 h-4 w-4" />
              Edit Product
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => setVariantsSheetProduct(product)}>
              <Layers className="mr-2 h-4 w-4" />
              Options / variants
            </DropdownMenuItem>
            <DropdownMenuItem
              onClick={() =>
                router.push(
                  `/dashboard/analytics?tab=products&product=${encodeURIComponent(product.name)}`
                )
              }
            >
              <BarChart3 className="mr-2 h-4 w-4" />
              View Analytics
            </DropdownMenuItem>
            <DropdownMenuItem
              className="text-destructive"
              onClick={() => {
                setSelectedProduct(product)
                setIsDeleteModalOpen(true)
              }}
            >
              <Trash2 className="mr-2 h-4 w-4" />
              Delete
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ]

  // Filter options
  const filters: Filter[] = [
    {
      key: 'category',
      label: 'Category',
      options: [
        { value: 'all', label: 'All Categories' },
        { value: 'Phones', label: 'Phones' },
        { value: 'Laptops', label: 'Laptops' },
        { value: 'Tablets', label: 'Tablets' },
        { value: 'Accessories', label: 'Accessories' },
      ],
    },
    {
      key: 'status',
      label: 'Status',
      options: [
        { value: 'all', label: 'All Status' },
        { value: 'active', label: 'Active' },
        { value: 'inactive', label: 'Inactive' },
      ],
    },
  ]

  // Product form fields (shared between add and edit)
  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col gap-2">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-foreground">Products</h1>
            <p className="text-muted-foreground">Manage your product catalog</p>
          </div>
        <div className="flex flex-wrap items-center gap-2">
          <TooltipProvider>
            <Tooltip>
              <TooltipTrigger asChild>
                <Popover open={exportOpen} onOpenChange={setExportOpen}>
                  <PopoverTrigger asChild>
                    <Button variant="outline" size="sm">
                      <Download className="mr-2 h-4 w-4" />
                      Export
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent className="w-64" align="end">
                    <div className="space-y-3">
                      <p className="text-sm font-medium">Export products</p>
                      <Select value={exportFormat} onValueChange={handleExportFormatChange}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="csv">CSV (Excel)</SelectItem>
                          <SelectItem value="json">JSON</SelectItem>
                        </SelectContent>
                      </Select>
                      <Button size="sm" className="w-full" onClick={handleExportProducts} disabled={exporting}>
                        {exporting ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : <Download className="h-4 w-4 mr-2" />}
                        {exporting ? 'Exporting…' : 'Download'}
                      </Button>
                    </div>
                  </PopoverContent>
                </Popover>
              </TooltipTrigger>
              <TooltipContent side="bottom" className="max-w-xs">
                Download your product catalog as CSV (opens in Excel) or JSON.
              </TooltipContent>
            </Tooltip>
            <Tooltip>
              <TooltipTrigger asChild>
                <span>
                  <input
                    type="file"
                    accept=".csv,.txt"
                    className="hidden"
                    ref={importInputRef}
                    onChange={handleImportProducts}
                  />
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={importing}
                    onClick={() => importInputRef.current?.click()}
                  >
                    {importing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Upload className="mr-2 h-4 w-4" />}
                    {importing ? 'Importing…' : 'Import CSV'}
                  </Button>
                </span>
              </TooltipTrigger>
              <TooltipContent side="bottom" className="max-w-xs">
                Upload a CSV with columns: name, description, price, category, status. Optional: stock. Use the sample CSV as a template.
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>
          <Button variant="outline" size="sm" asChild>
            <a href="/sample-data/products_sample.csv" download="products_sample.csv">
              Sample CSV
            </a>
          </Button>
          <Button onClick={() => {
            setFormData(initialFormData)
            setSelectedProduct(null)
            setWizardKey(Date.now())
            setIsAddModalOpen(true)
          }} disabled={catalogFull} title={catalogFull ? "You've used all 20 Starter products — upgrade to Growth for 50" : undefined}>
            <Plus className="mr-2 h-4 w-4" />
            Add Product
          </Button>
        </div>
        </div>
        {starterCatalog && (catalogNear || catalogFull) && (
          <div className="max-w-xl space-y-3">
            <PlanLimitBar used={stats.total} limit={productLimit} label="Catalog" unit="products" />
            {catalogFull && (
              <UpgradePrompt
                title="You've filled your 20 Starter products"
                description="Growth raises your catalog to 50 products with the same storefront, bookings, and dine-in — plus WhatsApp selling."
                compact
              />
            )}
          </div>
        )}
        {importResult !== null && (
          <p className="text-sm text-muted-foreground">
            Imported {importResult.created} product(s).
            {importResult.errors?.length ? ` ${importResult.errors.length} row(s) had errors.` : ''}
          </p>
        )}
        </div>
      <StatsGrid columns={4}>
        <StatsCard
          title="Total Products"
          value={stats.total}
          icon={Package}
          isLoading={isLoading}
        />
        <StatsCard
          title="In Stock"
          value={stats.inStock}
          icon={TrendingUp}
          isLoading={isLoading}
        />
        <StatsCard
          title="Low Stock"
          value={stats.lowStock}
          icon={AlertCircle}
          isLoading={isLoading}
        />
        <StatsCard
          title="Out of Stock"
          value={stats.outOfStock}
          icon={AlertCircle}
          isLoading={isLoading}
        />
      </StatsGrid>

      {/* Products Table - API Ready */}
      <Card className="bg-card border-border/50">
        <CardHeader>
          <CardTitle className="text-base font-medium">All Products</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            data={products}
            columns={columns}
            isLoading={isLoading}
            error={error}
            searchPlaceholder="Search products..."
            onSearch={setSearchQuery}
            filters={filters}
            filterValues={{ category: categoryFilter, status: statusFilter }}
            onFilterChange={(key, value) => {
              if (key === 'category') setCategoryFilter(value)
              if (key === 'status') setStatusFilter(value)
            }}
            emptyMessage="No products found"
            emptyDescription="Add products to your catalog to get started"
          />
        </CardContent>
      </Card>

      {/* Add Product Wizard */}
      <ProductWizardModal
        open={isAddModalOpen}
        onOpenChange={setIsAddModalOpen}
        mode="add"
        initial={formData}
        resetKey={wizardKey}
        categories={existingCategories}
        taxRates={taxRates ?? []}
        currencyCode={catalogCurrency}
        allowService={!starterCatalog}
        isSubmitting={isSubmitting}
        submitLabel="Add product"
        onSubmit={handleCreateProduct}
      />

      {/* Edit Product Wizard */}
      <ProductWizardModal
        open={isEditModalOpen}
        onOpenChange={(open) => {
          if (!open) {
            setSelectedProduct(null)
            setFormData(initialFormData)
          }
          setIsEditModalOpen(open)
        }}
        mode="edit"
        initial={formData}
        resetKey={selectedProduct?.id ?? 'edit'}
        categories={existingCategories}
        taxRates={taxRates ?? []}
        currencyCode={catalogCurrency}
        allowService={!starterCatalog}
        editExtras={
          selectedProduct
            ? {
                existingImageUrl: productPrimaryDisplayImage(selectedProduct),
                digitalFileName: selectedProduct.digitalFileName,
                licenseKeysAvailable: selectedProduct.licenseKeysAvailable,
                onClearDigitalFile: () => void handleClearDigitalFile(),
                clearingFile: isSubmitting,
                onUploadExtraImage: (f) => void handleUploadExtraProductImage(f),
                extraUploading: productExtraImageUploading,
              }
            : undefined
        }
        isSubmitting={isSubmitting}
        submitLabel="Save changes"
        onSubmit={handleEditProduct}
      />

      <ProductVariantsModal
        product={variantsSheetProduct}
        open={variantsSheetProduct !== null}
        onOpenChange={(open) => {
          if (!open) {
            setVariantsSheetProduct(null)
          }
        }}
        formatCurrency={formatCurrency}
        onAddVariant={handleAddVariantModal}
        onUpdateVariant={handleUpdateVariantModal}
        onDeleteVariant={handleDeleteVariantModal}
        onUploadVariantImage={handleUploadVariantImageModal}
      />

      {/* Delete Confirmation Modal */}
      <ConfirmModal
        open={isDeleteModalOpen}
        onOpenChange={(open) => {
          if (!open) setSelectedProduct(null)
          setIsDeleteModalOpen(open)
        }}
        title="Delete Product"
        description={`Are you sure you want to delete "${selectedProduct?.name}"? This action cannot be undone.`}
        confirmLabel="Delete"
        onConfirm={handleDeleteProduct}
        isLoading={isSubmitting}
        variant="destructive"
      />
    </div>
  )
}
