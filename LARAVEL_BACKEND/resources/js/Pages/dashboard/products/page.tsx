'use client'

import { useState, useCallback, useRef, useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { StatsCard, StatsGrid } from '@/components/shared/stats-card'
import { DataTable, type Column, type Filter } from '@/components/shared/data-table'
import { StatusBadge } from '@/components/shared/status-badge'
import { FormModal, ConfirmModal } from '@/components/shared/modal'
import { InputField, TextareaField, SelectField } from '@/components/shared/form-field'
import { useProducts, useCompanySettings } from '@/lib/api-hooks'
import { formatCurrencyAmount, normalizeCurrencyCode } from '@/lib/format-currency'
import {
  createProduct,
  updateProduct,
  deleteProduct,
  companyExportData,
  importProducts,
  createProductVariant,
  deleteProductVariant,
  uploadProductImage,
  uploadVariantImage,
} from '@/lib/api-actions'
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

interface ProductFormData {
  name: string
  description: string
  price: string
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

const initialFormData: ProductFormData = {
  name: '',
  description: '',
  price: '',
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
  const [formData, setFormData] = useState<ProductFormData>(initialFormData)
  const [productImageFile, setProductImageFile] = useState<File | null>(null)
  const [digitalFile, setDigitalFile] = useState<File | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formErrors, setFormErrors] = useState<Record<string, string>>({})
  const [exportOpen, setExportOpen] = useState(false)
  const [exportFormat, setExportFormat] = useState<'csv' | 'json'>('csv')
  const [exporting, setExporting] = useState(false)
  const [importing, setImporting] = useState(false)
  const [importResult, setImportResult] = useState<{ created: number; errors?: { row: number; errors: string[] }[] } | null>(null)
  const importInputRef = useRef<HTMLInputElement>(null)
  const [variantsSheetProduct, setVariantsSheetProduct] = useState<Product | null>(null)
  const [variantLabel, setVariantLabel] = useState('')
  const [variantPrice, setVariantPrice] = useState('')
  const [variantStock, setVariantStock] = useState('0')
  const [variantImageFile, setVariantImageFile] = useState<File | null>(null)
  const [variantSaving, setVariantSaving] = useState(false)
  const [variantImageUploadingId, setVariantImageUploadingId] = useState<string | null>(null)
  const [productExtraImageUploading, setProductExtraImageUploading] = useState(false)

  const { data: companySettings } = useCompanySettings()
  const catalogCurrency = normalizeCurrencyCode(companySettings?.displayCurrency)

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

  const formatCurrency = (value: number) => formatCurrencyAmount(value, catalogCurrency)

  // Validate form
  const validateForm = (): boolean => {
    const errors: Record<string, string> = {}
    
    if (!formData.name.trim()) {
      errors.name = 'Product name is required'
    }
    if (formData.price === '' || Number.isNaN(parseFloat(formData.price)) || parseFloat(formData.price) < 0) {
      errors.price = 'Enter a valid price (0 or more; use 0 if only variants have prices)'
    }
    if (!formData.category) {
      errors.category = 'Category is required'
    }
    if (!formData.stock || parseInt(formData.stock) < 0) {
      errors.stock = 'Valid stock quantity is required'
    }
    if (formData.maxDownloads && (!Number.isInteger(Number(formData.maxDownloads)) || Number(formData.maxDownloads) < 1)) {
      errors.maxDownloads = 'Download limit must be a whole number of at least 1'
    }
    if (formData.bookable && formData.bookingDurationMinutes &&
      (!Number.isInteger(Number(formData.bookingDurationMinutes)) ||
        Number(formData.bookingDurationMinutes) < 5 ||
        Number(formData.bookingDurationMinutes) > 480)) {
      errors.bookingDurationMinutes = 'Duration must be between 5 and 480 minutes'
    }
    
    setFormErrors(errors)
    return Object.keys(errors).length === 0
  }

  // Handle form field change
  const handleFieldChange = (field: keyof ProductFormData, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }))
    // Clear error when user types
    if (formErrors[field]) {
      setFormErrors((prev) => ({ ...prev, [field]: '' }))
    }
  }

  // Handle create product — api-actions.createProduct → POST /api/company/products
  const handleCreateProduct = useCallback(async () => {
    if (!validateForm()) return

    setIsSubmitting(true)
    try {
      const result = await createProduct({
        name: formData.name,
        description: formData.description,
        price: parseFloat(formData.price),
        category: formData.category,
        productType: formData.productType,
        fulfillmentType: formData.fulfillmentType,
        trackInventory: formData.trackInventory,
        requiresDeliveryAddress: formData.requiresDeliveryAddress,
        accessUrl: formData.accessUrl,
        serviceBookingUrl: formData.serviceBookingUrl,
        fulfillmentInstructions: formData.fulfillmentInstructions,
        licenseKeyMode: formData.licenseKeyMode,
        licenseKeyPrefix: formData.licenseKeyPrefix,
        accessExpiresDays: formData.accessExpiresDays ? parseInt(formData.accessExpiresDays, 10) : null,
        maxDownloads: formData.maxDownloads ? parseInt(formData.maxDownloads, 10) : null,
        bookable: formData.bookable,
        bookingDurationMinutes: formData.bookingDurationMinutes ? parseInt(formData.bookingDurationMinutes, 10) : null,
        licenseKeys: formData.licenseKeys || undefined,
        stock: parseInt(formData.stock),
        image: productImageFile ?? undefined,
        digitalFile: digitalFile ?? undefined,
      })

      if (result.success) {
        // Revalidate products data
        mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
        setIsAddModalOpen(false)
        setFormData(initialFormData)
        setProductImageFile(null)
        setDigitalFile(null)
      }
    } catch (error) {
      console.error('Failed to create product:', error)
    } finally {
      setIsSubmitting(false)
    }
  }, [formData, mutate, categoryFilter, statusFilter, searchQuery, productImageFile, digitalFile])

  // Handle edit product — api-actions.updateProduct → PUT /api/company/products/:productId
  const handleEditProduct = useCallback(async () => {
    if (!selectedProduct || !validateForm()) return

    setIsSubmitting(true)
    try {
      const result = await updateProduct(selectedProduct.id, {
        name: formData.name,
        description: formData.description,
        price: parseFloat(formData.price),
        category: formData.category,
        productType: formData.productType,
        fulfillmentType: formData.fulfillmentType,
        trackInventory: formData.trackInventory,
        requiresDeliveryAddress: formData.requiresDeliveryAddress,
        accessUrl: formData.accessUrl,
        serviceBookingUrl: formData.serviceBookingUrl,
        fulfillmentInstructions: formData.fulfillmentInstructions,
        licenseKeyMode: formData.licenseKeyMode,
        licenseKeyPrefix: formData.licenseKeyPrefix,
        accessExpiresDays: formData.accessExpiresDays ? parseInt(formData.accessExpiresDays, 10) : null,
        maxDownloads: formData.maxDownloads ? parseInt(formData.maxDownloads, 10) : null,
        bookable: formData.bookable,
        bookingDurationMinutes: formData.bookingDurationMinutes ? parseInt(formData.bookingDurationMinutes, 10) : null,
        licenseKeys: formData.licenseKeys || undefined,
        stock: parseInt(formData.stock),
        image: productImageFile ?? undefined,
        digitalFile: digitalFile ?? undefined,
      })

      if (result.success) {
        await mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
        setIsEditModalOpen(false)
        setSelectedProduct(null)
        setFormData(initialFormData)
        setProductImageFile(null)
        setDigitalFile(null)
      }
    } catch (error) {
      console.error('Failed to update product:', error)
    } finally {
      setIsSubmitting(false)
    }
  }, [selectedProduct, formData, mutate, categoryFilter, statusFilter, searchQuery, productImageFile, digitalFile])

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
    setProductImageFile(null)
    setDigitalFile(null)
    setFormData({
      name: product.name,
      description: product.description,
      price: product.price.toString(),
      category: product.category,
      productType: product.productType ?? 'physical',
      fulfillmentType: product.fulfillmentType ?? 'shipping',
      trackInventory: product.trackInventory ?? true,
      requiresDeliveryAddress: product.requiresDeliveryAddress ?? true,
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
    setFormErrors({})
    setIsEditModalOpen(true)
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

  const handleAddVariant = useCallback(async () => {
    if (!variantsSheetProduct || !variantLabel.trim() || variantPrice === '') return
    const price = parseFloat(variantPrice)
    if (Number.isNaN(price) || price < 0) return
    setVariantSaving(true)
    try {
      const res = await createProductVariant(variantsSheetProduct.id, {
        label: variantLabel.trim(),
        price,
        stock: parseInt(variantStock, 10) || 0,
        image: variantImageFile ?? undefined,
      })
      if (res.success) {
        setVariantLabel('')
        setVariantPrice('')
        setVariantStock('0')
        setVariantImageFile(null)
        mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
        if (res.variant) {
          setVariantsSheetProduct((prev) =>
            prev
              ? { ...prev, variants: [...(prev.variants ?? []), res.variant!] }
              : null
          )
        }
      }
    } finally {
      setVariantSaving(false)
    }
  }, [variantsSheetProduct, variantLabel, variantPrice, variantStock, mutate, categoryFilter, statusFilter, searchQuery, variantImageFile])

  const handleDeleteVariant = useCallback(
    async (variantId: string) => {
      const res = await deleteProductVariant(variantId)
      if (res.success) {
        mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
        setVariantsSheetProduct((prev) =>
          prev ? { ...prev, variants: (prev.variants ?? []).filter((v) => v.id !== variantId) } : null
        )
      }
    },
    [mutate, categoryFilter, statusFilter, searchQuery]
  )

  const handleUploadVariantImage = useCallback(
    async (variantId: string, file: File) => {
      setVariantImageUploadingId(variantId)
      try {
        const res = await uploadVariantImage(variantId, { image: file, isPrimary: true })
        if (res.success) {
          mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
        }
      } finally {
        setVariantImageUploadingId(null)
      }
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
            <DropdownMenuItem
              onClick={() => {
                setVariantsSheetProduct(product)
                setVariantLabel('')
                setVariantPrice('')
                setVariantStock('0')
                    setVariantImageFile(null)
              }}
            >
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
  const renderProductForm = () => (
    <div className="space-y-4">
      <InputField
        label="Product Name"
        name="name"
        value={formData.name}
        onChange={(value) => handleFieldChange('name', value)}
        placeholder="Enter product name"
        error={formErrors.name}
        required
      />

      <InputField
        label="Category"
        name="category"
        value={formData.category}
        onChange={(value) => handleFieldChange('category', value)}
        placeholder="e.g. Books, Coaching, Templates"
        error={formErrors.category}
        required
      />

      <div className="grid grid-cols-2 gap-4">
        <SelectField
          label="Item type"
          name="productType"
          value={formData.productType}
          onChange={(value) => setFormData((prev) => ({ ...prev, productType: value as ProductFormData['productType'] }))}
          options={[
            { value: 'physical', label: 'Physical product' },
            { value: 'digital', label: 'Digital good' },
            { value: 'service', label: 'Service' },
          ]}
          required
        />
        <SelectField
          label="Fulfillment"
          name="fulfillmentType"
          value={formData.fulfillmentType}
          onChange={(value) => setFormData((prev) => ({ ...prev, fulfillmentType: value as ProductFormData['fulfillmentType'] }))}
          options={[
            { value: 'shipping', label: 'Shipping / delivery' },
            { value: 'download', label: 'Download file' },
            { value: 'link', label: 'Access link' },
            { value: 'booking', label: 'Booking link' },
            { value: 'manual', label: 'Manual instructions' },
          ]}
          required
        />
      </div>

      <div className="grid grid-cols-2 gap-4">
        <InputField
          label="Price"
          name="price"
          type="number"
          value={formData.price}
          onChange={(value) => handleFieldChange('price', value)}
          placeholder="0.00"
          error={formErrors.price}
          required
        />
        <InputField
          label={formData.trackInventory ? 'Stock' : 'Stock'}
          name="stock"
          type="number"
          value={formData.stock}
          onChange={(value) => handleFieldChange('stock', value)}
          placeholder="0"
          error={formErrors.stock}
          required
        />
      </div>

      <div className="grid grid-cols-2 gap-4 rounded-md border border-border/70 p-3">
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={formData.trackInventory}
            onChange={(e) => setFormData((prev) => ({ ...prev, trackInventory: e.target.checked }))}
          />
          Track inventory
        </label>
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={formData.requiresDeliveryAddress}
            onChange={(e) => setFormData((prev) => ({ ...prev, requiresDeliveryAddress: e.target.checked }))}
          />
          Ask for delivery address
        </label>
      </div>

      <TextareaField
        label="Description"
        name="description"
        value={formData.description}
        onChange={(value) => handleFieldChange('description', value)}
        placeholder="Enter product description"
        description="This will be shown to customers and used by AI for responses"
      />

      {(formData.productType === 'digital' || formData.productType === 'service') && (
        <>
          <InputField
            label="Access link"
            name="accessUrl"
            value={formData.accessUrl}
            onChange={(value) => handleFieldChange('accessUrl', value)}
            placeholder="https://..."
            description="For course portals, members-only links, Google Drive, Notion, Calendly, etc."
          />
          <InputField
            label="Booking / secondary link"
            name="serviceBookingUrl"
            value={formData.serviceBookingUrl}
            onChange={(value) => handleFieldChange('serviceBookingUrl', value)}
            placeholder="https://..."
          />
          {formData.productType === 'digital' && (
            <InputField
              label="Maximum downloads"
              name="maxDownloads"
              type="number"
              value={formData.maxDownloads}
              onChange={(value) => handleFieldChange('maxDownloads', value)}
              placeholder="Leave blank for unlimited"
              description="Per purchased item"
              error={formErrors.maxDownloads}
            />
          )}
          {(formData.productType === 'service' || formData.fulfillmentType === 'booking') && (
            <div className="space-y-3 rounded-md border border-border/70 p-3">
              <label className="flex items-center gap-2 text-sm font-medium">
                <input
                  type="checkbox"
                  checked={formData.bookable}
                  onChange={(e) => setFormData((prev) => ({ ...prev, bookable: e.target.checked }))}
                />
                Enable customer bookings
              </label>
              {formData.bookable && (
                <InputField
                  label="Meeting duration (minutes)"
                  name="bookingDurationMinutes"
                  type="number"
                  value={formData.bookingDurationMinutes}
                  onChange={(value) => handleFieldChange('bookingDurationMinutes', value)}
                  placeholder="e.g. 30"
                  description="Leave blank to use the booking default"
                  error={formErrors.bookingDurationMinutes}
                />
              )}
            </div>
          )}
          <TextareaField
            label="Fulfillment instructions"
            name="fulfillmentInstructions"
            value={formData.fulfillmentInstructions}
            onChange={(value) => handleFieldChange('fulfillmentInstructions', value)}
            placeholder="Explain how the customer gets access after payment"
            description="Sent after payment and shown in the receipt."
          />
          <div className="space-y-2 rounded-md border border-border/70 p-3">
            <label className="text-sm font-medium text-foreground">Digital file / resource</label>
            {selectedProduct?.digitalFileName && !digitalFile && (
              <p className="text-xs text-muted-foreground">Current file: {selectedProduct.digitalFileName} (private — delivered via signed link after payment)</p>
            )}
            {digitalFile && <p className="text-xs text-muted-foreground">Selected: {digitalFile.name}</p>}
            <input
              type="file"
              accept=".pdf,.epub,.txt,.csv,.zip,.doc,.docx"
              onChange={(e) => setDigitalFile(e.target.files?.[0] ?? null)}
              className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm file:font-medium"
            />
            {selectedProduct?.digitalFileName && !digitalFile && (
              <button
                type="button"
                className="text-xs text-destructive underline"
                onClick={async () => {
                  if (!selectedProduct) return
                  setIsSubmitting(true)
                  try {
                    await updateProduct(selectedProduct.id, { clearDigitalFile: true })
                    mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
                    setSelectedProduct({ ...selectedProduct, digitalFileName: null, hasDigitalFile: false })
                  } finally {
                    setIsSubmitting(false)
                  }
                }}
              >
                Remove current digital file
              </button>
            )}
          </div>
          <div className="grid grid-cols-2 gap-4">
            <SelectField
              label="License keys"
              name="licenseKeyMode"
              value={formData.licenseKeyMode}
              onChange={(value) => setFormData((prev) => ({ ...prev, licenseKeyMode: value as ProductFormData['licenseKeyMode'] }))}
              options={[
                { value: 'none', label: 'None' },
                { value: 'auto', label: 'Auto-generate' },
                { value: 'pool', label: 'From key pool' },
              ]}
            />
            <InputField
              label="Access expires (days)"
              name="accessExpiresDays"
              type="number"
              value={formData.accessExpiresDays}
              onChange={(value) => handleFieldChange('accessExpiresDays', value)}
              placeholder="Leave blank for no expiry"
              description="Applies to signed download / portal links"
            />
          </div>
          {formData.licenseKeyMode !== 'none' && (
            <InputField
              label="License key prefix"
              name="licenseKeyPrefix"
              value={formData.licenseKeyPrefix}
              onChange={(value) => handleFieldChange('licenseKeyPrefix', value)}
              placeholder="e.g. COURSE"
              description="Used when auto-generating keys"
            />
          )}
          {formData.licenseKeyMode === 'pool' && (
            <>
              {(selectedProduct?.licenseKeysAvailable ?? 0) === 0 && !formData.licenseKeys.trim() && (
                <p className="text-xs text-amber-700">
                  No keys in the pool yet. Import keys below before selling this product, or checkout will be blocked.
                </p>
              )}
              <TextareaField
                label="Import license keys"
                name="licenseKeys"
                value={formData.licenseKeys}
                onChange={(value) => handleFieldChange('licenseKeys', value)}
                placeholder={'KEY-001\nKEY-002\nKEY-003'}
                description={
                  selectedProduct?.licenseKeysAvailable != null
                    ? `Add one key per line. Available in pool: ${selectedProduct.licenseKeysAvailable}`
                    : 'Add one key per line (or comma-separated). Keys are assigned after payment.'
                }
              />
            </>
          )}
        </>
      )}

      <div className="space-y-2">
        <label className="text-sm font-medium text-foreground">Main product image</label>
        {selectedProduct && productPrimaryDisplayImage(selectedProduct) && !productImageFile && (
          <div className="h-20 w-20 overflow-hidden rounded-md border border-border">
            <ProductThumbImg
              src={productPrimaryDisplayImage(selectedProduct)!}
              alt={selectedProduct.name}
              className="h-full w-full object-cover"
            />
          </div>
        )}
        {productImageFile && (
          <p className="text-xs text-muted-foreground">Selected: {productImageFile.name}</p>
        )}
        <input
          type="file"
          accept="image/*"
          onChange={(e) => setProductImageFile(e.target.files?.[0] ?? null)}
          className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm file:font-medium"
        />
      </div>

      {selectedProduct && (
        <div className="space-y-2 rounded-md border border-border/70 p-3">
          <p className="text-sm font-medium text-foreground">Add extra image variation</p>
          <input
            type="file"
            accept="image/*"
            onChange={(e) => {
              const file = e.target.files?.[0]
              if (file) handleUploadExtraProductImage(file)
              e.currentTarget.value = ''
            }}
            disabled={productExtraImageUploading}
            className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm file:font-medium disabled:opacity-60"
          />
          <p className="text-xs text-muted-foreground">
            Upload multiple image variations for this product.
          </p>
        </div>
      )}
    </div>
  )

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
            setFormErrors({})
            setProductImageFile(null)
            setDigitalFile(null)
            setIsAddModalOpen(true)
          }}>
            <Plus className="mr-2 h-4 w-4" />
            Add Product
          </Button>
        </div>
        </div>
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

      {/* Add Product Modal */}
      <FormModal
        open={isAddModalOpen}
        onOpenChange={(open) => {
          if (!open) {
            setProductImageFile(null)
            setDigitalFile(null)
          }
          setIsAddModalOpen(open)
        }}
        title="Add New Product"
        description="Add a new product to your catalog"
        onSubmit={handleCreateProduct}
        submitLabel="Add Product"
        isLoading={isSubmitting}
        isValid={
          formData.name.trim() !== '' &&
          formData.price !== '' &&
          parseFloat(formData.price) >= 0 &&
          !Number.isNaN(parseFloat(formData.price)) &&
          formData.category !== ''
        }
      >
        {renderProductForm()}
      </FormModal>

      {/* Edit Product Modal */}
      <FormModal
        open={isEditModalOpen}
        onOpenChange={(open) => {
          if (!open) {
            setSelectedProduct(null)
            setFormData(initialFormData)
            setProductImageFile(null)
            setDigitalFile(null)
          }
          setIsEditModalOpen(open)
        }}
        title="Edit Product"
        description="Update product details"
        onSubmit={handleEditProduct}
        submitLabel="Save Changes"
        isLoading={isSubmitting}
        isValid={
          formData.name.trim() !== '' &&
          formData.price !== '' &&
          parseFloat(formData.price) >= 0 &&
          !Number.isNaN(parseFloat(formData.price)) &&
          formData.category !== ''
        }
      >
        {renderProductForm()}
      </FormModal>

      <Sheet
        open={variantsSheetProduct !== null}
        onOpenChange={(open) => {
          if (!open) {
            setVariantsSheetProduct(null)
            setVariantImageFile(null)
          }
        }}
      >
        <SheetContent className="overflow-y-auto sm:max-w-md">
          <SheetHeader>
            <SheetTitle>Product options</SheetTitle>
            <SheetDescription>
              Add sizes, colors, brands, etc. Customers pick a number for the product, then a number for the option, then quantity in WhatsApp.
            </SheetDescription>
          </SheetHeader>
          {variantsSheetProduct && (
            <div className="mt-6 space-y-6">
              <p className="text-sm font-medium text-foreground">{variantsSheetProduct.name}</p>
              <ul className="space-y-2">
                {(variantsSheetProduct.variants ?? []).map((v) => {
                  const vThumb = variantDisplayImage(v)
                  return (
                  <li
                    key={v.id}
                    className="rounded-md border border-border/60 px-3 py-2 text-sm"
                  >
                    <div className="flex items-center justify-between gap-2">
                      <div className="flex items-center gap-3">
                        {vThumb ? (
                          <div className="h-10 w-10 overflow-hidden rounded-md">
                            <ProductThumbImg
                              src={vThumb}
                              alt={v.label}
                              className="h-full w-full object-cover"
                            />
                          </div>
                        ) : (
                          <div className="h-10 w-10 rounded-md bg-muted" />
                        )}
                        <span>
                          {v.label} — {formatCurrency(v.price)} (stock {v.stock})
                        </span>
                      </div>
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="text-destructive"
                        onClick={() => handleDeleteVariant(v.id)}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                    <div className="mt-2">
                      <input
                        type="file"
                        accept="image/*"
                        onChange={(e) => {
                          const file = e.target.files?.[0]
                          if (file) handleUploadVariantImage(v.id, file)
                          e.currentTarget.value = ''
                        }}
                        disabled={variantImageUploadingId === v.id}
                        className="block w-full text-xs text-muted-foreground file:mr-2 file:rounded-md file:border-0 file:bg-secondary file:px-2 file:py-1 file:text-xs file:font-medium disabled:opacity-60"
                      />
                    </div>
                  </li>
                  )
                })}
                {(variantsSheetProduct.variants ?? []).length === 0 && (
                  <p className="text-sm text-muted-foreground">No options yet. Add one below.</p>
                )}
              </ul>
              <div className="space-y-3 border-t border-border pt-4">
                <InputField label="Option label" name="vlabel" value={variantLabel} onChange={setVariantLabel} placeholder="e.g. Blue / L / Brand X" />
                <div className="grid grid-cols-2 gap-3">
                  <InputField label="Price" name="vprice" type="number" value={variantPrice} onChange={setVariantPrice} placeholder="0" />
                  <InputField label="Stock" name="vstock" type="number" value={variantStock} onChange={setVariantStock} placeholder="0" />
                </div>
                <div className="space-y-1">
                  <label className="text-sm font-medium text-foreground">Option image (optional)</label>
                  <input
                    type="file"
                    accept="image/*"
                    onChange={(e) => setVariantImageFile(e.target.files?.[0] ?? null)}
                    className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm file:font-medium"
                  />
                  {variantImageFile && (
                    <p className="text-xs text-muted-foreground">Selected: {variantImageFile.name}</p>
                  )}
                </div>
                <Button type="button" onClick={handleAddVariant} disabled={variantSaving || !variantLabel.trim() || variantPrice === ''}>
                  {variantSaving ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Add option'}
                </Button>
              </div>
            </div>
          )}
        </SheetContent>
      </Sheet>

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
