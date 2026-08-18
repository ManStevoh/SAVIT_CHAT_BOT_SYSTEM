'use client'

import { useState, useCallback, useEffect } from 'react'
import { useSearchParams, useRouter } from 'next/navigation'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { StatsCard, StatsGrid } from '@/components/shared/stats-card'
import { DataTable, type Column, type Filter } from '@/components/shared/data-table'
import { StatusBadge } from '@/components/shared/status-badge'
import { FormModal } from '@/components/shared/modal'
import { SelectField } from '@/components/shared/form-field'
import { useOrders, useOrder, useCompanySettings } from '@/lib/api-hooks'
import { formatCurrencyAmount, normalizeCurrencyCode, currencyDisplayFromSettings } from '@/lib/format-currency'
import { updateOrderStatus } from '@/lib/api-actions'
import type { Order } from '@/lib/mock-data'
import {
  Search,
  ShoppingCart,
  Clock,
  CheckCircle,
  Truck,
  Package,
  Eye,
  MessageSquare,
  Download,
  Loader2,
  Copy,
  MapPin,
  ExternalLink,
  Phone,
  AlertCircle,
  XCircle,
  FileText,
  Send,
} from 'lucide-react'
import { companyExportData } from '@/lib/api-actions'
import { downloadFile } from '@/lib/api-client'
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
import { useSWRConfig } from 'swr'
import { useToast } from '@/hooks/use-toast'
import { PageHeader } from '@/components/shared/page-header'

type OrderTabFilter = 'all' | 'pending' | 'waiting_shipping' | 'shipped_delivered' | 'failed'

