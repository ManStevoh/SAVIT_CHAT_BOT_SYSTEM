// API Hooks for data fetching
// These hooks use SWR for caching and real-time updates
// When NEXT_PUBLIC_USE_MOCK_API=false, they call the Laravel backend.

import useSWR from 'swr'
import { useMockApi, apiRequest } from './api-client'
import {
  mockChats,
  mockMessages,
  mockOrders,
  mockCustomers,
  mockProducts,
  mockFAQs,
  mockSubscriptions,
  mockCompanies,
  mockUsers,
  mockSystemLogs,
  mockAnalytics,
  mockRevenueData,
  mockAIUsage,
  type Chat,
  type Message,
  type Order,
  type Customer,
  type Product,
  type FAQ,
  type Subscription,
  type Plan,
  type PaymentGateway,
  type Company,
  type User,
  type SystemLog,
  type AnalyticsData,
  type RevenueData,
  type AIUsageData,
} from './mock-data'

const delay = (ms: number) => new Promise(resolve => setTimeout(resolve, ms))

// ============================================
// PUBLIC (no auth)
// ============================================

/**
 * Fetch plans for public pricing page
 * API Endpoint: GET /api/plans
 */
export function usePlans() {
  return useSWR<Plan[]>(
    'plans',
    async () => {
      if (!useMockApi()) {
        return apiRequest<Plan[]>(('/api/plans'))
      }
      await delay(400)
      return [
        { id: '1', name: 'Starter', slug: 'starter', price: '$29', description: 'Perfect for small businesses just getting started', features: ['1 WhatsApp number', '1,000 messages/month', 'Basic AI chatbot', 'Order management', 'Email support'], popular: false, cta: 'Start Free Trial', checkoutAvailable: true },
        { id: '2', name: 'Growth', slug: 'professional', price: '$99', description: 'For growing businesses with higher volume', features: ['3 WhatsApp numbers', '10,000 messages/month', 'Advanced AI with GPT-4', 'Multi-agent inbox', 'Analytics dashboard', 'Priority support', 'API access'], popular: true, cta: 'Start Free Trial', checkoutAvailable: true },
        { id: '3', name: 'Enterprise', slug: 'enterprise', price: 'Custom', description: 'For large organizations with custom needs', features: ['Unlimited WhatsApp numbers', 'Unlimited messages', 'Custom AI training', 'Dedicated account manager', 'Custom integrations', 'SLA guarantee', 'On-premise option'], popular: false, cta: 'Contact Sales', checkoutAvailable: false },
      ]
    },
    { revalidateOnFocus: false }
  )
}

/** Build path with optional query params (excludes undefined and empty string). */
function buildPath(
  base: string,
  params?: Record<string, string | number | undefined>
): string {
  if (!params) return base
  const sp = new URLSearchParams()
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== '') sp.set(k, String(v))
  })
  const q = sp.toString()
  return q ? `${base}?${q}` : base
}

// ============================================
// COMPANY DASHBOARD HOOKS
// ============================================

/**
 * Fetch all chats for the current company
 * API Endpoint: GET /api/company/chats
 */
export function useChats(filters?: { status?: string; search?: string }) {
  return useSWR<Chat[]>(
    ['chats', filters],
    async () => {
      if (!useMockApi()) {
        return apiRequest<Chat[]>(buildPath('/api/company/chats', filters as Record<string, string | undefined>))
      }
      await delay(800)
      let data = [...mockChats]
      if (filters?.status && filters.status !== 'all') {
        data = data.filter(chat => chat.status === filters.status)
      }
      if (filters?.search) {
        data = data.filter(chat =>
          chat.customerName.toLowerCase().includes(filters.search!.toLowerCase())
        )
      }
      return data
    },
    { revalidateOnFocus: false }
  )
}

/**
 * Fetch messages for a specific chat
 * API Endpoint: GET /api/company/chats/:chatId/messages
 */
