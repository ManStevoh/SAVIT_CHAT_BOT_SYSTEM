// API Hooks for data fetching
// These hooks use SWR for caching and real-time updates
// When VITE_USE_MOCK_API is not 'true', they call the Laravel backend.

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
  mockGrowthAnalytics,
  mockGrowthPosts,
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
  type GrowthAnalyticsData,
  type GrowthPost,
  type GrowthInsight,
  type GrowthSocialAccount,
  type GrowthCompetitor,
  type GrowthAgentRun,
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

import type { CmsPageData, CmsGlobalData, AdminCmsPage } from '@/components/lando/types'

/**
 * Fetch CMS page content for public pages
 * API Endpoint: GET /api/cms/pages/{slug}
 */
export function useCmsPage(slug: string) {
  return useSWR<CmsPageData>(
    slug ? `cms-page-${slug}` : null,
    async () => {
      if (!useMockApi()) {
        return apiRequest<CmsPageData>(`/api/cms/pages/${slug}`)
      }
      await delay(300)
      return {
        page: { slug, title: slug, metaTitle: null, metaDescription: null },
        sections: [],
      }
    },
    { revalidateOnFocus: false }
  )
}

export function useCmsGlobal() {
  return useSWR<CmsGlobalData>(
    'cms-global',
    async () => {
      if (!useMockApi()) {
        return apiRequest<CmsGlobalData>('/api/cms/global')
      }
      await delay(200)
      return {
        page: { slug: 'global', title: 'Global' },
        sections: [],
      }
    },
    { revalidateOnFocus: false }
  )
}

export function useAdminCmsPages() {
  return useSWR<Array<{ id: string; slug: string; title: string; isPublished: boolean }>>(
    'admin-cms-pages',
    async () => {
      if (!useMockApi()) {
        return apiRequest('/api/admin/cms/pages')
      }
      await delay(200)
      return []
    },
    { revalidateOnFocus: false }
  )
}

