'use client'

import { useState, useCallback } from 'react'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { StatsCard, StatsGrid } from '@/components/shared/stats-card'
import { DataTable, type Column, type Filter } from '@/components/shared/data-table'
import { StatusBadge } from '@/components/shared/status-badge'
import { FormModal, ConfirmModal } from '@/components/shared/modal'
import { SelectField } from '@/components/shared/form-field'
import { useOrders } from '@/lib/api-hooks'
import { updateOrderStatus } from '@/lib/api-actions'
import type { Order } from '@/lib/mock-data'
import {
  Search,
  ShoppingCart,
  Clock,
  CheckCircle,
  Truck,
  Package,
  X,
  Eye,
  MessageSquare,
} from 'lucide-react'
import { useSWRConfig } from 'swr'

export default function OrdersPage() {
  const { mutate } = useSWRConfig()
  const [searchQuery, setSearchQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [page, setPage] = useState(1)
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null)
  const [isUpdating, setIsUpdating] = useState(false)
  const [newStatus, setNewStatus] = useState('')

  // API: GET /api/company/orders (useOrders)
  const { data, isLoading, error } = useOrders({
    status: statusFilter,
    search: searchQuery,
    page,
    limit: 10,
  })

  // Calculate stats from data
  const stats = {
    total: data?.total || 0,
    pending: data?.orders?.filter((o) => o.status === 'pending').length || 0,
    processing: data?.orders?.filter((o) => o.status === 'confirmed' || o.status === 'shipped').length || 0,
    completed: data?.orders?.filter((o) => o.status === 'delivered').length || 0,
  }

  // Format currency
  const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'EGP',
      minimumFractionDigits: 0,
    }).format(value)
  }

  // Format date
  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    })
  }

  // Handle status update — api-actions.updateOrderStatus → PATCH /api/company/orders/:orderId
  const handleUpdateStatus = useCallback(async () => {
    if (!selectedOrder || !newStatus) return

    setIsUpdating(true)
    try {
      const result = await updateOrderStatus(selectedOrder.id, newStatus as Order['status'])
      if (result.success) {
        // Revalidate orders data
        mutate(['orders', { status: statusFilter, search: searchQuery, page, limit: 10 }])
        setSelectedOrder(null)
        setNewStatus('')
      }
    } catch (error) {
      console.error('Failed to update order status:', error)
    } finally {
      setIsUpdating(false)
    }
  }, [selectedOrder, newStatus, mutate, statusFilter, searchQuery, page])

  // Table columns definition
  const columns: Column<Order>[] = [
    {
      key: 'orderNumber',
      header: 'Order ID',
      cell: (order) => (
        <span className="font-medium text-foreground">{order.orderNumber}</span>
      ),
    },
    {
      key: 'customer',
      header: 'Customer',
      cell: (order) => (
        <div>
          <div className="font-medium text-foreground">{order.customerName}</div>
          <div className="text-sm text-muted-foreground">{order.customerPhone}</div>
        </div>
      ),
    },
    {
      key: 'items',
      header: 'Items',
      cell: (order) => (
        <div className="text-sm text-muted-foreground">
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
      header: 'Status',
      cell: (order) => <StatusBadge status={order.status} />,
    },
    {
      key: 'paymentStatus',
      header: 'Payment',
      cell: (order) => <StatusBadge status={order.paymentStatus} />,
    },
    {
      key: 'date',
      header: 'Date',
      cell: (order) => (
        <span className="text-muted-foreground">{formatDate(order.createdAt)}</span>
      ),
    },
    {
      key: 'actions',
      header: '',
      cell: (order) => (
        <Button variant="outline" size="sm" onClick={() => {
          setSelectedOrder(order)
          setNewStatus(order.status)
        }}>
          <Eye className="h-4 w-4 mr-1" />
          View
        </Button>
      ),
    },
  ]

  // Filter options
  const filters: Filter[] = [
    {
      key: 'status',
      label: 'Status',
      options: [
        { value: 'all', label: 'All Status' },
        { value: 'pending', label: 'Pending' },
        { value: 'confirmed', label: 'Confirmed' },
        { value: 'shipped', label: 'Shipped' },
        { value: 'delivered', label: 'Delivered' },
        { value: 'cancelled', label: 'Cancelled' },
      ],
    },
  ]

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div>
        <h1 className="text-2xl font-bold text-foreground">Orders</h1>
        <p className="text-muted-foreground">Manage and track customer orders</p>
      </div>

      {/* Stats Grid - API Ready */}
      <StatsGrid columns={4}>
        <StatsCard
          title="Total Orders"
          value={stats.total}
          icon={ShoppingCart}
          isLoading={isLoading}
          formatter={(v) => v.toLocaleString()}
        />
        <StatsCard
          title="Pending"
          value={stats.pending}
          icon={Clock}
          isLoading={isLoading}
          formatter={(v) => v.toLocaleString()}
        />
        <StatsCard
          title="Processing"
          value={stats.processing}
          icon={Package}
          isLoading={isLoading}
          formatter={(v) => v.toLocaleString()}
        />
        <StatsCard
          title="Completed"
          value={stats.completed}
          icon={CheckCircle}
          isLoading={isLoading}
          formatter={(v) => v.toLocaleString()}
        />
      </StatsGrid>

      {/* Orders Table - API Ready */}
      <Card className="bg-card border-border/50">
        <CardHeader>
          <CardTitle className="text-base font-medium">All Orders</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable
            data={data?.orders}
            columns={columns}
            isLoading={isLoading}
            error={error}
            searchPlaceholder="Search orders..."
            onSearch={setSearchQuery}
            filters={filters}
            filterValues={{ status: statusFilter }}
            onFilterChange={(key, value) => {
              if (key === 'status') setStatusFilter(value)
              setPage(1) // Reset to first page on filter change
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
            emptyMessage="No orders found"
            emptyDescription="Orders will appear here when customers place them"
          />
        </CardContent>
      </Card>

      {/* Order Details Modal */}
      <FormModal
        open={!!selectedOrder}
        onOpenChange={(open) => {
          if (!open) {
            setSelectedOrder(null)
            setNewStatus('')
          }
        }}
        title={`Order ${selectedOrder?.orderNumber}`}
        description="Order details and status management"
        onSubmit={handleUpdateStatus}
        submitLabel="Update Status"
        isLoading={isUpdating}
        isValid={newStatus !== selectedOrder?.status}
      >
        {selectedOrder && (
          <div className="space-y-4">
            {/* Customer Info */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-muted-foreground">Customer</p>
                <p className="font-medium text-foreground">{selectedOrder.customerName}</p>
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Phone</p>
                <p className="font-medium text-foreground">{selectedOrder.customerPhone}</p>
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Order Date</p>
                <p className="font-medium text-foreground">
                  {formatDate(selectedOrder.createdAt)}
                </p>
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Payment</p>
                <StatusBadge status={selectedOrder.paymentStatus} />
              </div>
            </div>

            {/* Order Items */}
            <div>
              <p className="mb-2 text-sm text-muted-foreground">Items</p>
              <div className="space-y-2 rounded-lg border border-border/50 bg-secondary/30 p-3">
                {selectedOrder.products.map((item) => (
                  <div
                    key={item.id}
                    className="flex items-center justify-between text-sm"
                  >
                    <span className="text-foreground">
                      {item.quantity}x {item.name}
                    </span>
                    <span className="font-medium text-foreground">
                      {formatCurrency(item.price * item.quantity)}
                    </span>
                  </div>
                ))}
              </div>
            </div>

            {/* Total */}
            <div className="flex items-center justify-between border-t border-border/50 pt-4">
              <span className="font-medium text-foreground">Total Amount</span>
              <span className="text-xl font-bold text-primary">
                {formatCurrency(selectedOrder.total)}
              </span>
            </div>

            {/* Status Update */}
            <SelectField
              label="Order Status"
              name="status"
              value={newStatus}
              onChange={setNewStatus}
              options={[
                { value: 'pending', label: 'Pending' },
                { value: 'confirmed', label: 'Confirmed' },
                { value: 'shipped', label: 'Shipped' },
                { value: 'delivered', label: 'Delivered' },
                { value: 'cancelled', label: 'Cancelled' },
              ]}
              description="Update the order status to notify the customer"
            />

            {/* Quick Actions */}
            <div className="flex gap-2 pt-2">
              <Button variant="outline" className="flex-1 border-border/50">
                <MessageSquare className="mr-2 h-4 w-4" />
                Send Message
              </Button>
            </div>
          </div>
        )}
      </FormModal>
    </div>
  )
}