export function useMessages(chatId: string | null) {
  return useSWR<Message[]>(
    chatId ? ['messages', chatId] : null,
    async () => {
      if (!useMockApi() && chatId) {
        return apiRequest<Message[]>(`/api/company/chats/${chatId}/messages`)
      }
      await delay(500)
      return mockMessages.filter(m => m.chatId === chatId)
    },
    { revalidateOnFocus: false }
  )
}

/**
 * Fetch all orders for the current company
 * API Endpoint: GET /api/company/orders
 */
export function useOrders(filters?: { status?: string; search?: string; page?: number; limit?: number }) {
  return useSWR<{ orders: Order[]; total: number; page: number; totalPages: number }>(
    ['orders', filters],
    async () => {
      if (!useMockApi()) {
        return apiRequest<{ orders: Order[]; total: number; page: number; totalPages: number }>(
          buildPath('/api/company/orders', filters as Record<string, string | number | undefined>)
        )
      }
      await delay(800)
      let data = [...mockOrders]
      if (filters?.status && filters.status !== 'all') {
        data = data.filter(order => order.status === filters.status)
      }
      if (filters?.search) {
        data = data.filter(order =>
          order.orderNumber.toLowerCase().includes(filters.search!.toLowerCase()) ||
          order.customerName.toLowerCase().includes(filters.search!.toLowerCase())
        )
      }
      const page = filters?.page || 1
      const limit = filters?.limit || 10
      const total = data.length
      const totalPages = Math.ceil(total / limit)
      const start = (page - 1) * limit
      const paginatedData = data.slice(start, start + limit)
      return { orders: paginatedData, total, page, totalPages }
    },
    { revalidateOnFocus: false }
  )
}

/**
 * Fetch all customers for the current company
 * API Endpoint: GET /api/company/customers
 */
export function useCustomers(filters?: { search?: string; page?: number; limit?: number }) {
  return useSWR<{ customers: Customer[]; total: number; page: number; totalPages: number }>(
    ['customers', filters],
    async () => {
      if (!useMockApi()) {
        return apiRequest<{ customers: Customer[]; total: number; page: number; totalPages: number }>(
          buildPath('/api/company/customers', filters as Record<string, string | number | undefined>)
        )
      }
      await delay(800)
      let data = [...mockCustomers]
      if (filters?.search) {
        data = data.filter(customer =>
          customer.name.toLowerCase().includes(filters.search!.toLowerCase()) ||
          customer.phone.includes(filters.search!)
        )
      }
      const page = filters?.page || 1
      const limit = filters?.limit || 10
      const total = data.length
      const totalPages = Math.ceil(total / limit)
      const start = (page - 1) * limit
      const paginatedData = data.slice(start, start + limit)
      return { customers: paginatedData, total, page, totalPages }
    },
    { revalidateOnFocus: false }
  )
}

/**
 * Fetch all products for the current company
 * API Endpoint: GET /api/company/products
 */
export function useProducts(filters?: { category?: string; status?: string; search?: string }) {
  return useSWR<Product[]>(
    ['products', filters],
    async () => {
      if (!useMockApi()) {
        return apiRequest<Product[]>(
          buildPath('/api/company/products', filters as Record<string, string | undefined>)
        )
      }
      await delay(800)
      let data = [...mockProducts]
      if (filters?.category && filters.category !== 'all') {
        data = data.filter(product => product.category === filters.category)
      }
      if (filters?.status && filters.status !== 'all') {
        data = data.filter(product => product.status === filters.status)
      }
      if (filters?.search) {
        data = data.filter(product =>
          product.name.toLowerCase().includes(filters.search!.toLowerCase())
        )
      }
      return data
    },
    { revalidateOnFocus: false }
  )
}

/**
 * Fetch all FAQs for the current company
 * API Endpoint: GET /api/company/faqs
 */
