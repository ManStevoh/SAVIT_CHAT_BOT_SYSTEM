'use client'

import { useState, useCallback, useRef } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { StatsCard, StatsGrid } from '@/components/shared/stats-card'
import { DataTable, type Column, type Filter } from '@/components/shared/data-table'
import { StatusBadge } from '@/components/shared/status-badge'
import { FormModal, ConfirmModal } from '@/components/shared/modal'
import { InputField, TextareaField, SelectField } from '@/components/shared/form-field'
import { useProducts } from '@/lib/api-hooks'
import { createProduct, updateProduct, deleteProduct, companyExportData, importProducts, createProductVariant, deleteProductVariant } from '@/lib/api-actions'
import { downloadFile } from '@/lib/api-client'
import type { Product } from '@/lib/mock-data'
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
  stock: string
}

const initialFormData: ProductFormData = {
  name: '',
  description: '',
  price: '',
  category: '',
  stock: '',
}

export default function ProductsPage() {
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
  const [variantSaving, setVariantSaving] = useState(false)

  // API: GET /api/company/products (useProducts)
  const { data: products, isLoading, error } = useProducts({
    category: categoryFilter,
    status: statusFilter,
    search: searchQuery,
  })

  // Calculate stats from data
  const stats = {
    total: products?.length || 0,
    inStock: products?.filter((p) => p.stock > 10).length || 0,
    lowStock: products?.filter((p) => p.stock > 0 && p.stock <= 10).length || 0,
    outOfStock: products?.filter((p) => p.stock === 0).length || 0,
  }

  // Format currency
  const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'EGP',
      minimumFractionDigits: 0,
    }).format(value)
  }

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
        stock: parseInt(formData.stock),
      })

      if (result.success) {
        // Revalidate products data
        mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
        setIsAddModalOpen(false)
        setFormData(initialFormData)
      }
    } catch (error) {
      console.error('Failed to create product:', error)
    } finally {
      setIsSubmitting(false)
    }
  }, [formData, mutate, categoryFilter, statusFilter, searchQuery])

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
        stock: parseInt(formData.stock),
      })

      if (result.success) {
        mutate(['products', { category: categoryFilter, status: statusFilter, search: searchQuery }])
        setIsEditModalOpen(false)
        setSelectedProduct(null)
        setFormData(initialFormData)
      }
    } catch (error) {
      console.error('Failed to update product:', error)
    } finally {
      setIsSubmitting(false)
    }
  }, [selectedProduct, formData, mutate, categoryFilter, statusFilter, searchQuery])

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
      price: product.price.toString(),
      category: product.category,
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
      })
      if (res.success) {
        setVariantLabel('')
        setVariantPrice('')
        setVariantStock('0')
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
  }, [variantsSheetProduct, variantLabel, variantPrice, variantStock, mutate, categoryFilter, statusFilter, searchQuery])

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
      cell: (product) => (
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
            {product.image ? (
              <img
                src={product.image}
                alt={product.name}
                className="h-full w-full rounded-lg object-cover"
              />
            ) : (
              <Package className="h-5 w-5 text-primary" />
            )}
          </div>
          <div>
            <span className="font-medium text-foreground">{product.name}</span>
            <p className="text-xs text-muted-foreground line-clamp-1">
              {product.description}
            </p>
          </div>
        </div>
      ),
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
              }}
            >
              <Layers className="mr-2 h-4 w-4" />
              Options / variants
            </DropdownMenuItem>
            <DropdownMenuItem>
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

      <SelectField
        label="Category"
        name="category"
        value={formData.category}
        onChange={(value) => handleFieldChange('category', value)}
        options={[
          { value: 'Phones', label: 'Phones' },
          { value: 'Laptops', label: 'Laptops' },
          { value: 'Tablets', label: 'Tablets' },
          { value: 'Accessories', label: 'Accessories' },
        ]}
        placeholder="Select category"
        error={formErrors.category}
        required
      />

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
          label="Stock"
          name="stock"
          type="number"
          value={formData.stock}
          onChange={(value) => handleFieldChange('stock', value)}
          placeholder="0"
          error={formErrors.stock}
          required
        />
      </div>

      <TextareaField
        label="Description"
        name="description"
        value={formData.description}
        onChange={(value) => handleFieldChange('description', value)}
        placeholder="Enter product description"
        description="This will be shown to customers and used by AI for responses"
      />
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
          iconColor="text-yellow-500"
          isLoading={isLoading}
        />
        <StatsCard
          title="Out of Stock"
          value={stats.outOfStock}
          icon={AlertCircle}
          iconColor="text-destructive"
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
        onOpenChange={setIsAddModalOpen}
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
          if (!open) setVariantsSheetProduct(null)
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
                {(variantsSheetProduct.variants ?? []).map((v) => (
                  <li
                    key={v.id}
                    className="flex items-center justify-between gap-2 rounded-md border border-border/60 px-3 py-2 text-sm"
                  >
                    <span>
                      {v.label} — {formatCurrency(v.price)} (stock {v.stock})
                    </span>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="text-destructive"
                      onClick={() => handleDeleteVariant(v.id)}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </li>
                ))}
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
