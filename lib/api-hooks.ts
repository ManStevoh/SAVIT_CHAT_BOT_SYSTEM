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

/** Landing page testimonial (public API) */
export interface LandingTestimonial {
  id: string
  name: string
  role: string
  content: string
  rating: number
}

/** Landing page data (testimonials + trusted companies). GET /api/landing */
/** Landing FAQ item (public API) */
export interface LandingFaqItem {
  id: string
  question: string
  answer: string
}

export interface LandingData {
  testimonials: LandingTestimonial[]
  trustedCompanies: string[]
  faqs: LandingFaqItem[]
}

/**
 * Fetch landing page data (testimonials, trusted companies) for public landing page
 * API Endpoint: GET /api/landing
 */
export function useLanding() {
  return useSWR<LandingData>(
    'landing',
    async () => {
      if (!useMockApi()) {
        return apiRequest<LandingData>('/api/landing')
      }
      await delay(300)
      return {
        testimonials: [],
        trustedCompanies: ['FoodHub', 'ShopEase', 'TechStore', 'FashionCo', 'QuickBite', 'HomeGoods'],
        faqs: [],
      }
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
export function useChats(filters?: { status?: string; search?: string; limit?: number }) {
  return useSWR<Chat[]>(
    ['chats', filters],
    async () => {
      if (!useMockApi()) {
        return apiRequest<Chat[]>(buildPath('/api/company/chats', filters as Record<string, string | number | undefined>))
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
      if (filters?.limit) {
        data = data.slice(0, filters.limit)
      }
      return data
    },
    { revalidateOnFocus: false }
  )
}

/** Customer stats from GET /api/company/customers/stats */
export interface CustomerStats {
  totalCustomers: number
  newThisMonth: number
  activeCustomers: number
  avgOrdersPerCustomer: number
}

/**
 * Fetch customer stats for the current company
 * API Endpoint: GET /api/company/customers/stats
 */
export function useCustomerStats() {
  return useSWR<CustomerStats>(
    'customer-stats',
    async () => {
      if (!useMockApi()) {
        return apiRequest<CustomerStats>('/api/company/customers/stats')
      }
      await delay(400)
      return {
        totalCustomers: 0,
        newThisMonth: 0,
        activeCustomers: 0,
        avgOrdersPerCustomer: 0,
      }
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
/**
 * Single order for deep links (e.g. notifications). API: GET /api/company/orders/:id
 */
export function useOrder(orderId: string | null) {
  return useSWR<{ order: Order }>(
    orderId ? ['order', orderId] : null,
    async () => {
      if (!useMockApi()) {
        return apiRequest<{ order: Order }>(`/api/company/orders/${orderId}`)
      }
      await delay(200)
      const o = mockOrders.find((x) => x.id === orderId)
      if (!o) {
        throw new Error('Order not found')
      }
      return { order: o }
    },
    { revalidateOnFocus: false }
  )
}

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
  fallbackMessage?: string
  awayMessage?: string
  timezone?: string
  workingHours?: Record<string, string>
  learnFromConversations?: boolean
  autoReplyEnabled?: boolean
  notificationsEnabled?: boolean
  ordersAcceptMpesa?: boolean
  ordersAcceptStripe?: boolean
  ordersCollectPaymentEnabled?: boolean
  orderPaymentManualInstructions?: string
  orderPaymentMpesaConfigured?: boolean
  orderPaymentStripeConfigured?: boolean
  /** Masked passkey/consumer_secret from GET /api/company/settings */
  orderPaymentMpesaConfig?: {
    type?: 'paybill' | 'till'
    shortcode?: string
    passkey?: string
    consumer_key?: string | null
    consumer_secret?: string | null
    env?: 'sandbox' | 'production'
  } | null
  /** Masked secret from GET /api/company/settings */
  orderPaymentStripeConfig?: { secret?: string; currency?: string } | null
  /** ISO 4217 — catalog & chat price display (e.g. USD, KES) */
  displayCurrency?: string
}

/**
 * Fetch company settings for the current user
 * API Endpoint: GET /api/company/settings
 * When real API is used, backend data is returned; mock is used only when useMockApi() is true.
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
        companyName: 'Demo Company',
        email: 'contact@demo.com',
        phone: '',
        address: '',
        displayCurrency: 'USD',
      }
    },
    { revalidateOnFocus: false }
  )
}

/** Team member shape — API: GET /api/company/team */
export interface TeamMember {
  id: string
  name: string
  email: string
  role: string
  status: string
}

/**
 * Fetch team members for the current company
 * API Endpoint: GET /api/company/team
 * When mock: returns placeholder list; when real API is used, backend data is shown.
 */
export function useCompanyTeam() {
  return useSWR<TeamMember[]>(
    'company-team',
    async () => {
      if (!useMockApi()) {
        return apiRequest<TeamMember[]>('/api/company/team')
      }
      await delay(400)
      return [
        { id: '1', name: 'Team Member 1', email: 'user1@company.com', role: 'Admin', status: 'active' },
        { id: '2', name: 'Team Member 2', email: 'user2@company.com', role: 'Agent', status: 'active' },
        { id: '3', name: 'Team Member 3', email: 'user3@company.com', role: 'Agent', status: 'active' },
      ]
    },
    { revalidateOnFocus: false }
  )
}

/** Notification item — API: GET /api/company/notifications */
export interface NotificationItem {
  id: string
  title: string
  body?: string
  type?: 'order' | 'handoff' | 'report' | 'info'
  read?: boolean
  createdAt?: string
  /** Deep link to Orders detail when present */
  orderId?: string | null
  /** Deep link to Chats when present */
  chatId?: string | null
}

export interface NotificationsData {
  items: NotificationItem[]
  unreadCount: number
}

/**
 * Fetch notifications for the current user (dashboard navbar).
 * API: GET /api/company/notifications → { items, unreadCount }.
 * On error, yields an empty list.
 */
export function useNotifications() {
  return useSWR<NotificationsData>(
    'company-notifications',
    async () => {
      if (!useMockApi()) {
        try {
          const data = await apiRequest<NotificationsData>('/api/company/notifications')
          return { items: data?.items ?? [], unreadCount: data?.unreadCount ?? 0 }
        } catch {
          return { items: [], unreadCount: 0 }
        }
      }
      await delay(200)
      return { items: [], unreadCount: 0 }
    },
    { revalidateOnFocus: true }
  )
}

/** Usage item shape — API: GET /api/company/subscription/usage */
export interface UsageItem {
  name: string
  used: number
  limit: number
}

/**
 * Fetch subscription usage for current billing period
 * API Endpoint: GET /api/company/subscription/usage
 */
export function useSubscriptionUsage() {
  return useSWR<{ items: UsageItem[] }>(
    'subscription-usage',
    async () => {
      if (!useMockApi()) {
        return apiRequest<{ items: UsageItem[] }>('/api/company/subscription/usage')
      }
      await delay(400)
      return {
        items: [
          { name: 'Messages', used: 3240, limit: 5000 },
          { name: 'Team members', used: 3, limit: 5 },
        ],
      }
    },
    { revalidateOnFocus: false }
  )
}

/** WhatsApp number shape — API: GET /api/company/whatsapp/numbers */
export interface WhatsAppNumber {
  id: string
  phoneNumberId: string
  displayPhoneNumber: string
  status: string
}

/**
 * Fetch WhatsApp numbers connected to the company
 * API Endpoint: GET /api/company/whatsapp/numbers
 */
export function useWhatsAppNumbers() {
  return useSWR<WhatsAppNumber[]>(
    'whatsapp-numbers',
    async () => {
      if (!useMockApi()) {
        return apiRequest<WhatsAppNumber[]>('/api/company/whatsapp/numbers')
      }
      await delay(400)
      return []
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

/** Admin testimonial (full fields) */
export interface AdminTestimonial {
  id: string
  name: string
  role: string
  content: string
  rating: number
  sortOrder: number
  isActive: boolean
  createdAt?: string
  updatedAt?: string
}

/**
 * Fetch all testimonials (admin only)
 * API Endpoint: GET /api/admin/testimonials
 */
export function useAdminTestimonials() {
  return useSWR<AdminTestimonial[]>(
    'admin-testimonials',
    async () => {
      if (!useMockApi()) {
        return apiRequest<AdminTestimonial[]>('/api/admin/testimonials')
      }
      await delay(300)
      return []
    },
    { revalidateOnFocus: false }
  )
}

/** Admin landing FAQ (full fields) */
export interface AdminLandingFaq {
  id: string
  question: string
  answer: string
  sortOrder: number
  isActive: boolean
  createdAt?: string
  updatedAt?: string
}

/**
 * Fetch all landing FAQs (admin only)
 * API Endpoint: GET /api/admin/landing-faqs
 */
export function useAdminLandingFaqs() {
  return useSWR<AdminLandingFaq[]>(
    'admin-landing-faqs',
    async () => {
      if (!useMockApi()) {
        return apiRequest<AdminLandingFaq[]>('/api/admin/landing-faqs')
      }
      await delay(300)
      return []
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
    monthlyRevenue?: number
    totalMessages: number
    totalOrders: number
    companiesChange: number
    revenueChange: number
    messagesChange: number
    usersChange?: number
    companyGrowthData?: { name: string; companies: number }[]
    messageVolumeData?: { name: string; messages: number }[]
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
          companyGrowthData?: { name: string; companies: number }[]
          messageVolumeData?: { name: string; messages: number }[]
        }>('/api/admin/overview')
      }
      await delay(800)
      const monthlyRevenue = 125000
      return {
        totalCompanies: 295,
        activeCompanies: 248,
        totalUsers: 1250,
        totalRevenue: 125000,
        monthlyRevenue,
        totalMessages: 2500000,
        totalOrders: 45000,
        companiesChange: 12.5,
        revenueChange: 18.3,
        messagesChange: 25.4,
        usersChange: 8.2,
        companyGrowthData: [
          { name: 'Jan', companies: 890 },
          { name: 'Feb', companies: 945 },
          { name: 'Mar', companies: 1020 },
          { name: 'Apr', companies: 1085 },
          { name: 'May', companies: 1140 },
          { name: 'Jun', companies: 1190 },
          { name: 'Jul', companies: 1234 },
        ],
        messageVolumeData: [
          { name: 'Mon', messages: 320000 },
          { name: 'Tue', messages: 450000 },
          { name: 'Wed', messages: 380000 },
          { name: 'Thu', messages: 520000 },
          { name: 'Fri', messages: 480000 },
          { name: 'Sat', messages: 350000 },
          { name: 'Sun', messages: 280000 },
        ],
      }
    },
    { revalidateOnFocus: false }
  )
}