export function useAdminCmsPage(slug: string | null) {
  return useSWR<AdminCmsPage>(
    slug ? `admin-cms-page-${slug}` : null,
    async () => {
      if (!slug) throw new Error('No slug')
      if (!useMockApi()) {
        return apiRequest<AdminCmsPage>(`/api/admin/cms/pages/${slug}`)
      }
      await delay(200)
      return { page: { id: '1', slug, title: slug, isPublished: true }, sections: [] }
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
export function useChats(filters?: { status?: string; search?: string; limit?: number; attributedOnly?: boolean; socialPostId?: string }) {
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

export function useOrders(filters?: { status?: string; search?: string; page?: number; limit?: number; attributedOnly?: boolean }) {
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
  companyId?: number
  companyName?: string
  email?: string
  phone?: string
  address?: string
  logo?: string
  whatsappNumber?: string
  aiGreeting?: string
  aiTone?: string
  aiModelMode?: 'auto' | 'platform_default' | 'specific'
  aiModelId?: string | null
  aiReplyMode?: 'ai_first' | 'balanced'
  aiCredentialMode?: 'platform' | 'company' | 'company_preferred'
  defaultReplyLanguage?: string | null
  replyInCustomerLanguage?: boolean
  effectiveAiModelMode?: string
  effectiveAiCredentialMode?: string
  aiPlanCapabilities?: {
    plan: string
    allowedModelModes: string[]
    allowByok: boolean
    allowedCredentialModes: string[]
    aiCostLimitUsd?: number | null
  }
  fallbackMessage?: string
  awayMessage?: string
  timezone?: string
  workingHours?: Record<string, string>
  learnFromConversations?: boolean
  learnFromConversationsEditable?: boolean
  aiLearningEnabled?: boolean
  autoReplyEnabled?: boolean
  agentCommerceEnabled?: boolean
  agentProactiveEnabled?: boolean
  agentVoiceReplyEnabled?: boolean
  agentMorningBriefWhatsappEnabled?: boolean
  ownerWhatsappPhone?: string | null
  consciousnessLastSensedAt?: string | null
  webWidgetToken?: string | null
  channelIngestSecret?: string | null
  channelWebhookUrls?: { email: string; instagramDm: string }
  widgetScriptUrl?: string
  agentBusinessGoals?: string[]
  agentBusinessGoalCatalog?: Record<string, string>
  businessDna?: BusinessDnaSettings
  businessDnaCustom?: boolean
  businessDnaPresets?: Record<string, BusinessDnaPreset>
  digitalTwin?: Record<string, string>
  digitalTwinCustom?: boolean
  digitalTwinFields?: Record<string, string>
  agentCouncilEnabled?: boolean
  notificationsEnabled?: boolean
  ordersAcceptMpesa?: boolean
  ordersAcceptStripe?: boolean
  ordersAcceptPaystack?: boolean
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
  /** Industry cluster for CRM templates and portfolio insights */
  industry?: 'retail' | 'restaurant' | 'services' | 'other'
  /** Days to retain attribution data (30–730); null uses platform default */
  attributionRetentionDays?: number | null
}

/** Business DNA shapes how the agent speaks (luxury vs café, etc.) */
export interface BusinessDnaSettings {
  tone?: string
  values?: string[]
  risk_tolerance?: 'low' | 'medium' | 'high'
  service_philosophy?: string
  escalation_culture?: string
  communication_style?: string
}

export interface BusinessDnaPreset extends BusinessDnaSettings {
  label?: string
  description?: string
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
  type?: 'order' | 'handoff' | 'report' | 'info' | 'growth'
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
export type UsageWarning = {
  resource: string
  level: 'critical' | 'warning' | 'info'
  message: string
  percentUsed: number
  projectedOverage?: boolean
}

export function useSubscriptionUsage() {
  return useSWR<{
    items: UsageItem[]
    growth?: { aiPostsUsed: number; aiPostsLimit: number; platformsConnected: number; platformLimit: number }
    warnings?: UsageWarning[]
    upgradeUrl?: string
  }>(
    'subscription-usage',
    async () => {
      if (!useMockApi()) {
        return apiRequest('/api/company/subscription/usage')
      }
      await delay(400)
      return {
        items: [
          { name: 'Messages', used: 3240, limit: 5000 },
          { name: 'Team members', used: 3, limit: 5 },
          { name: 'AI posts (this month)', used: 12, limit: 20 },
        ],
        warnings: [],
      }
    },
    { revalidateOnFocus: false }
  )
}

export type AdminSystemHealth = {
  queue: { pending: number; failed: number; healthy: boolean }
  alerts: string[]
}

export function useAdminSystemHealth() {
  return useSWR<AdminSystemHealth>(
    'admin-system-health',
    async () => {
      if (!useMockApi()) {
        return apiRequest<AdminSystemHealth>('/api/admin/system-health')
      }
      await delay(200)
      return { queue: { pending: 0, failed: 0, healthy: true }, alerts: [] }
    },
    { revalidateOnFocus: false, refreshInterval: 60_000 }
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

export interface AdminBlogPost {
  id: string
  title: string
  slug: string
  excerpt?: string | null
  body: string
  coverImage?: string | null
  coverImageRaw?: string | null
  metaTitle?: string | null
  metaDescription?: string | null
  ogImage?: string | null
  ogImageRaw?: string | null
  publishedAt?: string | null
  isPublished: boolean
  createdAt?: string
  updatedAt?: string
}

export function useAdminBlogPosts() {
  return useSWR<AdminBlogPost[]>(
    'admin-blog-posts',
    async () => {
      if (!useMockApi()) {
        return apiRequest<AdminBlogPost[]>('/api/admin/blog-posts')
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
 * Fetch subscription offers / coupons (admin)
 * API Endpoint: GET /api/admin/subscription-offers
 */
export function useAdminSubscriptionOffers() {
  return useSWR(
    'admin-subscription-offers',
    async () => {
      if (!useMockApi()) {
        return apiRequest<import('./api-actions').SubscriptionOffer[]>('/api/admin/subscription-offers')
      }
      await delay(300)
      return [] as import('./api-actions').SubscriptionOffer[]
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
 * Fetch AI learning / knowledge stats (admin only)
 * API Endpoint: GET /api/admin/ai-learning/stats
 */
export function useAdminAiLearning() {
  return useSWR<import('@/lib/api-actions').AdminAiLearningStats>(
    'admin-ai-learning',
    async () => {
      if (!useMockApi()) {
        const { getAdminAiLearningStats } = await import('@/lib/api-actions')
        return getAdminAiLearningStats()
      }
      await delay(400)
      return {
        config: {
          learningEnabled: true,
          retentionDays: 365,
          maxSamplesPerCompany: 200,
          piiRedactionEnabled: true,
        },
        stats: {
          totalLearningSamples: 0,
          pendingReviewSamples: 0,
          companiesWithSamples: 0,
          activeFaqs: 0,
          faqsWithEmbeddings: 0,
          embeddingCoveragePercent: 0,
          samplesBySource: {},
          topCompaniesBySamples: [],
        },
      }
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
          monthlyRevenue?: number
          totalMessages: number
          totalOrders: number
          companiesChange: number
          revenueChange: number
          messagesChange: number
          usersChange?: number
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

// ============================================
// GROWTH ENGINE
// ============================================

export function useGrowthAnalytics(period?: string) {
  return useSWR<GrowthAnalyticsData>(
    ['growth-analytics', period],
    async () => {
      if (!useMockApi()) {
        return apiRequest<GrowthAnalyticsData>(buildPath('/api/company/growth/analytics', { period }))
      }
      await delay(600)
      return mockGrowthAnalytics
    },
    { revalidateOnFocus: false }
  )
}

export function useGrowthPosts(status?: string) {
  return useSWR<GrowthPost[]>(
    ['growth-posts', status],
    async () => {
      if (!useMockApi()) {
        return apiRequest<GrowthPost[]>(buildPath('/api/company/growth/posts', { status }))
      }
      await delay(500)
      return mockGrowthPosts
    },
    { revalidateOnFocus: false }
  )
}

export function useGrowthInsights() {
  return useSWR<GrowthInsight[]>(
    'growth-insights',
    async () => {
      if (!useMockApi()) {
        return apiRequest<GrowthInsight[]>('/api/company/growth/insights')
      }
      await delay(400)
      return [
        { id: '1', insightType: 'strategy', title: 'Best performing platform', body: 'Facebook posts are generating the highest attributed revenue.', confidenceScore: 75, isRead: false },
      ]
    },
    { revalidateOnFocus: false }
  )
}

export interface GrowthAdSpendData {
  entries: {
    id: string
    platform: string | null
    campaignName: string | null
    amount: number
    currency: string
    spentAt: string
    source: string
  }[]
  totalSpend: number
}

export interface GrowthOAuthConfig {
  callbackUrl: string
  platforms: { platform: string; configured: boolean; authorizeSupported: boolean }[]
}

export function useGrowthOAuthConfig() {
  return useSWR<GrowthOAuthConfig>(
    'growth-oauth-config',
    async () => {
      if (!useMockApi()) {
        return apiRequest<GrowthOAuthConfig>('/api/company/growth/oauth/config')
      }
      await delay(200)
      return {
        callbackUrl: 'http://localhost:8000/oauth/growth/callback',
        platforms: [
          { platform: 'facebook', configured: true, authorizeSupported: true },
          { platform: 'instagram', configured: true, authorizeSupported: true },
          { platform: 'linkedin', configured: false, authorizeSupported: true },
          { platform: 'tiktok', configured: false, authorizeSupported: true },
          { platform: 'twitter', configured: false, authorizeSupported: true },
        ],
      }
    },
    { revalidateOnFocus: false }
  )
}

export function useGrowthAdSpend(period?: string) {
  return useSWR<GrowthAdSpendData>(
    ['growth-ad-spend', period],
    async () => {
      if (!useMockApi()) {
        return apiRequest<GrowthAdSpendData>(buildPath('/api/company/growth/ad-spend', { period }))
      }
      await delay(300)
      return { entries: [], totalSpend: 25000 }
    },
    { revalidateOnFocus: false }
  )
}

export function useGrowthSocialAccounts() {
  return useSWR<GrowthSocialAccount[]>(
    'growth-social-accounts',
    async () => {
      if (!useMockApi()) {
        return apiRequest<GrowthSocialAccount[]>('/api/company/growth/social-accounts')
      }
      await delay(300)
      return [{ id: '1', platform: 'facebook', accountName: 'Demo Page', status: 'connected' }]
    },
    { revalidateOnFocus: false }
  )
}

export function useGrowthCompetitors() {
  return useSWR<GrowthCompetitor[]>(
    'growth-competitors',
    async () => {
      if (!useMockApi()) {
        return apiRequest<GrowthCompetitor[]>('/api/company/growth/competitors')
      }
      await delay(300)
      return []
    },
    { revalidateOnFocus: false }
  )
}

export interface GrowthLearningPattern {
  id: string
  companyId: string | null
  patternType: string
  source: string
  title: string
  body: string
  metrics?: Record<string, unknown> | null
  confidenceScore: number
  isApplied: boolean
  appliedCount: number
  createdAt?: string
}

export interface GrowthContentMixPlan {
  weekOf: string
  totalPosts: number
  mix: { tag: string; count: number; reason: string }[]
  platform: string | null
  adjustments: string[]
}

export interface GrowthDraftScore {
  postId: string
  title: string
  predictedScore: number
  estimatedRevenue: number
  tags: string[]
}

export function useGrowthPatterns() {
  return useSWR<{ patterns: GrowthLearningPattern[] }>(
    'growth-patterns',
    async () => {
      if (!useMockApi()) {
        return apiRequest('/api/company/growth/intelligence/patterns')
      }
      await delay(300)
      return { patterns: [] }
    },
    { revalidateOnFocus: false }
  )
}

export function useGrowthContentMix() {
  return useSWR<{ plan: GrowthContentMixPlan }>(
    'growth-content-mix',
    async () => {
      if (!useMockApi()) {
        return apiRequest('/api/company/growth/intelligence/content-mix')
      }
      await delay(300)
      return {
        plan: {
          weekOf: new Date().toISOString().slice(0, 10),
          totalPosts: 7,
          mix: [
            { tag: 'product_showcase', count: 3, reason: 'Default' },
            { tag: 'testimonial', count: 2, reason: 'Trust' },
            { tag: 'promo', count: 2, reason: 'Conversions' },
          ],
          platform: 'facebook',
          adjustments: [],
        },
      }
    },
    { revalidateOnFocus: false }
  )
}

export function useGrowthDraftScores() {
  return useSWR<{ drafts: GrowthDraftScore[] }>(
    'growth-draft-scores',
    async () => {
      if (!useMockApi()) {
        return apiRequest('/api/company/growth/intelligence/score-drafts')
      }
      await delay(300)
      return { drafts: [] }
    },
    { revalidateOnFocus: false }
  )
}

export function useGrowthAgents() {
  return useSWR<GrowthAgentRun[]>(
    'growth-agents',
    async () => {
      if (!useMockApi()) {
        return apiRequest<GrowthAgentRun[]>('/api/company/growth/agents')
      }
      await delay(300)
      return []
    },
    { revalidateOnFocus: false }
  )
}

export type GrowthIntegrationStatus = {
  provider: 'ga4' | 'email' | 'website'
  status: string
  configured: boolean
  lastSyncedAt?: string | null
  message?: string | null
}

export type GrowthCrmStatus = {
  coldLeads: number
  paymentRecovery: number
  totalEligible: number
  hoursQuiet: number
  paymentRecoveryHours: number
  maxFollowUps: number
  whatsAppActive: boolean
}

export function useGrowthIntegrations() {
  return useSWR<{ integrations: GrowthIntegrationStatus[] }>(
    'growth-integrations',
    async () => {
      if (!useMockApi()) {
        return apiRequest<{ integrations: GrowthIntegrationStatus[] }>('/api/company/growth/integrations')
      }
      await delay(200)
      return {
        integrations: [
          { provider: 'ga4', status: 'not_configured', configured: false },
          { provider: 'email', status: 'not_configured', configured: false },
          { provider: 'website', status: 'available', configured: true },
        ],
      }
    },
    { revalidateOnFocus: false }
  )
}

export function useGrowthCrmStatus() {
  return useSWR<GrowthCrmStatus>(
    'growth-crm-status',
    async () => {
      if (!useMockApi()) {
        return apiRequest<GrowthCrmStatus>('/api/company/growth/crm/status')
      }
      await delay(200)
      return {
        coldLeads: 0,
        paymentRecovery: 0,
        totalEligible: 0,
        hoursQuiet: 24,
        paymentRecoveryHours: 48,
        maxFollowUps: 2,
        whatsAppActive: true,
      }
    },
    { revalidateOnFocus: false }
  )
}

export interface PortfolioRecommendation {
  id: string
  companyId: string | null
  companyName?: string | null
  recommendationType: string
  title: string
  body: string
  confidenceScore: number
  isRead: boolean
  createdAt?: string
}

export interface GrowthPortfolioData {
  period: string
  companies: { companyId: string; companyName: string; leads: number; revenue: number; posts: number }[]
  crossBrandInsight: string
  totals: { leads: number; revenue: number; posts: number }
  recommendations?: PortfolioRecommendation[]
}

export type GrowthPilotStatus = {
  isPilot: boolean
  pilotSince?: string | null
  demoMode?: boolean
  firstAttributedSaleAt?: string | null
  onboarding?: {
    steps: { key: string; label: string; description: string; completed: boolean; actionTab?: string }[]
    completedCount: number
    totalCount: number
    percentComplete: number
    isComplete: boolean
  }
  tokenExpiryWarnings?: { platform: string; accountName: string; expiresAt: string }[]
}

export function useGrowthPilotStatus() {
  return useSWR<GrowthPilotStatus>(
    'growth-pilot',
    async () => {
      if (!useMockApi()) {
        return apiRequest<GrowthPilotStatus>('/api/company/growth/pilot')
      }
      await delay(200)
      return { isPilot: false }
    },
    { revalidateOnFocus: false }
  )
}

export type GrowthPredictionAccuracy = {
  hasEnoughData: boolean
  items: { title: string; predictedRevenue: number; actualRevenue: number }[]
  accuracyPercent: number | null
  message: string
}

export type GrowthPortfolioInsights = {
  tips: unknown[]
  benchmark: { percentile: number | null; leadToOrderRate?: number; message: string }
}

export function useGrowthPredictionAccuracy() {
  return useSWR<GrowthPredictionAccuracy>(
    'growth-prediction-accuracy',
    async () => {
      if (!useMockApi()) {
        return apiRequest<GrowthPredictionAccuracy>('/api/company/growth/intelligence/prediction-accuracy')
      }
      await delay(300)
      return { hasEnoughData: false, items: [], accuracyPercent: null, message: 'Demo mode' }
    },
    { revalidateOnFocus: false }
  )
}

export function useGrowthPortfolioInsights() {
  return useSWR<GrowthPortfolioInsights>(
    'growth-portfolio-insights',
    async () => {
      if (!useMockApi()) {
        return apiRequest<GrowthPortfolioInsights>('/api/company/growth/intelligence/portfolio-insights')
      }
      await delay(300)
      return { tips: [], benchmark: { percentile: null, message: 'Demo' } }
    },
    { revalidateOnFocus: false }
  )
}

export function useGrowthMetaPages(platform = 'facebook') {
  return useSWR<{ pages: { id: string; name: string }[] }>(
    ['growth-meta-pages', platform],
    async () => {
      if (!useMockApi()) {
        return apiRequest(buildPath('/api/company/growth/meta/pages', { platform }))
      }
      await delay(200)
      return { pages: [] }
    },
    { revalidateOnFocus: false }
  )
}

export function useGrowthAdAccounts() {
  return useSWR<{ adAccounts: { id: string; name: string }[]; selectedAdAccountId?: string | null }>(
    'growth-ad-accounts',
    async () => {
      if (!useMockApi()) {
        return apiRequest('/api/company/growth/meta/ad-accounts')
      }
      await delay(200)
      return { adAccounts: [], selectedAdAccountId: null }
    },
    { revalidateOnFocus: false }
  )
}

export function useAdminGrowthPortfolio(period?: string) {
  return useSWR<GrowthPortfolioData>(
    ['admin-growth-portfolio', period],
    async () => {
      if (!useMockApi()) {
        return apiRequest<GrowthPortfolioData>(buildPath('/api/admin/growth-portfolio', { period }))
      }
      await delay(500)
      return {
        period: period ?? '30d',
        companies: [],
        crossBrandInsight: 'Connect social accounts to unlock portfolio insights.',
        totals: { leads: 0, revenue: 0, posts: 0 },
      }
    },
    { revalidateOnFocus: false }
  )
}

// ============================================
// EXECUTIVE AI (Phase 5)
// ============================================

export interface ExecutiveDashboardData {
  worldModel: Record<string, unknown>
  healthScore: {
    overall: number
    factors: Record<string, unknown>
    summary: string
    date: string
  } | null
  topDecisions: Array<{
    decision: string
    evidence?: Record<string, unknown>
    risk?: string
    requires_approval?: boolean
  }>
  pendingApprovals: number
  openOpportunities: number
}

export interface CommerceBriefData {
  brief: {
    date: string
    summary: string
    metrics: Record<string, unknown>
    recommendations: string[]
    executiveDecisions: Array<{ decision: string; evidence?: Record<string, unknown> }>
  } | null
}

export interface AgentApprovalItem {
  id: number
  action_type: string
  risk_level: string
  payload: Record<string, unknown>
  reasoning: string | null
  status: string
  created_at: string
}

export interface BusinessOpportunityItem {
  id: number
  opportunity_type: string
  title: string
  description: string
  priority: string
  status: string
  estimated_impact: Record<string, unknown> | null
  detected_at: string
}

export interface CommerceExperimentVariant {
  id: number
  variant_key: string
  label: string
  payload: { message?: string }
  assignments_count: number
  conversions_count: number
  revenue_total: string
}

export interface CommerceExperimentItem {
  id: number
  name: string
  experiment_type: string
  status: string
  metric_key: string
  winner_variant_id: number | null
  started_at: string | null
  ended_at: string | null
  variants: CommerceExperimentVariant[]
}

export function useExecutiveDashboard() {
  return useSWR<ExecutiveDashboardData>(
    'executive-dashboard',
    async () => apiRequest<ExecutiveDashboardData>('/api/company/executive-ai/dashboard'),
    { revalidateOnFocus: false }
  )
}

export function useCommerceBrief() {
  return useSWR<CommerceBriefData>(
    'commerce-brief',
    async () => apiRequest<CommerceBriefData>('/api/company/commerce-brief'),
    { revalidateOnFocus: false }
  )
}

export function useExecutiveApprovals() {
  return useSWR<{ approvals: AgentApprovalItem[] }>(
    'executive-approvals',
    async () => apiRequest<{ approvals: AgentApprovalItem[] }>('/api/company/executive-ai/approvals'),
    { revalidateOnFocus: false }
  )
}

export function useExecutiveOpportunities() {
  return useSWR<{ opportunities: BusinessOpportunityItem[] }>(
    'executive-opportunities',
    async () => apiRequest<{ opportunities: BusinessOpportunityItem[] }>('/api/company/executive-ai/opportunities'),
    { revalidateOnFocus: false }
  )
}

export function useCommerceExperiments() {
  return useSWR<{ experiments: CommerceExperimentItem[] }>(
    'commerce-experiments',
    async () => apiRequest<{ experiments: CommerceExperimentItem[] }>('/api/company/commerce-experiments'),
    { revalidateOnFocus: false }
  )
}

// ============================================
// COGNITIVE AI + INTELLIGENCE API (ABI Level 19)
// ============================================

export interface CognitiveDashboardData {
  architecture: string
  workforce: Array<{ id: string; title: string; objective: string; reports: string }>
  forecast: Record<string, unknown>
  causalAnalysis: {
    metric: string
    change: string
    likely_causes: Array<{ cause: string; likelihood: string }>
  }
  recentEpisode: {
    confidence: number
    confidence_action: string
    perception: Record<string, unknown>
    outcome: string | null
  } | null
  counts: {
    strategic_memories: number
    tool_proposals: number
    knowledge_artifacts: number
    executive_plans: number
  }
}

export interface IntelligenceReasoningResult {
  goal: string
  confidence: number
  executive_summary: string
  assumptions: string[]
  hypotheses: Array<{
    hypothesis: string
    likelihood: string
    source: string
    confidence?: number
  }>
  recommended_actions: Array<{
    action: string
    source: string
    requires_approval?: boolean
  }>
  simulation: {
    scenarios: Array<Record<string, unknown>>
    recommendation: string
  } | null
  plan: { breakdown: Record<string, unknown>; persisted?: boolean } | null
  missing_info: string[]
  investigation_id: number
  case_id?: number
  probability_scores?: {
    buy: number
    churn: number
    refund: number
    factors: Record<string, unknown>
  }
}

export interface InvestigationCaseItem {
  id: number
  goal: string
  status: string
  current_step: number
  steps: Array<{ step: number; name: string; status: string; at: string | null }>
  owner_analytics_investigation_id: number | null
  created_at: string
}

export function useInvestigationCases() {
  return useSWR<{ cases: InvestigationCaseItem[] }>(
    'investigation-cases',
    async () => apiRequest<{ cases: InvestigationCaseItem[] }>('/api/company/intelligence/cases'),
    { revalidateOnFocus: false }
  )
}

export function useCognitiveDashboard() {
  return useSWR<CognitiveDashboardData>(
    'cognitive-dashboard',
    async () => apiRequest<CognitiveDashboardData>('/api/company/cognitive-ai/dashboard'),
    { revalidateOnFocus: false }
  )
}

export interface CommerceAgentEventItem {
  id: number
  eventType: string
  eventKey: string
  status: string
  payload?: Record<string, unknown>
  handledAt?: string | null
  createdAt?: string
}

export interface CommerceSpecialistRunItem {
  id: string
  agentType: string
  status: string
  chatId?: number | null
  output?: Record<string, unknown>
  startedAt?: string | null
  completedAt?: string | null
}

export function useCommerceSpecialistRuns() {
  return useSWR<{ runs: CommerceSpecialistRunItem[] }>(
    'commerce-specialist-runs',
    async () => apiRequest<{ runs: CommerceSpecialistRunItem[] }>('/api/company/commerce-specialists/runs'),
    { revalidateOnFocus: false }
  )
}

export function useCommerceOwnerAlerts() {
  return useSWR<{ alerts: CommerceAgentEventItem[] }>(
    'commerce-owner-alerts',
    async () => apiRequest<{ alerts: CommerceAgentEventItem[] }>('/api/company/commerce-events/owner-alerts'),
    { revalidateOnFocus: false }
  )
}

export function useCommerceEvents() {
  return useSWR<{ events: CommerceAgentEventItem[] }>(
    'commerce-events',
    async () => apiRequest<{ events: CommerceAgentEventItem[] }>('/api/company/commerce-events'),
    { revalidateOnFocus: false }
  )
}

export interface CompanyBrainSnapshot {
  id: number
  snapshotAt?: string
  summaryText?: string
  commerceData?: Record<string, unknown>
  growthData?: Record<string, unknown>
  digest?: Record<string, unknown>
}

export interface OwnerAnalyticsInvestigationItem {
  id: number
  question: string
  period: string
  status: string
  confidence?: number
  findings?: unknown[]
  recommendations?: unknown[]
  evidence?: Record<string, unknown>
  createdAt?: string
}

export interface CommerceConnectorItem {
  type: string
  name: string
  category: string
  status_label: string
  description: string
  status: string
  connected: boolean
  lastSyncAt?: string | null
  lastError?: string | null
}

export function useCompanyBrain() {
  return useSWR<{ snapshot: CompanyBrainSnapshot }>(
    'company-brain',
    async () => apiRequest<{ snapshot: CompanyBrainSnapshot }>('/api/company/company-brain'),
    { revalidateOnFocus: false }
  )
}

export function useOwnerAnalyticsInvestigations() {
  return useSWR<{ investigations: OwnerAnalyticsInvestigationItem[] }>(
    'owner-analytics-investigations',
    async () =>
      apiRequest<{ investigations: OwnerAnalyticsInvestigationItem[] }>(
        '/api/company/owner-analytics/investigations'
      ),
    { revalidateOnFocus: false }
  )
}

export function useCommerceIntegrations() {
  return useSWR<{ connectors: CommerceConnectorItem[] }>(
    'commerce-integrations',
    async () => apiRequest<{ connectors: CommerceConnectorItem[] }>('/api/company/integrations'),
    { revalidateOnFocus: false }
  )
}

export function useKnowledgeVectorStatus() {
  return useSWR<{ vectorSearch: { driver: string; pgvector: boolean; message: string }; chunkCount: number }>(
    'knowledge-vector-status',
    async () =>
      apiRequest('/api/company/knowledge/vector-status'),
    { revalidateOnFocus: false }
  )
}

export type MissionControlAttentionItem = {
  priority: number
  type: string
  title: string
  summary?: string | null
  id?: number | null
  href?: string
}

export type MissionControlData = {
  generatedAt: string
  brainSummary?: string | null
  brainDigest?: Record<string, unknown> | null
  healthScore?: { overall: number; summary?: string; date: string } | null
  topDecisions: Array<{ decision: string; evidence?: Record<string, unknown> }>
  attentionQueue: MissionControlAttentionItem[]
  counts: { openEvents: number; pendingApprovals: number; openOpportunities: number }
  recentTimeline: Array<{
    id: number
    eventType: string
    category: string
    title: string
    summary?: string | null
    importance: number
    occurredAt?: string
  }>
  graphStats: { nodes: number; edges: number }
}

export function useMissionControl() {
  return useSWR<MissionControlData>(
    'mission-control',
    async () => apiRequest<MissionControlData>('/api/company/mission-control'),
    { revalidateOnFocus: false }
  )
}

export function useBusinessTimeline(category?: string) {
  const key = category ? `business-timeline-${category}` : 'business-timeline'
  return useSWR<{ events: MissionControlData['recentTimeline'] }>(
    key,
    async () =>
      apiRequest<{ events: MissionControlData['recentTimeline'] }>(
        `/api/company/business-timeline${category ? `?category=${encodeURIComponent(category)}` : ''}`
      ),
    { revalidateOnFocus: false }
  )
}

export function useBusinessGraph() {
  return useSWR<{
    stats: { nodes: number; edges: number }
    nodes: Array<{ id: number; type: string; label: string; refType?: string; refId?: number; metadata?: Record<string, unknown> }>
    edges: Array<{ from: number; to: number; type: string }>
  }>(
    'business-graph',
    async () => apiRequest('/api/company/business-graph'),
    { revalidateOnFocus: false }
  )
}

export type AgentTrustLogItem = {
  id: number
  actionType?: string | null
  goal?: string | null
  reasoningSummary?: string | null
  confidence?: number | null
  toolsUsed?: unknown
  dataConsulted?: unknown
  outcome?: string | null
  explainability?: Record<string, unknown> | null
  createdAt?: string | null
}

export function useAgentTrustLogs(limit = 10) {
  return useSWR<{ logs: AgentTrustLogItem[] }>(
    `agent-trust-logs-${limit}`,
    async () => apiRequest<{ logs: AgentTrustLogItem[] }>(`/api/company/agent-trust-logs?limit=${limit}`),
    { revalidateOnFocus: false }
  )
}

export type MemorySearchResult = {
  source: string
  sourceType: string
  sourceId: number
  title: string
  snippet?: string | null
  score: number
  occurredAt?: string | null
}

export type MemorySearchResponse = {
  query: string
  results: MemorySearchResult[]
  counts: { total: number; knowledge: number; investigations: number; timeline: number; briefs: number }
}

export type MarketplaceModuleItem = {
  moduleKey: string
  name: string
  description?: string | null
  category: string
  publisher: string
  requiredPlan?: string | null
  tools: string[]
  isInstalled: boolean
  canInstall: boolean
  isThirdParty: boolean
}

export type MarketplaceInstalledItem = {
  moduleKey: string
  name: string
  description?: string | null
  category?: string
  publisher?: string
  tools: string[]
  config?: Record<string, unknown>
  installedAt?: string
}

export function useMarketplaceModules() {
  return useSWR<{ modules: MarketplaceModuleItem[]; installed: MarketplaceInstalledItem[] }>(
    'marketplace-modules',
    async () => apiRequest('/api/company/marketplace/modules'),
    { revalidateOnFocus: false }
  )
}