export function useFAQs(filters?: { category?: string; search?: string }) {
  return useSWR<FAQ[]>(
    ['faqs', filters],
    async () => {
      if (!useMockApi()) {
        return apiRequest<FAQ[]>(
          buildPath('/api/company/faqs', filters as Record<string, string | undefined>)
        )
      }
      await delay(800)
      let data = [...mockFAQs]
      if (filters?.category && filters.category !== 'all') {
        data = data.filter(faq => faq.category === filters.category)
      }
      if (filters?.search) {
        data = data.filter(faq =>
          faq.question.toLowerCase().includes(filters.search!.toLowerCase())
        )
      }
      return data
    },
    { revalidateOnFocus: false }
  )
}

/**
 * Fetch analytics data for the current company
 * API Endpoint: GET /api/company/analytics
 */
export function useAnalytics(period?: string) {
  return useSWR<AnalyticsData>(
    ['analytics', period],
    async () => {
      if (!useMockApi()) {
        return apiRequest<AnalyticsData>(buildPath('/api/company/analytics', { period }))
      }
      await delay(1000)
      return mockAnalytics
    },
    { revalidateOnFocus: false }
  )
}

/**
 * Fetch current subscription for the company
 * API Endpoint: GET /api/company/subscription
 */
export function useSubscription() {
  return useSWR<Subscription>(
    'subscription',
    async () => {
      if (!useMockApi()) {
        return apiRequest<Subscription>('/api/company/subscription')
      }
      await delay(600)
      return mockSubscriptions[0]
    },
    { revalidateOnFocus: false }
  )
}

/** Billing invoice shape — API: GET /api/company/subscription/invoices */
export interface BillingInvoice {
  id: string
  date: string
  amount: string
  status: string
  /** Stripe hosted invoice PDF URL (optional) */
  invoicePdf?: string | null
}

/**
 * Fetch billing invoices for the company
 * API Endpoint: GET /api/company/subscription/invoices
 */
export function useSubscriptionInvoices() {
  return useSWR<BillingInvoice[]>(
    'subscription-invoices',
    async () => {
      if (!useMockApi()) {
        return apiRequest<BillingInvoice[]>('/api/company/subscription/invoices')
      }
      await delay(500)
      return [
        { id: 'INV-001', date: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }), amount: '$99.00', status: 'paid' },
        { id: 'INV-002', date: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }), amount: '$99.00', status: 'paid' },
      ]
    },
    { revalidateOnFocus: false }
  )
}

/** Company settings shape — API: GET /api/company/settings */
export interface CompanySettings {
  companyName?: string
  email?: string
  phone?: string
  address?: string
  logo?: string
  whatsappNumber?: string
  aiGreeting?: string
  aiTone?: string
  autoReplyEnabled?: boolean
  notificationsEnabled?: boolean
}

/**
 * Fetch company settings for the current user
 * API Endpoint: GET /api/company/settings
 */
export function useCompanySettings() {
  return useSWR<CompanySettings>(
    'company-settings',
    async () => {
      if (!useMockApi()) {
        return apiRequest<CompanySettings>('/api/company/settings')
      }
      await delay(400)
      return {
        companyName: 'QuickBite Restaurant',
        email: 'contact@quickbite.com',
        phone: '+1 555-0100',
        address: '123 Main Street, New York, NY 10001',
      }
    },
    { revalidateOnFocus: false }
  )
}

// ============================================
// SUPER ADMIN DASHBOARD HOOKS
// ============================================

/**
 * Fetch all companies (admin only)
 * API Endpoint: GET /api/admin/companies
 */