export default function OrdersPage() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const initialSearch = searchParams.get('search') ?? ''
  const orderIdFromNotif = searchParams.get('orderId')
  const { data: companySettings } = useCompanySettings()
  const catalogCurrency = normalizeCurrencyCode(companySettings?.displayCurrency)
  const { mutate } = useSWRConfig()
  const { toast } = useToast()

  const [searchQuery, setSearchQuery] = useState(initialSearch)
  const [statusFilter, setStatusFilter] = useState<OrderTabFilter>('all')
  const [attributedOnly, setAttributedOnly] = useState(false)
  const [page, setPage] = useState(1)

  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null)
  const [isUpdating, setIsUpdating] = useState(false)
  const [newStatus, setNewStatus] = useState<string>('')
  const [newPaymentStatus, setNewPaymentStatus] = useState<Order['paymentStatus']>('pending')
  const [courierName, setCourierName] = useState<string>('')
  const [trackingNumber, setTrackingNumber] = useState<string>('')
  const [deliveryAddress, setDeliveryAddress] = useState<string>('')

  const [exportOpen, setExportOpen] = useState(false)
  const [exportFormat, setExportFormat] = useState<'csv' | 'json'>('csv')
  const [exporting, setExporting] = useState(false)

  // API: GET /api/company/orders
  const { data, isLoading, error } = useOrders({
    status: statusFilter,
    search: searchQuery,
    page,
    limit: 10,
    attributedOnly,
  })

  const { data: singleOrderPayload } = useOrder(orderIdFromNotif)

  useEffect(() => {
    if (!orderIdFromNotif || !singleOrderPayload?.order) return
    const o = singleOrderPayload.order
    openOrderModal(o)
    router.replace('/dashboard/orders', { scroll: false })
  }, [orderIdFromNotif, singleOrderPayload, router])

  const openOrderModal = (order: Order, defaultStatus?: string) => {
    setSelectedOrder(order)
    setNewStatus(defaultStatus || order.status)
    setNewPaymentStatus(order.paymentStatus)
    setCourierName(order.courierName || '')
    setTrackingNumber(order.trackingNumber || '')
    setDeliveryAddress(order.deliveryAddress || '')
  }

  // Calculate stats from total & items
  const stats = {
    total: data?.total || 0,
    pending: data?.orders?.filter((o) => o.status === 'pending' || o.paymentStatus === 'pending').length || 0,
    waitingShipping: data?.orders?.filter((o) => (o.status === 'confirmed' || o.paymentStatus === 'paid') && o.status !== 'shipped' && o.status !== 'delivered' && o.status !== 'cancelled').length || 0,
    completed: data?.orders?.filter((o) => o.status === 'shipped' || o.status === 'delivered').length || 0,
    failed: data?.orders?.filter((o) => o.status === 'cancelled' || o.paymentStatus === 'refunded').length || 0,
  }

  const formatCurrency = (value: number) =>
    formatCurrencyAmount(value, catalogCurrency, currencyDisplayFromSettings(companySettings))

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    })
  }

  const copyToClipboard = (text: string, label: string) => {
    navigator.clipboard.writeText(text)
    toast({
      title: `${label} Copied`,
      description: `"${text}" copied to clipboard.`,
    })
  }

  const copyShippingSlip = (order: Order) => {
    const itemsText = order.products.map((p) => `• ${p.quantity}x ${p.name}`).join('\n')
    const slip = `📦 DISPATCH SHIPPING SLIP
Order: #${order.orderNumber}
Customer: ${order.customerName}
Phone: ${order.customerPhone}
Fulfillment: ${(order.fulfillmentType || 'delivery').toUpperCase()}
Address: ${order.deliveryAddress || 'N/A'}
Items:
${itemsText}
Total: ${formatCurrency(order.total)} (${order.paymentStatus === 'paid' ? 'PAID' : 'COLLECT CASH / UNPAID'})`
    navigator.clipboard.writeText(slip)
    toast({
      title: 'Shipping Slip Copied!',
      description: `Full courier slip for #${order.orderNumber} copied to clipboard ready for dispatch.`,
    })
  }

  const handleUpdateStatus = useCallback(async () => {
    if (!selectedOrder || !newStatus) return

    setIsUpdating(true)
    try {
      const result = await updateOrderStatus(
        selectedOrder.id,
        newStatus as Order['status'],
        newPaymentStatus,
        {
          courierName: courierName.trim() || undefined,
          trackingNumber: trackingNumber.trim() || undefined,
          deliveryAddress: deliveryAddress.trim() || undefined,
        }
      )
      if (result.success) {
        mutate(['orders', { status: statusFilter, search: searchQuery, page, limit: 10 }])
        toast({
          title: 'Order Updated',
          description: result.message || 'Order details updated successfully.',
        })
        if (result.whatsappSent === false && result.whatsappError) {
          toast({
            title: 'WhatsApp Notification Warning',
            description: result.whatsappError,
            variant: 'destructive',
          })
        }
        setSelectedOrder(null)
      }
    } catch (err) {
      console.error('Failed to update order', err)
      toast({
        title: 'Update Failed',
        description: 'An error occurred while updating the order.',
        variant: 'destructive',
      })
    } finally {
      setIsUpdating(false)
    }
  }, [selectedOrder, newStatus, newPaymentStatus, courierName, trackingNumber, deliveryAddress, mutate, statusFilter, searchQuery, page, toast])

  const handleExportOrders = async () => {
    setExporting(true)
    try {
      const result = await companyExportData('orders', exportFormat)
      if (result.success && result.downloadUrl && result.filename) {
        await downloadFile(result.downloadUrl, result.filename)
        setExportOpen(false)
      }
    } finally {
      setExporting(false)
    }
  }

  const columns: Column<Order>[] = [
    {
      key: 'orderNumber',
      header: 'Order & Type',
      cell: (order) => {
        const type = order.fulfillmentType || 'delivery'
        return (
          <div className="space-y-1">
            <span className="font-medium text-foreground">{order.orderNumber}</span>
            <div className="flex items-center gap-1">
              {type === 'pickup' ? (
                <Badge variant="secondary" className="text-[10px] px-1.5 py-0 bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20">
                  🏬 Pickup
                </Badge>
              ) : type === 'dine_in' ? (
                <Badge variant="secondary" className="text-[10px] px-1.5 py-0 bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20">
                  🍽️ Dine-in {order.dineInTableName ? `(${order.dineInTableName})` : ''}
                </Badge>
              ) : (
                <Badge variant="secondary" className="text-[10px] px-1.5 py-0 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20">
                  🚚 Delivery
                </Badge>
              )}
            </div>
          </div>
        )
      },
    },
    {
      key: 'customer',
      header: 'Customer',
      cell: (order) => (
        <div>
          <div className="font-medium text-foreground flex items-center gap-1.5">
            {order.customerName}
          </div>
          <div className="flex items-center gap-1 text-xs text-muted-foreground mt-0.5">
            <span>{order.customerPhone}</span>
            {order.customerPhone && (
              <button
                type="button"
                onClick={(e) => { e.stopPropagation(); copyToClipboard(order.customerPhone, 'Phone number') }}
                className="hover:text-primary transition-colors p-0.5"
                title="Copy Phone"
              >
                <Copy className="h-3 w-3" />
              </button>
            )}
          </div>
        </div>
      ),
    },
    {
      key: 'deliveryAddress',
      header: 'Shipping Address',
      cell: (order) => {
        if (!order.deliveryAddress) {
          return <span className="text-xs text-muted-foreground italic">No address (Self-pickup / Dine-in)</span>
        }
        return (
          <div className="max-w-[200px] text-xs space-y-1">
            <div className="truncate font-normal text-foreground" title={order.deliveryAddress}>
              {order.deliveryAddress}
            </div>
            <div className="flex items-center gap-1.5">
              <button
                type="button"
                onClick={(e) => { e.stopPropagation(); copyToClipboard(order.deliveryAddress!, 'Shipping Address') }}
                className="text-[11px] text-primary hover:underline inline-flex items-center gap-0.5"
              >
                <Copy className="h-3 w-3" /> Copy
              </button>
              <a
                href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(order.deliveryAddress)}`}
                target="_blank"
                rel="noreferrer"
                className="text-[11px] text-muted-foreground hover:text-foreground inline-flex items-center gap-0.5"
                onClick={(e) => e.stopPropagation()}
              >
                <MapPin className="h-3 w-3 text-red-500" /> Map
              </a>
            </div>
          </div>
        )
      },
    },
    {
      key: 'items',
      header: 'Items',
      cell: (order) => (
        <div className="text-sm text-muted-foreground max-w-[160px]">
          {order.products.slice(0, 2).map((p) => `${p.quantity}x ${p.name}`).join(', ')}
          {order.products.length > 2 && ` +${order.products.length - 2} more`}
        </div>
      ),
    },
    {
      key: 'total',
      header: 'Amount',
      cell: (order) => (
        <span className="font-medium text-foreground">{formatCurrency(order.total)}</span>
      ),
    },
    {
      key: 'status',
      header: 'Status & Shipping',
      cell: (order) => (
        <div className="space-y-1">
          <StatusBadge status={order.status} />
          {order.courierName || order.trackingNumber ? (
            <div className="text-[11px] text-muted-foreground flex items-center gap-1 font-mono">
              <Truck className="h-3 w-3 text-primary" />
              <span>{order.courierName || 'Shipped'}: {order.trackingNumber || 'No #'}</span>
            </div>
          ) : null}
        </div>
      ),
    },
    {
      key: 'paymentStatus',
      header: 'Payment',
      cell: (order) => <StatusBadge status={order.paymentStatus} />,
    },
    {
      key: 'actions',
      header: '',
      cell: (order) => {
        const isReadyToShip = (order.status === 'confirmed' || order.paymentStatus === 'paid') && order.status !== 'shipped' && order.status !== 'delivered' && order.status !== 'cancelled'
        return (
          <div className="flex items-center gap-1.5">
            {isReadyToShip ? (
              <Button
                variant="default"
                size="sm"
                className="bg-emerald-600 hover:bg-emerald-700 text-white h-7 text-xs px-2.5 shadow-sm"
                onClick={() => openOrderModal(order, 'shipped')}
              >
                <Truck className="h-3.5 w-3.5 mr-1" />
                Ship Now
              </Button>
            ) : null}
            <Button
              variant="outline"
              size="sm"
              className="h-7 text-xs px-2"
              onClick={() => openOrderModal(order)}
            >
              <Eye className="h-3.5 w-3.5 mr-1" />
              View
            </Button>
          </div>
        )
      },
    },
  ]

  const filters: Filter[] = [
    {
      key: 'status',
      label: 'Status Category',
      options: [
        { value: 'all', label: 'All Orders' },
        { value: 'pending', label: 'Pending Payment / Unconfirmed' },
        { value: 'waiting_shipping', label: 'Ready to Ship / Waiting Shipping 🚚' },
        { value: 'shipped_delivered', label: 'Shipped & Delivered ✅' },
        { value: 'failed', label: 'Failed / Cancelled ❌' },
      ],
    },
  ]

  return (
    <div className="space-y-8">
      <PageHeader
        title="Orders & Shipping Center"
        description="Track customer orders, separate pending/failed orders, and dispatch fast shipping."
        actions={
          <>
            <Button
              variant={attributedOnly ? 'default' : 'outline'}
              size="sm"
              className="h-9"
              onClick={() => { setAttributedOnly((v) => !v); setPage(1) }}
            >
              Attributed only
            </Button>
            <TooltipProvider>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Popover open={exportOpen} onOpenChange={setExportOpen}>
                    <PopoverTrigger asChild>
                      <Button variant="outline" size="sm" className="h-9">
                        <Download className="mr-2 h-4 w-4" />
                        Export
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-64" align="end">
                      <div className="space-y-3">
                        <p className="text-sm font-medium">Export orders</p>
                        <Select value={exportFormat} onValueChange={(v) => setExportFormat(v as 'csv' | 'json')}>
                          <SelectTrigger><SelectValue /></SelectTrigger>
                          <SelectContent>
                            <SelectItem value="csv">CSV (Excel)</SelectItem>
                            <SelectItem value="json">JSON</SelectItem>
                          </SelectContent>
                        </Select>
                        <Button size="sm" className="w-full" onClick={handleExportOrders} disabled={exporting}>
                          {exporting ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Download className="mr-2 h-4 w-4" />}
                          {exporting ? 'Exporting…' : 'Download'}
                        </Button>
                      </div>
                    </PopoverContent>
                  </Popover>
                </TooltipTrigger>
                <TooltipContent side="bottom" className="max-w-xs">
                  Download order history as CSV or JSON with full customer and shipping details.
                </TooltipContent>
              </Tooltip>
            </TooltipProvider>
          </>
        }
      />

      {/* Interactive Stats Grid - Click card to filter tabs */}
      <StatsGrid columns={5}>
        <div
          onClick={() => { setStatusFilter('all'); setPage(1) }}
          className="cursor-pointer transition-transform hover:scale-[1.02]"
        >
          <StatsCard
            title="Total Orders"
            value={stats.total}
            icon={ShoppingCart}
            isLoading={isLoading}
            formatter={(v) => v.toLocaleString()}
          />
        </div>
        <div
          onClick={() => { setStatusFilter('pending'); setPage(1) }}
          className="cursor-pointer transition-transform hover:scale-[1.02]"
        >
          <StatsCard
            title="Pending Payment"
            value={stats.pending}
            icon={Clock}
            isLoading={isLoading}
            formatter={(v) => v.toLocaleString()}
          />
        </div>
        <div
          onClick={() => { setStatusFilter('waiting_shipping'); setPage(1) }}
          className="cursor-pointer transition-transform hover:scale-[1.02]"
        >
          <StatsCard
            title="Ready to Ship"
            value={stats.waitingShipping}
            icon={Truck}
            isLoading={isLoading}
            formatter={(v) => v.toLocaleString()}
          />
        </div>
        <div
          onClick={() => { setStatusFilter('shipped_delivered'); setPage(1) }}
          className="cursor-pointer transition-transform hover:scale-[1.02]"
        >
          <StatsCard
            title="Shipped & Delivered"
            value={stats.completed}
            icon={CheckCircle}
            isLoading={isLoading}
            formatter={(v) => v.toLocaleString()}
          />
        </div>
        <div
          onClick={() => { setStatusFilter('failed'); setPage(1) }}
          className="cursor-pointer transition-transform hover:scale-[1.02]"
        >
          <StatsCard
            title="Failed / Cancelled"
            value={stats.failed}
            icon={XCircle}
            isLoading={isLoading}
            formatter={(v) => v.toLocaleString()}
          />
        </div>
      </StatsGrid>

      {/* Order Category Filter Tabs Bar */}
      <Card className="border-border/60 bg-card shadow-sm">
        <CardHeader className="pb-3 border-b border-border/40">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <CardTitle className="text-base font-semibold">Orders Pipeline</CardTitle>
              <p className="text-xs text-muted-foreground">Select a category tab to view separated orders</p>
            </div>

            {/* Quick Segmented Tabs */}
            <div className="flex items-center gap-1 bg-secondary/50 p-1 rounded-lg border border-border/50 text-xs overflow-x-auto">
              <button
                type="button"
                onClick={() => { setStatusFilter('all'); setPage(1) }}
                className={`px-3 py-1.5 rounded-md font-medium transition-colors whitespace-nowrap ${
                  statusFilter === 'all'
                    ? 'bg-background text-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                All Orders
              </button>
              <button
                type="button"
                onClick={() => { setStatusFilter('pending'); setPage(1) }}
                className={`px-3 py-1.5 rounded-md font-medium transition-colors whitespace-nowrap flex items-center gap-1.5 ${
                  statusFilter === 'pending'
                    ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                <Clock className="h-3.5 w-3.5" />
                Pending
              </button>
              <button
                type="button"
                onClick={() => { setStatusFilter('waiting_shipping'); setPage(1) }}
                className={`px-3 py-1.5 rounded-md font-medium transition-colors whitespace-nowrap flex items-center gap-1.5 ${
                  statusFilter === 'waiting_shipping'
                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 shadow-sm'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                <Truck className="h-3.5 w-3.5" />
                Ready to Ship (Waiting Shipping)
              </button>
              <button
                type="button"
                onClick={() => { setStatusFilter('shipped_delivered'); setPage(1) }}
                className={`px-3 py-1.5 rounded-md font-medium transition-colors whitespace-nowrap flex items-center gap-1.5 ${
                  statusFilter === 'shipped_delivered'
                    ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/30'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                <CheckCircle className="h-3.5 w-3.5" />
                Shipped & Delivered
              </button>
              <button
                type="button"
                onClick={() => { setStatusFilter('failed'); setPage(1) }}
                className={`px-3 py-1.5 rounded-md font-medium transition-colors whitespace-nowrap flex items-center gap-1.5 ${
                  statusFilter === 'failed'
                    ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                <XCircle className="h-3.5 w-3.5" />
                Failed / Cancelled
              </button>
            </div>
          </div>
        </CardHeader>
        <CardContent className="pt-4">
          <DataTable
            data={data?.orders}
            columns={columns}
            isLoading={isLoading}
            error={error}
            searchPlaceholder="Search order #, customer name, phone, address, or tracking..."
            onSearch={setSearchQuery}
            filters={filters}
            filterValues={{ status: statusFilter }}
            onFilterChange={(key, value) => {
              if (key === 'status') setStatusFilter(value as OrderTabFilter)
              setPage(1)
            }}
            pagination={
              data
                ? {
                    page: data.page,
                    totalPages: data.totalPages,
                    onPageChange: setPage,
                  }
                : undefined
            }
            emptyMessage="No orders found in this category"
            emptyDescription="Orders matching this status filter will appear here."
          />
        </CardContent>
      </Card>

      {/* Fast Shipping & Order Details Modal */}
      <FormModal
        open={!!selectedOrder}
        onOpenChange={(open) => {
          if (!open) {
            setSelectedOrder(null)
          }
        }}
        title={`Order ${selectedOrder?.orderNumber} Details & Dispatch`}
        description="Manage fulfillment, fast shipping dispatch, and order status"
        onSubmit={handleUpdateStatus}
        submitLabel="Save & Update Customer"
        isLoading={isUpdating}
      >
        {selectedOrder && (
          <div className="space-y-5 text-sm">
            {/* Customer & Shipping Summary Header */}
            <div className="rounded-xl border border-border/60 bg-secondary/30 p-4 space-y-3">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-border/40">
                <div>
                  <h4 className="font-semibold text-foreground text-base">{selectedOrder.customerName}</h4>
                  <div className="flex items-center gap-3 text-xs text-muted-foreground mt-0.5">
                    <span className="flex items-center gap-1">
                      <Phone className="h-3.5 w-3.5" /> {selectedOrder.customerPhone}
                    </span>
                    {selectedOrder.customerEmail && <span>{selectedOrder.customerEmail}</span>}
                    <span>Placed {formatDate(selectedOrder.createdAt)}</span>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="h-8 text-xs bg-background"
                    onClick={() => copyShippingSlip(selectedOrder)}
                  >
                    <FileText className="h-3.5 w-3.5 mr-1.5 text-primary" />
                    Copy Shipping Slip
                  </Button>
                  {selectedOrder.customerPhone && (
                    <a
                      href={`https://wa.me/${selectedOrder.customerPhone.replace(/\D+/g, '')}`}
                      target="_blank"
                      rel="noreferrer"
                    >
                      <Button type="button" variant="outline" size="sm" className="h-8 text-xs bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20 border-emerald-500/30">
                        <MessageSquare className="h-3.5 w-3.5 mr-1" />
                        WhatsApp
                      </Button>
                    </a>
                  )}
                </div>
              </div>

              {/* Delivery / Shipping Address Box */}
              <div>
                <div className="flex items-center justify-between text-xs font-medium text-muted-foreground mb-1">
                  <span className="flex items-center gap-1">
                    <MapPin className="h-3.5 w-3.5 text-emerald-600" />
                    Fulfillment & Shipping Destination:
                  </span>
                  {selectedOrder.deliveryAddress && (
                    <button
                      type="button"
                      onClick={() => copyToClipboard(selectedOrder.deliveryAddress!, 'Shipping Address')}
                      className="text-primary hover:underline inline-flex items-center gap-1"
                    >
                      <Copy className="h-3 w-3" /> Copy Address
                    </button>
                  )}
                </div>
                <Input
                  value={deliveryAddress}
                  onChange={(e) => setDeliveryAddress(e.target.value)}
                  placeholder="Street address, building, city, zip code..."
                  className="bg-background text-foreground text-xs"
                />
                {selectedOrder.deliveryAddress && (
                  <div className="mt-1.5">
                    <a
                      href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(selectedOrder.deliveryAddress)}`}
                      target="_blank"
                      rel="noreferrer"
                      className="text-xs text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1"
                    >
                      <ExternalLink className="h-3 w-3" /> Open in Google Maps for Rider Directions
                    </a>
                  </div>
                )}
              </div>
            </div>

            {/* Order Line Items */}
            <div>
              <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Order Items & Breakdown</p>
              <div className="space-y-2 rounded-lg border border-border/50 bg-background p-3">
                {selectedOrder.products.map((item) => (
                  <div key={item.id} className="flex items-center justify-between text-sm">
                    <span className="text-foreground">
                      <strong className="text-primary">{item.quantity}x</strong> {item.name}
                    </span>
                    <span className="font-medium text-foreground">
                      {formatCurrency(item.price * item.quantity)}
                    </span>
                  </div>
                ))}
                <div className="border-t border-border/40 pt-2 mt-2 space-y-1 text-xs">
                  {selectedOrder.subtotal !== undefined && (
                    <div className="flex justify-between text-muted-foreground">
                      <span>Subtotal</span>
                      <span>{formatCurrency(selectedOrder.subtotal)}</span>
                    </div>
                  )}
                  {selectedOrder.deliveryFee ? (
                    <div className="flex justify-between text-muted-foreground">
                      <span>Delivery Fee</span>
                      <span>{formatCurrency(selectedOrder.deliveryFee)}</span>
                    </div>
                  ) : null}
                  {(selectedOrder.taxTotal ?? 0) > 0 && (
                    <div className="flex justify-between text-muted-foreground">
                      <span>Tax Total</span>
                      <span>{formatCurrency(selectedOrder.taxTotal ?? 0)}</span>
                    </div>
                  )}
                  <div className="flex justify-between text-sm font-bold text-foreground pt-1 border-t border-border/30">
                    <span>Total Amount</span>
                    <span className="text-primary">{formatCurrency(selectedOrder.total)}</span>
                  </div>
                </div>
              </div>
            </div>

            {/* Fast Shipping Dispatch Form */}
            <div className="space-y-4 rounded-xl border border-primary/20 bg-primary/5 p-4">
              <div className="flex items-center justify-between">
                <h4 className="font-semibold text-foreground flex items-center gap-2">
                  <Truck className="h-4 w-4 text-primary" />
                  Fast Shipping & Courier Info
                </h4>
                <Badge variant="outline" className="text-xs">
                  Sends WhatsApp notification automatically
                </Badge>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="text-xs font-medium text-foreground mb-1 block">Courier / Carrier Name</label>
                  <Input
                    value={courierName}
                    onChange={(e) => setCourierName(e.target.value)}
                    placeholder="e.g. DHL, FedEx, Sendy, Bodaboda, Rider..."
                    className="bg-background text-xs"
                  />
                </div>
                <div>
                  <label className="text-xs font-medium text-foreground mb-1 block">Tracking Number / Reference</label>
                  <Input
                    value={trackingNumber}
                    onChange={(e) => setTrackingNumber(e.target.value)}
                    placeholder="e.g. TRK-9840219"
                    className="bg-background text-xs"
                  />
                </div>
              </div>

              {/* Status Selectors */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <SelectField
                  label="Order Status"
                  name="status"
                  value={newStatus}
                  onChange={setNewStatus}
                  options={[
                    { value: 'pending', label: 'Pending' },
                    { value: 'confirmed', label: 'Confirmed (Preparing)' },
                    { value: 'shipped', label: 'Shipped (Out for Delivery)' },
                    { value: 'delivered', label: 'Delivered (Completed)' },
                    { value: 'cancelled', label: 'Cancelled' },
                  ]}
                  description="Setting to 'Shipped' attaches courier info & notifies customer"
                />

                <SelectField
                  label="Payment Status"
                  name="paymentStatus"
                  value={newPaymentStatus}
                  onChange={(v) => setNewPaymentStatus(v as Order['paymentStatus'])}
                  options={[
                    { value: 'pending', label: 'Pending Payment' },
                    { value: 'paid', label: 'Paid' },
                    { value: 'refunded', label: 'Refunded' },
                  ]}
                  description="Mark as paid when cash or online payment is verified"
                />
              </div>
            </div>
          </div>
        )}
      </FormModal>
    </div>
  )
}
