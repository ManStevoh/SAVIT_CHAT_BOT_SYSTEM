'use client'

import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'

type StatusType =
  | 'active'
  | 'inactive'
  | 'pending'
  | 'confirmed'
  | 'shipped'
  | 'delivered'
  | 'cancelled'
  | 'paid'
  | 'refunded'
  | 'resolved'
  | 'trial'
  | 'expired'
  | 'suspended'
  | 'success'
  | 'warning'
  | 'error'
  | 'info'

interface StatusBadgeProps {
  status: StatusType | string
  className?: string
}

const statusStyles: Record<string, string> = {
  // General
  active: 'bg-green-500/20 text-green-500 border-green-500/30',
  inactive: 'bg-gray-500/20 text-gray-400 border-gray-500/30',
  pending: 'bg-yellow-500/20 text-yellow-500 border-yellow-500/30',
  suspended: 'bg-red-500/20 text-red-500 border-red-500/30',
  
  // Order status
  confirmed: 'bg-blue-500/20 text-blue-500 border-blue-500/30',
  shipped: 'bg-purple-500/20 text-purple-500 border-purple-500/30',
  delivered: 'bg-green-500/20 text-green-500 border-green-500/30',
  cancelled: 'bg-red-500/20 text-red-500 border-red-500/30',
  
  // Payment status
  paid: 'bg-green-500/20 text-green-500 border-green-500/30',
  refunded: 'bg-orange-500/20 text-orange-500 border-orange-500/30',
  
  // Chat status
  resolved: 'bg-green-500/20 text-green-500 border-green-500/30',
  
  // Subscription status
  trial: 'bg-blue-500/20 text-blue-500 border-blue-500/30',
  expired: 'bg-red-500/20 text-red-500 border-red-500/30',
  
  // Log types
  success: 'bg-green-500/20 text-green-500 border-green-500/30',
  warning: 'bg-yellow-500/20 text-yellow-500 border-yellow-500/30',
  error: 'bg-red-500/20 text-red-500 border-red-500/30',
  info: 'bg-blue-500/20 text-blue-500 border-blue-500/30',
}

const statusLabels: Record<string, string> = {
  active: 'Active',
  inactive: 'Inactive',
  pending: 'Pending',
  suspended: 'Suspended',
  confirmed: 'Confirmed',
  shipped: 'Shipped',
  delivered: 'Delivered',
  cancelled: 'Cancelled',
  paid: 'Paid',
  refunded: 'Refunded',
  resolved: 'Resolved',
  trial: 'Trial',
  expired: 'Expired',
  success: 'Success',
  warning: 'Warning',
  error: 'Error',
  info: 'Info',
}

export function StatusBadge({ status, className }: StatusBadgeProps) {
  const normalizedStatus = status.toLowerCase()
  const styles = statusStyles[normalizedStatus] || statusStyles.inactive
  const label = statusLabels[normalizedStatus] || status

  return (
    <Badge
      variant="outline"
      className={cn(
        'font-medium text-xs capitalize border',
        styles,
        className
      )}
    >
      {label}
    </Badge>
  )
}

interface PlanBadgeProps {
  plan: 'starter' | 'professional' | 'enterprise' | string
  className?: string
}

const planStyles: Record<string, string> = {
  starter: 'bg-gray-500/20 text-gray-400 border-gray-500/30',
  professional: 'bg-blue-500/20 text-blue-500 border-blue-500/30',
  enterprise: 'bg-purple-500/20 text-purple-500 border-purple-500/30',
}

export function PlanBadge({ plan, className }: PlanBadgeProps) {
  const normalizedPlan = plan.toLowerCase()
  const styles = planStyles[normalizedPlan] || planStyles.starter

  return (
    <Badge
      variant="outline"
      className={cn(
        'font-medium text-xs capitalize border',
        styles,
        className
      )}
    >
      {plan}
    </Badge>
  )
}

interface RoleBadgeProps {
  role: 'admin' | 'company_owner' | 'company_user' | string
  className?: string
}

const roleStyles: Record<string, string> = {
  admin: 'bg-red-500/20 text-red-500 border-red-500/30',
  company_owner: 'bg-blue-500/20 text-blue-500 border-blue-500/30',
  company_user: 'bg-gray-500/20 text-gray-400 border-gray-500/30',
}

const roleLabels: Record<string, string> = {
  admin: 'Admin',
  company_owner: 'Owner',
  company_user: 'User',
}

export function RoleBadge({ role, className }: RoleBadgeProps) {
  const normalizedRole = role.toLowerCase()
  const styles = roleStyles[normalizedRole] || roleStyles.company_user
  const label = roleLabels[normalizedRole] || role

  return (
    <Badge
      variant="outline"
      className={cn(
        'font-medium text-xs capitalize border',
        styles,
        className
      )}
    >
      {label}
    </Badge>
  )
}