export function useAdminCompanies(filters?: { status?: string; plan?: string; search?: string }) {
  return useSWR<Company[]>(
    ['admin-companies', filters],
    async () => {
      if (!useMockApi()) {
        return apiRequest<Company[]>(
          buildPath('/api/admin/companies', filters as Record<string, string | undefined>)
        )
      }
      await delay(800)
      let data = [...mockCompanies]
      if (filters?.status && filters.status !== 'all') {
        data = data.filter(company => company.status === filters.status)
      }
      if (filters?.plan && filters.plan !== 'all') {
        data = data.filter(company => company.plan === filters.plan)
      }
      if (filters?.search) {
        data = data.filter(company =>
          company.name.toLowerCase().includes(filters.search!.toLowerCase())
        )
      }
      return data
    },
    { revalidateOnFocus: false }
  )
}

/**
 * Fetch all users (admin only)
 * API Endpoint: GET /api/admin/users
 */
export function useAdminUsers(filters?: { role?: string; status?: string; search?: string }) {
  return useSWR<User[]>(
    ['admin-users', filters],
    async () => {
      if (!useMockApi()) {
        return apiRequest<User[]>(
          buildPath('/api/admin/users', filters as Record<string, string | undefined>)
        )
      }
      await delay(800)
      let data = [...mockUsers]
      if (filters?.role && filters.role !== 'all') {
        data = data.filter(user => user.role === filters.role)
      }
      if (filters?.status && filters.status !== 'all') {
        data = data.filter(user => user.status === filters.status)
      }
      if (filters?.search) {
        data = data.filter(user =>
          user.name.toLowerCase().includes(filters.search!.toLowerCase()) ||
          user.email.toLowerCase().includes(filters.search!.toLowerCase())
        )
      }
      return data
    },
    { revalidateOnFocus: false }
  )
}

/**
 * Fetch all plans (admin only)
 * API Endpoint: GET /api/admin/plans
 */
export function useAdminPlans() {
  return useSWR<Plan[]>(
    'admin-plans',
    async () => {
      if (!useMockApi()) {
        return apiRequest<Plan[]>('/api/admin/plans')
      }
      await delay(400)
      return [
        { id: '1', name: 'Starter', slug: 'starter', priceDisplay: '$29', priceAmount: 29, description: 'Perfect for small businesses just getting started', features: ['1 WhatsApp number', '1,000 messages/month', 'Basic AI chatbot', 'Order management', 'Email support'], popular: false, cta: 'Start Free Trial', sortOrder: 0, stripePriceId: null },
        { id: '2', name: 'Growth', slug: 'professional', priceDisplay: '$99', priceAmount: 99, description: 'For growing businesses with higher volume', features: ['3 WhatsApp numbers', '10,000 messages/month', 'Advanced AI with GPT-4', 'Multi-agent inbox', 'Analytics dashboard', 'Priority support', 'API access'], popular: true, cta: 'Start Free Trial', sortOrder: 1, stripePriceId: null },
        { id: '3', name: 'Enterprise', slug: 'enterprise', priceDisplay: 'Custom', priceAmount: null, description: 'For large organizations with custom needs', features: ['Unlimited WhatsApp numbers', 'Unlimited messages', 'Custom AI training', 'Dedicated account manager', 'Custom integrations', 'SLA guarantee', 'On-premise option'], popular: false, cta: 'Contact Sales', sortOrder: 2, stripePriceId: null },
      ]
    },
    { revalidateOnFocus: false }
  )
}

/**
 * Fetch all subscriptions (admin only)
 * API Endpoint: GET /api/admin/subscriptions
 */
export function useAdminSubscriptions(filters?: { plan?: string; status?: string }) {
  return useSWR<Subscription[]>(
    ['admin-subscriptions', filters],
    async () => {
      if (!useMockApi()) {
        return apiRequest<Subscription[]>(
          buildPath('/api/admin/subscriptions', filters as Record<string, string | undefined>)
        )
      }
      await delay(800)
      let data = [...mockSubscriptions]
      if (filters?.plan && filters.plan !== 'all') {
        data = data.filter(sub => sub.plan === filters.plan)
      }
      if (filters?.status && filters.status !== 'all') {
        data = data.filter(sub => sub.status === filters.status)
      }
      return data
    },
    { revalidateOnFocus: false }
  )
}

