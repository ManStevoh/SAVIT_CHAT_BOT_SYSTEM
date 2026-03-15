// API Actions for form submissions and mutations
// Uses mock data when NEXT_PUBLIC_USE_MOCK_API is true; calls Laravel when false (set NEXT_PUBLIC_API_URL).

import type {
  Order,
  Product,
  FAQ,
  Plan,
  PaymentGateway,
  Company,
  User,
} from './mock-data'
import { useMockApi, apiRequest } from './api-client'

const delay = (ms: number) => new Promise(resolve => setTimeout(resolve, ms))

function handleApiError(e: unknown): { success: false; message: string } {
  return { success: false, message: e instanceof Error ? e.message : 'Request failed' }
}

// ============================================
// AUTHENTICATION ACTIONS
// ============================================

export interface LoginCredentials {
  email: string
  password: string
  rememberMe?: boolean
}

export interface RegisterData {
  companyName: string
  name: string
  email: string
  phone: string
  password: string
  confirmPassword: string
  acceptTerms: boolean
}

export interface ForgotPasswordData {
  email: string
}

export interface ResetPasswordData {
  token: string
  email: string
  password: string
  confirmPassword: string
}

/**
 * Login user
 * Laravel: POST /api/auth/login
 */
export async function login(credentials: LoginCredentials): Promise<{ success: boolean; message?: string; user?: User; token?: string }> {
  if (useMockApi()) {
    await delay(1500)
    if (!credentials.email || !credentials.password) {
      return { success: false, message: 'Email and password are required' }
    }
    return {
      success: true,
      user: {
        id: '1',
        name: 'Test User',
        email: credentials.email,
        role: 'company_owner',
        companyId: '1',
        companyName: 'Tech Store Egypt',
        status: 'active',
        lastLogin: new Date().toISOString(),
        createdAt: '2023-06-15',
      },
    }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string; user?: User; token?: string }>('/api/auth/login', {
      method: 'POST',
      body: credentials,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Register new company
 * Laravel: POST /api/auth/register
 */
export async function register(data: RegisterData): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(2000)
    if (data.password !== data.confirmPassword) {
      return { success: false, message: 'Passwords do not match' }
    }
    if (!data.acceptTerms) {
      return { success: false, message: 'You must accept the terms and conditions' }
    }
    return { success: true, message: 'Registration successful! Please check your email to verify your account.' }
  }
  try {
    // Laravel's 'confirmed' rule expects password_confirmation, not confirmPassword
    const body = {
      companyName: data.companyName,
      name: data.name,
      email: data.email,
      phone: data.phone,
      password: data.password,
      password_confirmation: data.confirmPassword,
      acceptTerms: data.acceptTerms,
    }
    return await apiRequest<{ success: boolean; message?: string }>('/api/auth/register', {
      method: 'POST',
      body,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Request password reset
 * Laravel: POST /api/auth/forgot-password
 */
export async function forgotPassword(data: ForgotPasswordData): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(1500)
    return { success: true, message: 'If an account exists with this email, you will receive a password reset link.' }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>('/api/auth/forgot-password', {
      method: 'POST',
      body: data,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Reset password with token
 * Laravel: POST /api/auth/reset-password
 */
export async function resetPassword(data: ResetPasswordData): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(1500)
    if (data.password !== data.confirmPassword) {
      return { success: false, message: 'Passwords do not match' }
    }
    return { success: true, message: 'Password reset successful! You can now login with your new password.' }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>('/api/auth/reset-password', {
      method: 'POST',
      body: data,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Logout user
 * Laravel: POST /api/auth/logout
 */
export async function logout(): Promise<{ success: boolean }> {
  if (useMockApi()) {
    await delay(500)
    return { success: true }
  }
  try {
    return await apiRequest<{ success: boolean }>('/api/auth/logout', { method: 'POST' })
  } catch (e) {
    return handleApiError(e)
  }
}

// ============================================
// COMPANY DASHBOARD ACTIONS
// ============================================

export interface SendMessageData {
  chatId: string
  content: string
}

/**
 * Send message in chat
 * Laravel: POST /api/company/chats/:chatId/messages
 */
export async function sendMessage(data: SendMessageData): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(500)
    return { success: true }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>(
      `/api/company/chats/${data.chatId}/messages`,
      { method: 'POST', body: { content: data.content } }
    )
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Update order status
 * Laravel: PATCH /api/company/orders/:orderId
 */
export async function updateOrderStatus(
  orderId: string,
  status: Order['status']
): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(800)
    return { success: true, message: 'Order status updated successfully' }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>(`/api/company/orders/${orderId}`, {
      method: 'PATCH',
      body: { status },
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export interface CreateProductData {
  name: string
  description: string
  price: number
  category: string
  stock: number
  image?: File
}

/**
 * Create new product
 * Laravel: POST /api/company/products (JSON or multipart if image)
 */
export async function createProduct(data: CreateProductData): Promise<{ success: boolean; product?: Product; message?: string }> {
  if (useMockApi()) {
    await delay(1500)
    return {
      success: true,
      product: {
        id: Math.random().toString(36).substr(2, 9),
        name: data.name,
        description: data.description,
        price: data.price,
        category: data.category,
        stock: data.stock,
        status: 'active',
        createdAt: new Date().toISOString(),
      },
      message: 'Product created successfully',
    }
  }
  try {
    if (data.image) {
      const formData = new FormData()
      formData.append('name', data.name)
      formData.append('description', data.description)
      formData.append('price', String(data.price))
      formData.append('category', data.category)
      formData.append('stock', String(data.stock))
      formData.append('image', data.image)
      return await apiRequest<{ success: boolean; product?: Product; message?: string }>('/api/company/products', {
        method: 'POST',
        body: formData,
      })
    }
    return await apiRequest<{ success: boolean; product?: Product; message?: string }>('/api/company/products', {
      method: 'POST',
      body: { name: data.name, description: data.description, price: data.price, category: data.category, stock: data.stock },
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Update product
 * Laravel: PUT /api/company/products/:productId
 */
export async function updateProduct(
  productId: string,
  data: Partial<CreateProductData>
): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(1000)
    return { success: true, message: 'Product updated successfully' }
  }
  try {
    const body = { ...data }
    if (body.image !== undefined) delete (body as Record<string, unknown>).image
    return await apiRequest<{ success: boolean; message?: string }>(`/api/company/products/${productId}`, {
      method: 'PUT',
      body: body as Record<string, unknown>,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Delete product
 * Laravel: DELETE /api/company/products/:productId
 */
export async function deleteProduct(productId: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(800)
    return { success: true, message: 'Product deleted successfully' }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>(`/api/company/products/${productId}`, {
      method: 'DELETE',
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export interface CreateFAQData {
  question: string
  answer: string
  category: string
  keywords: string[]
}

/**
 * Create new FAQ
 * Laravel: POST /api/company/faqs
 */
export async function createFAQ(data: CreateFAQData): Promise<{ success: boolean; faq?: FAQ; message?: string }> {
  if (useMockApi()) {
    await delay(1000)
    return {
      success: true,
      faq: {
        id: Math.random().toString(36).substr(2, 9),
        question: data.question,
        answer: data.answer,
        category: data.category,
        keywords: data.keywords,
        isActive: true,
        usageCount: 0,
        createdAt: new Date().toISOString(),
      },
      message: 'FAQ created successfully',
    }
  }
  try {
    return await apiRequest<{ success: boolean; faq?: FAQ; message?: string }>('/api/company/faqs', {
      method: 'POST',
      body: data,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Update FAQ
 * Laravel: PUT /api/company/faqs/:faqId
 */
export async function updateFAQ(
  faqId: string,
  data: Partial<CreateFAQData & { isActive: boolean }>
): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(800)
    return { success: true, message: 'FAQ updated successfully' }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>(`/api/company/faqs/${faqId}`, {
      method: 'PUT',
      body: data,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Delete FAQ
 * Laravel: DELETE /api/company/faqs/:faqId
 */
export async function deleteFAQ(faqId: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(600)
    return { success: true, message: 'FAQ deleted successfully' }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>(`/api/company/faqs/${faqId}`, {
      method: 'DELETE',
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export interface UpdateSettingsData {
  companyName?: string
  email?: string
  phone?: string
  logo?: File
  whatsappNumber?: string
  aiGreeting?: string
  aiTone?: string
  autoReplyEnabled?: boolean
  notificationsEnabled?: boolean
}

/**
 * Update company settings
 * Laravel: PUT /api/company/settings (multipart if logo present)
 */
export async function updateSettings(data: UpdateSettingsData): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(1500)
    return { success: true, message: 'Settings updated successfully' }
  }
  try {
    if (data.logo) {
      const formData = new FormData()
      Object.entries(data).forEach(([key, value]) => {
        if (value !== undefined && value !== null) {
          formData.append(key, value instanceof File ? value : String(value))
        }
      })
      return await apiRequest<{ success: boolean; message?: string }>('/api/company/settings', {
        method: 'PUT',
        body: formData,
      })
    }
    const body = { ...data }
    delete (body as Record<string, unknown>).logo
    return await apiRequest<{ success: boolean; message?: string }>('/api/company/settings', {
      method: 'PUT',
      body: body as Record<string, unknown>,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Connect WhatsApp number
 * Laravel: POST /api/company/whatsapp/connect
 */
export async function connectWhatsApp(phoneNumber: string): Promise<{ success: boolean; qrCode?: string; message?: string }> {
  if (useMockApi()) {
    await delay(2000)
    return {
      success: true,
      qrCode: 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=example',
      message: 'Scan the QR code with WhatsApp to connect',
    }
  }
  try {
    return await apiRequest<{ success: boolean; qrCode?: string; message?: string }>('/api/company/whatsapp/connect', {
      method: 'POST',
      body: { phoneNumber },
    })
  } catch (e) {
    return handleApiError(e)
  }
}

// ============================================
// SUPER ADMIN ACTIONS
// ============================================

export interface CreatePlanData {
  name: string
  slug: string
  priceDisplay: string
  priceAmount?: number | null
  description?: string
  features?: string[]
  popular?: boolean
  cta?: string
  sortOrder?: number
  stripePriceId?: string
  isFree?: boolean
  hasTrial?: boolean
  trialDays?: number | null
  trialElapsedAction?: string | null
}

export interface UpdatePlanData {
  name?: string
  slug?: string
  priceDisplay?: string
  priceAmount?: number | null
  description?: string
  features?: string[]
  popular?: boolean
  cta?: string
  sortOrder?: number
  stripePriceId?: string
  isFree?: boolean
  hasTrial?: boolean
  trialDays?: number | null
  trialElapsedAction?: string | null
}

/**
 * Create plan (admin only)
 * Laravel: POST /api/admin/plans
 */
export async function createPlan(data: CreatePlanData): Promise<{ success: boolean; plan?: Plan; message?: string }> {
  if (useMockApi()) {
    await delay(600)
    return { success: true, plan: { id: String(Date.now()), name: data.name, slug: data.slug, priceDisplay: data.priceDisplay, priceAmount: data.priceAmount ?? null, description: data.description ?? '', features: data.features ?? [], popular: data.popular ?? false, cta: data.cta ?? 'Start Free Trial', sortOrder: data.sortOrder ?? 0, isFree: data.isFree ?? false, hasTrial: data.hasTrial ?? false, trialDays: data.trialDays ?? null, trialElapsedAction: data.trialElapsedAction ?? null } as Plan }
  }
  try {
    const res = await apiRequest<{ success: boolean; plan: Plan }>('/api/admin/plans', { method: 'POST', body: data })
    return res
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

/**
 * Update plan (admin only)
 * Laravel: PUT /api/admin/plans/:planId
 */
export async function updatePlan(planId: string, data: UpdatePlanData): Promise<{ success: boolean; plan?: Plan; message?: string }> {
  if (useMockApi()) {
    await delay(600)
    return { success: true }
  }
  try {
    const res = await apiRequest<{ success: boolean; plan: Plan }>(`/api/admin/plans/${planId}`, { method: 'PUT', body: data })
    return res
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

/**
 * Delete plan (admin only)
 * Laravel: DELETE /api/admin/plans/:planId
 */
export async function deletePlan(planId: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    return await apiRequest<{ success: boolean }>(`/api/admin/plans/${planId}`, { method: 'DELETE' })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Create Stripe Checkout Session (company user). Returns redirect URL.
 * Laravel: POST /api/company/checkout
 */
export async function createCheckoutSession(
  planId: string,
  options?: { successUrl?: string; cancelUrl?: string }
): Promise<{ success: boolean; url?: string; message?: string }> {
  if (useMockApi()) {
    await delay(600)
    return { success: true, url: 'https://checkout.stripe.com/c/pay/cs_test_placeholder' }
  }
  try {
    const res = await apiRequest<{ url: string }>('/api/company/checkout', {
      method: 'POST',
      body: { planId, ...options },
    })
    return { success: true, url: res.url }
  } catch (e) {
    return { success: false, message: e instanceof Error ? e.message : 'Checkout failed' }
  }
}

/**
 * Create Stripe Billing Portal session (company user). Returns redirect URL.
 * Laravel: POST /api/company/billing-portal
 */
export async function createBillingPortalSession(
  returnUrl?: string
): Promise<{ success: boolean; url?: string; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true, url: 'https://billing.stripe.com/session/placeholder' }
  }
  try {
    const res = await apiRequest<{ url: string }>('/api/company/billing-portal', {
      method: 'POST',
      body: returnUrl ? { returnUrl } : {},
    })
    return { success: true, url: res.url }
  } catch (e) {
    return { success: false, message: e instanceof Error ? e.message : 'Could not open billing portal' }
  }
}

/**
 * Update payment gateway (admin only)
 * Laravel: PUT /api/admin/payment-gateways/:slug
 */
export async function updatePaymentGateway(
  slug: string,
  data: { isEnabled?: boolean; config?: Record<string, string | number> }
): Promise<{ success: boolean; gateway?: PaymentGateway; message?: string }> {
  if (useMockApi()) {
    await delay(600)
    return { success: true }
  }
  try {
    const res = await apiRequest<{ success: boolean; gateway: PaymentGateway }>(`/api/admin/payment-gateways/${slug}`, {
      method: 'PUT',
      body: data,
    })
    return res
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

/**
 * Update company status (admin only)
 * Laravel: PATCH /api/admin/companies/:companyId
 */
export async function updateCompanyStatus(
  companyId: string,
  status: Company['status']
): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(800)
    return { success: true, message: 'Company status updated successfully' }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>(`/api/admin/companies/${companyId}`, {
      method: 'PATCH',
      body: { status },
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Update user status (admin only)
 * Laravel: PATCH /api/admin/users/:userId
 */
export async function updateUserStatus(
  userId: string,
  status: User['status']
): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(800)
    return { success: true, message: 'User status updated successfully' }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>(`/api/admin/users/${userId}`, {
      method: 'PATCH',
      body: { status },
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export interface UpdatePlatformSettingsData {
  platformName?: string
  supportEmail?: string
  maintenanceMode?: boolean
  aiModel?: string
  maxTokensPerRequest?: number
  rateLimitPerMinute?: number
}

/**
 * Update platform settings (admin only)
 * Laravel: PUT /api/admin/settings
 */
export async function updatePlatformSettings(data: UpdatePlatformSettingsData): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(1500)
    return { success: true, message: 'Platform settings updated successfully' }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>('/api/admin/settings', {
      method: 'PUT',
      body: data,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Export data (admin only)
 * Laravel: POST /api/admin/export
 */
export async function exportData(
  dataType: 'companies' | 'users' | 'subscriptions' | 'revenue',
  format: 'csv' | 'json' | 'xlsx'
): Promise<{ success: boolean; downloadUrl?: string; message?: string }> {
  if (useMockApi()) {
    await delay(2000)
    return {
      success: true,
      downloadUrl: `/exports/${dataType}-${Date.now()}.${format}`,
      message: 'Export generated successfully',
    }
  }
  try {
    return await apiRequest<{ success: boolean; downloadUrl?: string; message?: string }>('/api/admin/export', {
      method: 'POST',
      body: { dataType, format },
    })
  } catch (e) {
    return handleApiError(e)
  }
}