/**
 * Fetch revenue data (admin only)
 * API Endpoint: GET /api/admin/revenue
 */
export function useAdminRevenue(period?: string) {
  return useSWR<RevenueData>(
    ['admin-revenue', period],
    async () => {
      if (!useMockApi()) {
        return apiRequest<RevenueData>(buildPath('/api/admin/revenue', { period }))
      }
      await delay(1000)
      return mockRevenueData
    },
    { revalidateOnFocus: false }
  )
}

/**
 * Fetch AI usage data (admin only)
 * API Endpoint: GET /api/admin/ai-usage
 */
export function useAdminAIUsage(period?: string) {
  return useSWR<AIUsageData>(
    ['admin-ai-usage', period],
    async () => {
      if (!useMockApi()) {
        return apiRequest<AIUsageData>(buildPath('/api/admin/ai-usage', { period }))
      }
      await delay(1000)
      return mockAIUsage
    },
    { revalidateOnFocus: false }
  )
}

/**
 * Fetch payment gateways (admin only)
 * API Endpoint: GET /api/admin/payment-gateways
 */
export function useAdminPaymentGateways() {
  return useSWR<PaymentGateway[]>(
    'admin-payment-gateways',
    async () => {
      if (!useMockApi()) {
        return apiRequest<PaymentGateway[]>('/api/admin/payment-gateways')
      }
      await delay(400)
      return [
        { id: '1', slug: 'stripe', name: 'Stripe', isEnabled: false, config: { key: '', secret: '', webhook_secret: '', trial_days: 14, currency: 'usd' } },
        { id: '2', slug: 'mpesa', name: 'Lipa Na M-Pesa', isEnabled: false, config: { consumer_key: '', consumer_secret: '', shortcode: '', passkey: '', env: 'sandbox', callback_url: '' } },
      ]
    },
    { revalidateOnFocus: false }
  )
}

/**
 * Fetch system logs (admin only)
 * API Endpoint: GET /api/admin/logs
 */
export function useAdminLogs(filters?: { type?: string; source?: string }) {
  return useSWR<SystemLog[]>(
    ['admin-logs', filters],
    async () => {
      if (!useMockApi()) {
        return apiRequest<SystemLog[]>(
          buildPath('/api/admin/logs', filters as Record<string, string | undefined>)
        )
      }
      await delay(800)
      let data = [...mockSystemLogs]
      if (filters?.type && filters.type !== 'all') {
        data = data.filter(log => log.type === filters.type)
      }
      if (filters?.source && filters.source !== 'all') {
        data = data.filter(log => log.source === filters.source)
      }
      return data
    },
    { revalidateOnFocus: false }
  )
}

/**
 * Fetch platform overview stats (admin only)
 * API Endpoint: GET /api/admin/overview
 */
export function useAdminOverview() {
  return useSWR<{
    totalCompanies: number
    activeCompanies: number
    totalUsers: number
    totalRevenue: number
    totalMessages: number
    totalOrders: number
    companiesChange: number
    revenueChange: number
    messagesChange: number
  }>(
    'admin-overview',
    async () => {
      if (!useMockApi()) {
        return apiRequest<{
          totalCompanies: number
          activeCompanies: number
          totalUsers: number
          totalRevenue: number
          totalMessages: number
          totalOrders: number
          companiesChange: number
          revenueChange: number
          messagesChange: number
        }>('/api/admin/overview')
      }
      await delay(800)
      return {
        totalCompanies: 295,
        activeCompanies: 248,
        totalUsers: 1250,
        totalRevenue: 125000,
        totalMessages: 2500000,
        totalOrders: 45000,
        companiesChange: 12.5,
        revenueChange: 18.3,
        messagesChange: 25.4,
      }
    },
    { revalidateOnFocus: false }
  )
}
