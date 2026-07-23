// API Actions for form submissions and mutations
// When real API is available (VITE_API_URL set and VITE_USE_MOCK_API not 'true'), all functions call Laravel; mock branches are skipped.

import type {
  Order,
  Product,
  ProductImage,
  ProductVariant,
  FAQ,
  Plan,
  PaymentGateway,
  Company,
  User,
  Subscription,
  GrowthPost,
} from './mock-data'
import type { BusinessDnaSettings } from './api-hooks'
import type { IntelligenceReasoningResult } from './api-hooks'
import { mockSubscriptions } from './mock-data'
import { useMockApi, apiRequest, apiUrl } from './api-client'

export { apiRequest, apiUrl, resolveBackendMediaUrl } from './api-client'

const delay = (ms: number) => new Promise(resolve => setTimeout(resolve, ms))

function handleApiError(e: unknown): { success: false; message: string; code?: string } {
  const err = e as Error & { code?: string }
  return {
    success: false,
    message: err instanceof Error ? err.message : 'Request failed',
    code: err?.code,
  }
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
    // Mock only: when real API is used, backend returns actual user.
    const email = credentials.email
    const nameFromEmail = email.split('@')[0]
    const displayName = nameFromEmail ? nameFromEmail.charAt(0).toUpperCase() + nameFromEmail.slice(1) : 'Demo User'
    return {
      success: true,
      user: {
        id: '1',
        name: displayName,
        email,
        role: 'company_owner',
        companyId: '1',
        companyName: 'Demo Company',
        status: 'active',
        lastLogin: new Date().toISOString(),
        createdAt: new Date().toISOString().slice(0, 10),
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
    // Laravel's 'confirmed' rule expects password_confirmation; backend also accepts confirmPassword
    const body = {
      companyName: data.companyName,
      name: data.name,
      email: data.email,
      phone: data.phone,
      password: data.password,
      password_confirmation: data.confirmPassword,
      confirmPassword: data.confirmPassword,
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
 * Resend email verification link
 * Laravel: POST /api/auth/resend-verification
 */
export async function resendVerificationEmail(email: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(800)
    return { success: true, message: 'A new verification link has been sent to your email address.' }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>('/api/auth/resend-verification', {
      method: 'POST',
      body: { email },
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

export interface UpdateAccountProfileData {
  name: string
  email: string
  phone?: string
}

export interface UpdateAccountPasswordData {
  currentPassword: string
  password: string
  confirmPassword: string
}

/**
 * Get the authenticated user's profile.
 * Laravel: GET /api/auth/me
 */
export async function getAccountProfile(): Promise<User> {
  if (useMockApi()) {
    await delay(300)
    return {
      id: '1',
      name: 'Demo Admin',
      email: 'admin@example.com',
      phone: '+254700000000',
      role: 'admin',
      status: 'active',
      lastLogin: new Date().toISOString(),
      createdAt: '2024-01-01',
    }
  }
  const res = await apiRequest<{ user: User }>('/api/auth/me')
  return res.user
}

/**
 * Update the authenticated user's profile.
 * Laravel: PUT /api/auth/profile
 */
export async function updateAccountProfile(
  data: UpdateAccountProfileData
): Promise<{ success: boolean; message?: string; user?: User }> {
  if (useMockApi()) {
    await delay(500)
    return {
      success: true,
      message: 'Profile updated successfully.',
      user: {
        id: '1',
        name: data.name,
        email: data.email,
        phone: data.phone,
        role: 'admin',
        status: 'active',
        lastLogin: new Date().toISOString(),
        createdAt: '2024-01-01',
      },
    }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string; user?: User }>('/api/auth/profile', {
      method: 'PUT',
      body: data,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Update the authenticated user's password.
 * Laravel: PUT /api/auth/password
 */
export async function updateAccountPassword(
  data: UpdateAccountPasswordData
): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(500)
    return { success: true, message: 'Password updated successfully.' }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>('/api/auth/password', {
      method: 'PUT',
      body: data,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

// ============================================
// COMPANY DASHBOARD ACTIONS
// ============================================

export interface SendMessageData {
  chatId: string
  content?: string
  attachment?: File
}

export interface CreateOrderFromChatData {
  chatId: string
  items: Array<{
    name: string
    quantity: number
    price: number
  }>
  sendWhatsApp?: boolean
}

/**
 * Send message in chat
 * Laravel: POST /api/company/chats/:chatId/messages
 */
export async function sendMessage(data: SendMessageData): Promise<{
  success: boolean
  message?: string
  whatsappSent?: boolean
  whatsappError?: string | null
}> {
  if (useMockApi()) {
    await delay(500)
    return { success: true, whatsappSent: true }
  }
  try {
    const trimmedContent = (data.content ?? '').trim()
    const hasAttachment = data.attachment != null
    if (!hasAttachment && trimmedContent === '') {
      return { success: false, message: 'Message text or attachment is required' }
    }

    const body: { content: string } | FormData = hasAttachment
      ? (() => {
          const formData = new FormData()
          formData.append('content', trimmedContent)
          formData.append('attachment', data.attachment as File)
          return formData
        })()
      : { content: trimmedContent }

    return await apiRequest<{
      success: boolean
      message?: string
      whatsappSent?: boolean
      whatsappError?: string | null
    }>(`/api/company/chats/${data.chatId}/messages`, {
      method: 'POST',
      body,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Hand back chat to bot (clear agent_handling_at so auto-reply resumes).
 * Laravel: POST /api/company/chats/:chatId/hand-back
 */
export async function handBackToBot(chatId: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(300)
    return { success: true, message: 'Chat handed back to bot.' }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>(
      `/api/company/chats/${chatId}/hand-back`,
      { method: 'POST' }
    )
  } catch (e) {
    return handleApiError(e)
  }
}

/** Laravel: POST /api/company/notifications/:id/read */
export async function markNotificationRead(
  notificationId: string
): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(150)
    return { success: true }
  }
  try {
    return await apiRequest<{ success: boolean }>(`/api/company/notifications/${notificationId}/read`, {
      method: 'POST',
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/** Laravel: POST /api/company/notifications/read-all */
export async function markAllNotificationsRead(): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(150)
    return { success: true }
  }
  try {
    return await apiRequest<{ success: boolean }>('/api/company/notifications/read-all', {
      method: 'POST',
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Update order status and/or payment status
 * Laravel: PATCH /api/company/orders/:orderId  body: { status?, paymentStatus? }
 */
export async function updateOrderStatus(
  orderId: string,
  status: Order['status'],
  paymentStatus?: 'pending' | 'paid' | 'refunded'
): Promise<{ success: boolean; message?: string; whatsappSent?: boolean; whatsappError?: string | null }> {
  if (useMockApi()) {
    await delay(800)
    return { success: true, message: 'Order updated successfully', whatsappSent: true, whatsappError: null }
  }
  try {
    const body: { status: Order['status']; paymentStatus?: string } = { status }
    if (paymentStatus !== undefined) body.paymentStatus = paymentStatus
    return await apiRequest<{ success: boolean; message?: string; whatsappSent?: boolean; whatsappError?: string | null }>(`/api/company/orders/${orderId}`, {
      method: 'PATCH',
      body,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Update only payment status (e.g. mark as paid manually).
 * Laravel: PATCH /api/company/orders/:orderId with body: { paymentStatus }
 */
export async function updateOrderPaymentStatus(
  orderId: string,
  paymentStatus: 'pending' | 'paid' | 'refunded'
): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(800)
    return { success: true, message: 'Order updated successfully' }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>(`/api/company/orders/${orderId}`, {
      method: 'PATCH',
      body: { paymentStatus },
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Create an order for a chat customer and optionally send WhatsApp invoice.
 * Laravel: POST /api/company/orders
 */
export async function createOrderFromChat(
  data: CreateOrderFromChatData
): Promise<{
  success: boolean
  message?: string
  order?: { id: string; orderNumber: string }
  whatsappSent?: boolean
  whatsappError?: string | null
}> {
  if (useMockApi()) {
    await delay(600)
    return {
      success: true,
      message: 'Order created and invoice sent.',
      order: { id: String(Date.now()), orderNumber: `ORD-${Math.random().toString(36).slice(2, 10).toUpperCase()}` },
      whatsappSent: true,
      whatsappError: null,
    }
  }
  try {
    return await apiRequest<{
      success: boolean
      message?: string
      order?: { id: string; orderNumber: string }
      whatsappSent?: boolean
      whatsappError?: string | null
    }>('/api/company/orders', {
      method: 'POST',
      body: data,
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
    if (data.image) {
      const formData = new FormData()
      Object.entries(data).forEach(([key, value]) => {
        if (value === undefined || value === null) return
        if (key === 'image' && value instanceof File) {
          formData.append('image', value)
        } else {
          formData.append(key, String(value))
        }
      })
      // Multipart file uploads must use POST: PHP does not populate uploaded files for PUT.
      return await apiRequest<{ success: boolean; message?: string; product?: Product }>(
        `/api/company/products/${productId}`,
        { method: 'POST', body: formData }
      )
    }
    return await apiRequest<{ success: boolean; message?: string }>(`/api/company/products/${productId}`, {
      method: 'PUT',
      body: data as Record<string, unknown>,
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

export interface CreateProductVariantData {
  label: string
  price: number
  stock?: number
  status?: 'active' | 'inactive'
  sortOrder?: number
  attributes?: Record<string, string>
  image?: File
}

/**
 * Laravel: POST /api/company/products/:productId/variants
 */
export async function createProductVariant(
  productId: string,
  data: CreateProductVariantData
): Promise<{ success: boolean; variant?: ProductVariant; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return {
      success: true,
      variant: {
        id: Math.random().toString(36).slice(2),
        label: data.label,
        price: data.price,
        stock: data.stock ?? 0,
        status: data.status ?? 'active',
        attributes: data.attributes ?? {},
        sortOrder: data.sortOrder ?? 0,
      },
    }
  }
  try {
    if (data.image) {
      const formData = new FormData()
      formData.append('label', data.label)
      formData.append('price', String(data.price))
      formData.append('stock', String(data.stock ?? 0))
      formData.append('status', data.status ?? 'active')
      formData.append('sortOrder', String(data.sortOrder ?? 0))
      formData.append('attributes', JSON.stringify(data.attributes ?? {}))
      formData.append('image', data.image)
      return await apiRequest<{ success: boolean; variant: ProductVariant; message?: string }>(
        `/api/company/products/${productId}/variants`,
        {
          method: 'POST',
          body: formData,
        }
      )
    }
    return await apiRequest<{ success: boolean; variant: ProductVariant; message?: string }>(
      `/api/company/products/${productId}/variants`,
      {
        method: 'POST',
        body: {
          label: data.label,
          price: data.price,
          stock: data.stock ?? 0,
          status: data.status ?? 'active',
          sortOrder: data.sortOrder ?? 0,
          attributes: data.attributes ?? {},
        },
      }
    )
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

export interface UploadProductImageData {
  image: File
  isPrimary?: boolean
  sortOrder?: number
  altText?: string
}

export async function uploadProductImage(
  productId: string,
  data: UploadProductImageData
): Promise<{ success: boolean; image?: ProductImage; message?: string }> {
  if (useMockApi()) {
    await delay(300)
    return { success: true }
  }
  try {
    const formData = new FormData()
    formData.append('image', data.image)
    if (data.isPrimary !== undefined) formData.append('isPrimary', data.isPrimary ? '1' : '0')
    if (data.sortOrder !== undefined) formData.append('sortOrder', String(data.sortOrder))
    if (data.altText !== undefined) formData.append('altText', data.altText)
    return await apiRequest<{ success: boolean; image?: ProductImage; message?: string }>(
      `/api/company/products/${productId}/images`,
      { method: 'POST', body: formData }
    )
  } catch (e) {
    return handleApiError(e)
  }
}

export async function uploadVariantImage(
  variantId: string,
  data: UploadProductImageData
): Promise<{ success: boolean; image?: ProductImage; message?: string }> {
  if (useMockApi()) {
    await delay(300)
    return { success: true }
  }
  try {
    const formData = new FormData()
    formData.append('image', data.image)
    if (data.isPrimary !== undefined) formData.append('isPrimary', data.isPrimary ? '1' : '0')
    if (data.sortOrder !== undefined) formData.append('sortOrder', String(data.sortOrder))
    if (data.altText !== undefined) formData.append('altText', data.altText)
    return await apiRequest<{ success: boolean; image?: ProductImage; message?: string }>(
      `/api/company/product-variants/${variantId}/images`,
      { method: 'POST', body: formData }
    )
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Laravel: PUT /api/company/product-variants/:variantId
 */
export async function updateProductVariant(
  variantId: string,
  data: Partial<CreateProductVariantData>
): Promise<{ success: boolean; variant?: ProductVariant; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    const body: Record<string, unknown> = {}
    if (data.label !== undefined) body.label = data.label
    if (data.price !== undefined) body.price = data.price
    if (data.stock !== undefined) body.stock = data.stock
    if (data.status !== undefined) body.status = data.status
    if (data.sortOrder !== undefined) body.sortOrder = data.sortOrder
    if (data.attributes !== undefined) body.attributes = data.attributes
    return await apiRequest<{ success: boolean; variant: ProductVariant; message?: string }>(
      `/api/company/product-variants/${variantId}`,
      { method: 'PUT', body }
    )
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

/**
 * Laravel: DELETE /api/company/product-variants/:variantId
 */
export async function deleteProductVariant(variantId: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>(`/api/company/product-variants/${variantId}`, {
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
  address?: string
  logo?: File
  whatsappNumber?: string
  aiGreeting?: string
  aiTone?: string
  aiModelMode?: 'auto' | 'platform_default' | 'specific'
  aiModelId?: string | null
  aiReplyMode?: 'ai_first' | 'balanced'
  aiCredentialMode?: 'platform' | 'company' | 'company_preferred'
  defaultReplyLanguage?: string | null
  replyInCustomerLanguage?: boolean
  fallbackMessage?: string
  awayMessage?: string
  timezone?: string
  workingHours?: Record<string, string>
  learnFromConversations?: boolean
  agentCommerceEnabled?: boolean
  agentProactiveEnabled?: boolean
  agentVoiceReplyEnabled?: boolean
  agentMorningBriefWhatsappEnabled?: boolean
  ownerWhatsappPhone?: string | null
  agentBusinessGoals?: string[]
  businessDna?: BusinessDnaSettings | null
  digitalTwin?: Record<string, string> | null
  agentCouncilEnabled?: boolean
  autoReplyEnabled?: boolean
  notificationsEnabled?: boolean
  ordersAcceptMpesa?: boolean
  ordersAcceptStripe?: boolean
  ordersAcceptPaystack?: boolean
  attributionRetentionDays?: number | null
  ordersCollectPaymentEnabled?: boolean
  orderPaymentManualInstructions?: string | null
  orderPaymentMpesaConfig?: {
    type?: 'paybill' | 'till'
    shortcode?: string
    passkey?: string
    consumer_key?: string
    consumer_secret?: string
    env?: 'sandbox' | 'production'
  } | null
  orderPaymentStripeConfig?: {
    secret?: string
    currency?: string
  } | null
  /** ISO 4217 (3 letters), e.g. USD, KES — shown in dashboard and WhatsApp */
  displayCurrency?: string
  industry?: 'retail' | 'restaurant' | 'services' | 'other'
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

/** Payload for connecting WhatsApp via Meta Cloud API */
export interface ConnectWhatsAppPayload {
  phoneNumberId: string
  accessToken: string
  displayPhoneNumber?: string
  whatsappBusinessAccountId?: string
  /** Existing Meta two-step verification PIN (6 digits), if already set on the number */
  registrationPin?: string
}

/**
 * Connect WhatsApp via Meta Cloud API credentials (Phone Number ID + permanent access token).
 * Laravel: POST /api/company/whatsapp/connect
 */
export async function connectWhatsApp(payload: ConnectWhatsAppPayload): Promise<{
  success: boolean
  message?: string
  phoneNumberId?: string | null
  displayPhoneNumber?: string | null
  onboardingStatus?: string | null
}> {
  if (useMockApi()) {
    await delay(500)
    return { success: true, message: 'WhatsApp connected.', phoneNumberId: payload.phoneNumberId }
  }
  try {
    return await apiRequest('/api/company/whatsapp/connect', {
      method: 'POST',
      body: payload,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/** WhatsApp connection status from backend */
export interface WhatsAppStatus {
  connected: boolean
  phoneNumberId: string | null
  displayPhoneNumber: string | null
  onboardingStatus?: string | null
  displayNameStatus?: string | null
  qualityRating?: string | null
  webhookSubscribed?: boolean
  phoneRegistered?: boolean
  creditLineShared?: boolean
  onboardingError?: string | null
  embeddedSignupEnabled?: boolean
  manualConnectEnabled?: boolean
  webhookUrl?: string | null
  metaBillingModel?: 'tech_provider' | 'solution_partner'
  metaBillingModelLabel?: string | null
  requiresMetaPaymentMethod?: boolean
  platformBillingReady?: boolean
}

export interface WhatsAppEmbeddedConfig {
  enabled: boolean
  appId: string | null
  configId: string | null
  graphVersion: string
  enableCoexist?: boolean
  webhookUrl?: string | null
  metaBillingModel?: 'tech_provider' | 'solution_partner'
  requiresMetaPaymentMethod?: boolean
  platformBillingReady?: boolean
}

export interface WhatsAppTemplate {
  id: string
  metaTemplateId?: string | null
  name: string
  language: string
  category: string
  status: string
  bodyPreview?: string | null
  rejectionReason?: string | null
  updatedAt?: string | null
}

export interface CompleteEmbeddedSignupPayload {
  code: string
  phoneNumberId?: string
  whatsappBusinessAccountId?: string
  displayPhoneNumber?: string
}

export async function getWhatsAppStatus(): Promise<WhatsAppStatus> {
  if (useMockApi()) {
    await delay(300)
    return { connected: false, phoneNumberId: null, displayPhoneNumber: null }
  }
  const res = await apiRequest<WhatsAppStatus>('/api/company/whatsapp/status', { method: 'GET' })
  return res
}

export async function disconnectWhatsApp(): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(500)
    return { success: true, message: 'WhatsApp disconnected.' }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>('/api/company/whatsapp/disconnect', { method: 'POST' })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function getWhatsAppEmbeddedConfig(): Promise<WhatsAppEmbeddedConfig> {
  if (useMockApi()) {
    await delay(200)
    return { enabled: false, appId: null, configId: null, graphVersion: 'v22.0' }
  }
  return apiRequest<WhatsAppEmbeddedConfig>('/api/company/whatsapp/embedded/config', { method: 'GET' })
}

export async function listWhatsAppTemplates(): Promise<WhatsAppTemplate[]> {
  if (useMockApi()) return []
  return apiRequest<WhatsAppTemplate[]>('/api/company/whatsapp/templates', { method: 'GET' })
}

export async function createWhatsAppTemplate(payload: {
  name: string
  body: string
  language?: string
  category?: 'utility' | 'marketing' | 'authentication'
}): Promise<{ success: boolean; message?: string; template?: WhatsAppTemplate }> {
  if (useMockApi()) return { success: true, message: 'Template submitted.' }
  try {
    return await apiRequest('/api/company/whatsapp/templates', { method: 'POST', body: payload })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function syncWhatsAppTemplates(): Promise<{ success: boolean; message?: string; count?: number }> {
  if (useMockApi()) return { success: true, message: 'Synced.', count: 0 }
  try {
    return await apiRequest('/api/company/whatsapp/templates/sync', { method: 'POST' })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function deleteWhatsAppTemplate(id: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) return { success: true, message: 'Deleted.' }
  try {
    return await apiRequest(`/api/company/whatsapp/templates/${id}`, { method: 'DELETE' })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function getWhatsAppCampaignAudience(segment?: 'all' | 'recent' | 'inactive' | 'ordered'): Promise<{ uniqueCustomers: number; segment?: string; note?: string }> {
  if (useMockApi()) return { uniqueCustomers: 0 }
  const path = segment ? `/api/company/whatsapp/campaign/audience?segment=${segment}` : '/api/company/whatsapp/campaign/audience'
  return apiRequest(path)
}

export async function sendWhatsAppCampaign(data: {
  mode: 'template' | 'image'
  templateName?: string
  languageCode?: string
  bodyParameters?: string[]
  imageUrl?: string
  caption?: string
  segment?: 'all' | 'recent' | 'inactive' | 'ordered'
}): Promise<{ success: boolean; sent?: number; failed?: number; total?: number; message?: string; errors?: string[] }> {
  if (useMockApi()) return { success: true, sent: 0, total: 0, message: 'Mock send.' }
  try {
    return await apiRequest('/api/company/whatsapp/campaign/send', { method: 'POST', body: data })
  } catch (e) {
    return handleApiError(e)
  }
}

export type WhatsAppCampaignSegment = 'all' | 'recent' | 'inactive' | 'ordered'

export interface WhatsAppCampaignRecord {
  id: string
  name: string
  status: string
  segment: WhatsAppCampaignSegment
  templateName: string | null
  languageCode: string
  posterUrl: string | null
  caption: string | null
  totalRecipients: number
  sentCount: number
  failedCount: number
  pendingCount?: number | null
  startedAt?: string | null
  completedAt?: string | null
  errorSummary?: string | null
  createdAt?: string
}

export interface WhatsAppCampaignGrowthPost {
  id: string
  title: string | null
  content: string
  platform: string
  mediaUrls: string[]
  status: string
}

export async function getWhatsAppCampaignLimits(): Promise<{ campaignsUsed: number; campaignsLimit: number; recipientsLimit: number }> {
  if (useMockApi()) return { campaignsUsed: 0, campaignsLimit: 10, recipientsLimit: 1000 }
  return apiRequest('/api/company/whatsapp/campaign/limits')
}

export async function listWhatsAppCampaigns(): Promise<WhatsAppCampaignRecord[]> {
  if (useMockApi()) return []
  return apiRequest('/api/company/whatsapp/campaigns')
}

export async function createWhatsAppCampaign(data: {
  name?: string
  segment?: WhatsAppCampaignSegment
  templateName?: string
  caption?: string
  posterUrl?: string
  socialPostId?: string
}): Promise<{ success: boolean; campaign?: WhatsAppCampaignRecord; message?: string }> {
  if (useMockApi()) return { success: true, campaign: { id: '1', name: 'Mock', status: 'draft', segment: 'all', templateName: null, languageCode: 'en', posterUrl: null, caption: null, totalRecipients: 0, sentCount: 0, failedCount: 0 } }
  try {
    const res = await apiRequest<{ campaign: WhatsAppCampaignRecord }>('/api/company/whatsapp/campaigns', { method: 'POST', body: data })
    return { success: true, campaign: res.campaign }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function updateWhatsAppCampaign(
  campaignId: string,
  data: Partial<{
    name: string
    segment: WhatsAppCampaignSegment
    templateName: string
    caption: string
    posterUrl: string
    socialPostId: string
  }>
): Promise<{ success: boolean; campaign?: WhatsAppCampaignRecord; message?: string }> {
  if (useMockApi()) return { success: true }
  try {
    const res = await apiRequest<{ campaign: WhatsAppCampaignRecord }>(`/api/company/whatsapp/campaigns/${campaignId}`, { method: 'PATCH', body: data })
    return { success: true, campaign: res.campaign }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function getWhatsAppCampaign(campaignId: string): Promise<WhatsAppCampaignRecord> {
  return apiRequest(`/api/company/whatsapp/campaigns/${campaignId}`)
}

export async function uploadWhatsAppCampaignPoster(
  campaignId: string,
  file: File
): Promise<{ success: boolean; url?: string; campaign?: WhatsAppCampaignRecord; message?: string }> {
  if (useMockApi()) return { success: true, url: 'https://example.com/poster.png' }
  const form = new FormData()
  form.append('image', file)
  try {
    const res = await apiRequest<{ url: string; campaign: WhatsAppCampaignRecord }>(`/api/company/whatsapp/campaigns/${campaignId}/poster`, { method: 'POST', body: form })
    return { success: true, url: res.url, campaign: res.campaign }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function generateWhatsAppCampaignCaption(data: {
  topic?: string
  tone?: string
  posterHint?: string
}): Promise<{ success: boolean; caption?: string; message?: string }> {
  if (useMockApi()) return { success: true, caption: 'Check out our latest offer — reply to order!' }
  try {
    return await apiRequest('/api/company/whatsapp/campaign/generate-caption', { method: 'POST', body: data })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function getWhatsAppCampaignGrowthPosts(): Promise<WhatsAppCampaignGrowthPost[]> {
  if (useMockApi()) return []
  return apiRequest('/api/company/whatsapp/campaign/growth-posts')
}

export async function sendWhatsAppCampaignWizard(campaignId: string): Promise<{ success: boolean; message?: string; campaign?: WhatsAppCampaignRecord }> {
  if (useMockApi()) return { success: true, message: 'Queued' }
  try {
    const res = await apiRequest<{ message: string; campaign: WhatsAppCampaignRecord }>(`/api/company/whatsapp/campaigns/${campaignId}/send`, { method: 'POST', body: {} })
    return { success: true, message: res.message, campaign: res.campaign }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function testWhatsAppCampaign(campaignId: string, phone: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) return { success: true, message: 'Test sent' }
  try {
    return await apiRequest(`/api/company/whatsapp/campaigns/${campaignId}/test`, { method: 'POST', body: { phone } })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function exportLearningSamples(): Promise<Blob> {
  const res = await fetch(apiUrl('/api/company/learning/export'), { credentials: 'include' })
  if (!res.ok) throw new Error('Export failed')
  return res.blob()
}

export interface AdminWhatsAppConnection {
  id: string
  companyId: string
  companyName?: string
  companyEmail?: string
  displayPhoneNumber?: string | null
  status: string
  onboardingStatus?: string
  onboardingError?: string | null
  metaBillingModel?: string | null
  creditLineSharedAt?: string | null
  connectedAt?: string | null
}

export async function getAdminWhatsAppConnections(): Promise<{
  connections: AdminWhatsAppConnection[]
  platform: {
    embeddedSignupEnabled: boolean
    embeddedSignupReady?: boolean
    manualConnectEnabled?: boolean
    webhookUrl: string
    graphVersion: string
    billingModel?: string
    billingModelLabel?: string
    solutionPartnerReady?: boolean
  }
}> {
  if (useMockApi()) {
    return {
      connections: [],
      platform: { embeddedSignupEnabled: false, manualConnectEnabled: true, webhookUrl: '', graphVersion: 'v22.0' },
    }
  }
  return apiRequest('/api/admin/whatsapp/connections', { method: 'GET' })
}

export async function completeWhatsAppEmbeddedSignup(
  payload: CompleteEmbeddedSignupPayload
): Promise<{ success: boolean; message?: string; phoneNumberId?: string | null }> {
  if (useMockApi()) {
    await delay(1500)
    return { success: true, message: 'WhatsApp connected via embedded signup.', phoneNumberId: payload.phoneNumberId ?? null }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string; phoneNumberId?: string | null }>(
      '/api/company/whatsapp/embedded/complete',
      { method: 'POST', body: payload }
    )
  } catch (e) {
    return handleApiError(e)
  }
}

// ============================================
// SUPER ADMIN ACTIONS
// ============================================

export interface PlanEntitlements {
  messages?: number | null
  messagesUnlimited?: boolean
  team?: number
  whatsappNumbers?: number
  aiCostUsd?: number | null
  aiModelModes?: string[]
  allowByok?: boolean
  credentialModes?: string[]
  apiAccess?: boolean
  analytics?: boolean
  attribution?: boolean
  aiPostsPerMonth?: number
  aiImagesPerMonth?: number
  socialPlatforms?: number
  growthEnabled?: boolean
}

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
  entitlements?: PlanEntitlements
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
  entitlements?: PlanEntitlements
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

export interface AdminUpdateSubscriptionPayload {
  status: 'active' | 'trial' | 'cancelled' | 'expired'
  plan?: string
  billingCycle?: 'monthly' | 'yearly'
}

/**
 * Update a company subscription (admin only): assign plan, trial/active, cancel, or expire.
 * Laravel: PATCH /api/admin/subscriptions/:subscriptionId
 */
export async function adminUpdateSubscription(
  subscriptionId: string,
  payload: AdminUpdateSubscriptionPayload
): Promise<{ success: boolean; subscription?: Subscription; message?: string }> {
  if (useMockApi()) {
    await delay(500)
    const idx = mockSubscriptions.findIndex((s) => s.id === subscriptionId)
    if (idx === -1) {
      return { success: false, message: 'Subscription not found.' }
    }
    const cur = mockSubscriptions[idx]
    if (payload.status === 'cancelled' || payload.status === 'expired') {
      mockSubscriptions[idx] = { ...cur, status: payload.status === 'expired' ? 'expired' : 'cancelled' }
    } else if (payload.plan) {
      mockSubscriptions[idx] = {
        ...cur,
        plan: payload.plan as Subscription['plan'],
        status: payload.status,
        billingCycle: payload.billingCycle ?? cur.billingCycle,
        amount: payload.status === 'trial' ? 0 : cur.amount,
      }
    }
    return { success: true, message: 'Subscription updated.', subscription: mockSubscriptions[idx] }
  }
  try {
    return await apiRequest<{ success: boolean; subscription: Subscription; message?: string }>(
      `/api/admin/subscriptions/${subscriptionId}`,
      { method: 'PATCH', body: payload }
    )
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

// ——— Admin Subscription Offers (coupons) ———

export interface SubscriptionOffer {
  id: string
  name: string
  code: string
  description?: string | null
  discountType: 'percent' | 'fixed'
  discountValue: number
  currency?: string | null
  planId?: string | null
  planName?: string | null
  maxRedemptions?: number | null
  redemptionCount: number
  maxPerCompany: number
  startsAt?: string | null
  endsAt?: string | null
  isActive: boolean
  firstPaymentOnly: boolean
  isCurrentlyValid: boolean
}

export interface SubscriptionOfferPayload {
  name: string
  code: string
  description?: string | null
  discountType: 'percent' | 'fixed'
  discountValue: number
  currency?: string | null
  planId?: string | null
  maxRedemptions?: number | null
  maxPerCompany?: number | null
  startsAt?: string | null
  endsAt?: string | null
  isActive?: boolean
  firstPaymentOnly?: boolean
}

export async function createSubscriptionOffer(
  payload: SubscriptionOfferPayload
): Promise<{ success: boolean; offer?: SubscriptionOffer; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true, offer: { id: '1', redemptionCount: 0, maxPerCompany: 1, isCurrentlyValid: true, firstPaymentOnly: true, isActive: true, ...payload } as SubscriptionOffer }
  }
  try {
    return await apiRequest('/api/admin/subscription-offers', { method: 'POST', body: payload })
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

export async function updateSubscriptionOffer(
  id: string,
  payload: SubscriptionOfferPayload
): Promise<{ success: boolean; offer?: SubscriptionOffer; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    return await apiRequest(`/api/admin/subscription-offers/${id}`, { method: 'PUT', body: payload })
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

export async function deleteSubscriptionOffer(
  id: string
): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(300)
    return { success: true }
  }
  try {
    return await apiRequest(`/api/admin/subscription-offers/${id}`, { method: 'DELETE' })
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

export async function previewCoupon(
  planId: string,
  couponCode: string,
  currency?: string
): Promise<{
  success: boolean
  message?: string
  code?: string
  originalAmount?: number
  discountAmount?: number
  finalAmount?: number
  currency?: string
}> {
  if (useMockApi()) {
    await delay(300)
    return { success: true, code: couponCode, originalAmount: 99, discountAmount: 10, finalAmount: 89, currency: currency ?? 'KES' }
  }
  try {
    return await apiRequest('/api/company/coupon/preview', {
      method: 'POST',
      body: { planId, couponCode, currency },
    })
  } catch (e) {
    return handleApiError(e)
  }
}

// ——— Admin Testimonials ———

export interface CreateTestimonialData {
  name: string
  role?: string
  content: string
  rating?: number
  sortOrder?: number
  isActive?: boolean
}

export interface UpdateTestimonialData {
  name?: string
  role?: string
  content?: string
  rating?: number
  sortOrder?: number
  isActive?: boolean
}

export async function createTestimonial(data: CreateTestimonialData): Promise<{ success: boolean; testimonial?: { id: string; name: string; role: string; content: string; rating: number; sortOrder: number; isActive: boolean }; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true, testimonial: { id: String(Date.now()), name: data.name, role: data.role ?? '', content: data.content, rating: data.rating ?? 5, sortOrder: data.sortOrder ?? 0, isActive: data.isActive ?? true } }
  }
  try {
    const res = await apiRequest<{ success: boolean; testimonial: { id: string; name: string; role: string; content: string; rating: number; sortOrder: number; isActive: boolean } }>('/api/admin/testimonials', { method: 'POST', body: data })
    return res
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

export async function updateTestimonial(testimonialId: string, data: UpdateTestimonialData): Promise<{ success: boolean; testimonial?: unknown; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    return await apiRequest<{ success: boolean; testimonial: unknown }>(`/api/admin/testimonials/${testimonialId}`, { method: 'PUT', body: data })
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

export async function deleteTestimonial(testimonialId: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(300)
    return { success: true }
  }
  try {
    return await apiRequest<{ success: boolean }>(`/api/admin/testimonials/${testimonialId}`, { method: 'DELETE' })
  } catch (e) {
    return handleApiError(e)
  }
}

export type BlogPostPayload = {
  title: string
  slug?: string
  excerpt?: string | null
  body: string
  coverImage?: string | null
  metaTitle?: string | null
  metaDescription?: string | null
  ogImage?: string | null
  publishedAt?: string | null
  isPublished?: boolean
}

export async function createBlogPost(
  data: BlogPostPayload
): Promise<{ success: boolean; post?: unknown; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true, post: { id: String(Date.now()), ...data } }
  }
  try {
    return await apiRequest('/api/admin/blog-posts', { method: 'POST', body: data })
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

export async function updateBlogPost(
  id: string,
  data: Partial<BlogPostPayload>
): Promise<{ success: boolean; post?: unknown; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    return await apiRequest(`/api/admin/blog-posts/${id}`, { method: 'PUT', body: data })
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

export async function deleteBlogPost(id: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(300)
    return { success: true }
  }
  try {
    return await apiRequest(`/api/admin/blog-posts/${id}`, { method: 'DELETE' })
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

// ——— Admin Landing FAQs ———

export interface CreateLandingFaqData {
  question: string
  answer: string
  sortOrder?: number
  isActive?: boolean
}

export interface UpdateLandingFaqData {
  question?: string
  answer?: string
  sortOrder?: number
  isActive?: boolean
}

export async function createLandingFaq(data: CreateLandingFaqData): Promise<{ success: boolean; faq?: { id: string; question: string; answer: string; sortOrder: number; isActive: boolean }; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true, faq: { id: String(Date.now()), question: data.question, answer: data.answer, sortOrder: data.sortOrder ?? 0, isActive: data.isActive ?? true } }
  }
  try {
    const res = await apiRequest<{ success: boolean; faq: { id: string; question: string; answer: string; sortOrder: number; isActive: boolean } }>('/api/admin/landing-faqs', { method: 'POST', body: data })
    return res
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

export async function updateLandingFaq(faqId: string, data: UpdateLandingFaqData): Promise<{ success: boolean; faq?: unknown; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    return await apiRequest<{ success: boolean; faq: unknown }>(`/api/admin/landing-faqs/${faqId}`, { method: 'PUT', body: data })
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

export async function deleteLandingFaq(faqId: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(300)
    return { success: true }
  }
  try {
    return await apiRequest<{ success: boolean }>(`/api/admin/landing-faqs/${faqId}`, { method: 'DELETE' })
  } catch (e) {
    return handleApiError(e)
  }
}

// ============================================
// ADMIN CMS
// ============================================

export async function updateCmsPage(
  slug: string,
  data: {
    title?: string
    metaTitle?: string
    metaDescription?: string
    ogImage?: string | null
    ogTitle?: string | null
    ogDescription?: string | null
    canonicalUrl?: string | null
    robots?: string | null
    isPublished?: boolean
  }
): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(300)
    return { success: true }
  }
  try {
    return await apiRequest(`/api/admin/cms/pages/${slug}`, { method: 'PUT', body: data })
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

export async function updateCmsSection(
  slug: string,
  sectionKey: string,
  data: { isEnabled?: boolean; sortOrder?: number; content?: Record<string, unknown> }
): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(300)
    return { success: true }
  }
  try {
    return await apiRequest(`/api/admin/cms/pages/${slug}/sections/${sectionKey}`, {
      method: 'PUT',
      body: data,
    })
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

export async function reorderCmsSections(
  slug: string,
  orders: Array<{ key: string; sortOrder: number }>
): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(300)
    return { success: true }
  }
  try {
    return await apiRequest(`/api/admin/cms/pages/${slug}/sections-reorder`, {
      method: 'PUT',
      body: { orders },
    })
  } catch (e) {
    return { ...handleApiError(e), success: false }
  }
}

export async function uploadCmsImage(file: File): Promise<{ success: boolean; url?: string; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true, url: URL.createObjectURL(file) }
  }
  try {
    const form = new FormData()
    form.append('image', file)
    return await apiRequest<{ success: boolean; url: string }>('/api/admin/cms/upload-image', {
      method: 'POST',
      body: form,
    })
  } catch (e) {
    return { ...handleApiError(e), success: false }
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
 * Initiate M-Pesa STK push for subscription (company user). User completes payment on phone.
 * Laravel: POST /api/company/mpesa/initiate
 */
export async function createMpesaCheckout(
  planId: string,
  phone: string,
  couponCode?: string
): Promise<{ success: boolean; checkoutRequestId?: string; message?: string; amount?: number }> {
  if (useMockApi()) {
    await delay(800)
    return { success: true, checkoutRequestId: 'mock-req-'.concat(planId), message: 'Enter your M-Pesa PIN on your phone.' }
  }
  try {
    const res = await apiRequest<{ checkoutRequestId: string; message: string; amount?: number }>('/api/company/mpesa/initiate', {
      method: 'POST',
      body: { planId, phone, ...(couponCode ? { couponCode } : {}) },
    })
    return { success: true, checkoutRequestId: res.checkoutRequestId, message: res.message, amount: res.amount }
  } catch (e) {
    return { success: false, message: e instanceof Error ? e.message : 'M-Pesa initiation failed' }
  }
}

/**
 * Initialize Paystack checkout for subscription (company user). Returns redirect URL.
 * Laravel: POST /api/company/paystack/initialize
 */
export async function createPaystackCheckout(
  planId: string,
  options?: { callbackUrl?: string; couponCode?: string }
): Promise<{ success: boolean; url?: string; reference?: string; message?: string; amount?: number }> {
  if (useMockApi()) {
    await delay(600)
    return { success: true, url: 'https://checkout.paystack.com/mock-placeholder', reference: 'mock-ref-' + planId }
  }
  try {
    const res = await apiRequest<{ authorizationUrl: string; reference: string; amount?: number }>('/api/company/paystack/initialize', {
      method: 'POST',
      body: { planId, ...options },
    })
    return { success: true, url: res.authorizationUrl, reference: res.reference, amount: res.amount }
  } catch (e) {
    return { success: false, message: e instanceof Error ? e.message : 'Paystack checkout failed' }
  }
}

/**
 * Verify Paystack payment after redirect (webhook fallback).
 * Laravel: POST /api/company/paystack/verify
 */
export async function verifyPaystackCheckout(
  reference: string
): Promise<{ success: boolean; message?: string; alreadyProcessed?: boolean }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true, alreadyProcessed: false }
  }
  try {
    const res = await apiRequest<{ success: boolean; message?: string; alreadyProcessed?: boolean }>(
      '/api/company/paystack/verify',
      { method: 'POST', body: { reference } }
    )
    return {
      success: !!res.success,
      message: res.message,
      alreadyProcessed: res.alreadyProcessed,
    }
  } catch (e) {
    return { success: false, message: e instanceof Error ? e.message : 'Paystack verification failed' }
  }
}

/**
 * Cancel local (Paystack / M-Pesa / trial) subscription. Stripe users should use billing portal.
 * Laravel: POST /api/company/subscription/cancel
 */
export async function cancelSubscription(): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true, message: 'Subscription cancelled.' }
  }
  try {
    const res = await apiRequest<{ success: boolean; message?: string }>('/api/company/subscription/cancel', {
      method: 'POST',
      body: {},
    })
    return { success: !!res.success, message: res.message }
  } catch (e) {
    return { success: false, message: e instanceof Error ? e.message : 'Could not cancel subscription' }
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
 * Get single company (admin only)
 * Laravel: GET /api/admin/companies/:companyId
 */
export async function getAdminCompany(companyId: string): Promise<{ success: boolean; company?: Company; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return {
      success: true,
      company: {
        id: companyId,
        name: 'Demo Company',
        email: 'contact@demo.com',
        phone: '+1 555-0100',
        plan: 'starter',
        status: 'active',
        totalChats: 0,
        totalOrders: 0,
        createdAt: new Date().toISOString().slice(0, 10),
      } as Company,
    }
  }
  try {
    const company = await apiRequest<Company>(`/api/admin/companies/${companyId}`)
    return { success: true, company }
  } catch (e) {
    return { success: false, message: e instanceof Error ? e.message : 'Failed to load company' }
  }
}

export interface UpdateAdminCompanyData {
  name?: string
  email?: string
  phone?: string
  plan?: Company['plan']
  status?: Company['status']
  isGrowthPilot?: boolean
  growthDemoMode?: boolean
}

/**
 * Update company (admin only)
 * Laravel: PUT /api/admin/companies/:companyId
 */
export async function updateAdminCompany(
  companyId: string,
  data: UpdateAdminCompanyData
): Promise<{ success: boolean; company?: Company; message?: string }> {
  if (useMockApi()) {
    await delay(600)
    return { success: true, message: 'Company updated successfully' }
  }
  try {
    const res = await apiRequest<{ success: boolean; company: Company }>(`/api/admin/companies/${companyId}`, {
      method: 'PUT',
      body: data,
    })
    return { success: true, company: res.company }
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

/**
 * Reset user password (admin only)
 * Laravel: POST /api/admin/users/:userId/reset-password
 */
export async function adminResetUserPassword(
  userId: string,
  password: string,
  confirmPassword: string
): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(600)
    if (password !== confirmPassword) return { success: false, message: 'Passwords do not match' }
    return { success: true, message: 'Password updated successfully.' }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>(`/api/admin/users/${userId}/reset-password`, {
      method: 'POST',
      body: { password, confirmPassword },
    })
  } catch (e) {
    return { success: false, message: e instanceof Error ? e.message : 'Failed to reset password' }
  }
}

/**
 * Impersonate a user (admin only). Returns token and user to log in as that user.
 * Laravel: POST /api/admin/impersonate/user/:userId
 */
export async function adminImpersonateUser(userId: string): Promise<{
  success: boolean
  token?: string
  user?: User
  message?: string
}> {
  if (useMockApi()) {
    await delay(500)
    return {
      success: true,
      token: 'mock-impersonation-token',
      user: {
        id: userId,
        name: 'Demo Impersonated User',
        email: 'impersonated@demo.com',
        role: 'company_owner',
        companyId: '1',
        companyName: 'Demo Company',
        status: 'active',
        lastLogin: new Date().toISOString(),
        createdAt: new Date().toISOString().slice(0, 10),
      } as User,
    }
  }
  try {
    const res = await apiRequest<{ success: boolean; token: string; user: User }>(
      `/api/admin/impersonate/user/${userId}`,
      { method: 'POST' }
    )
    return res
  } catch (e) {
    return { success: false, message: e instanceof Error ? e.message : 'Impersonation failed' }
  }
}

/**
 * Impersonate a company (admin only). Returns token and user for first user of company.
 * Laravel: POST /api/admin/impersonate/company/:companyId
 */
export async function adminImpersonateCompany(companyId: string): Promise<{
  success: boolean
  token?: string
  user?: User
  message?: string
}> {
  if (useMockApi()) {
    await delay(500)
    return {
      success: true,
      token: 'mock-impersonation-token',
      user: {
        id: '1',
        name: 'Demo Company User',
        email: 'owner@demo.com',
        role: 'company_owner',
        companyId,
        companyName: 'Demo Company',
        status: 'active',
        lastLogin: new Date().toISOString(),
        createdAt: new Date().toISOString().slice(0, 10),
      } as User,
    }
  }
  try {
    const res = await apiRequest<{ success: boolean; token: string; user: User }>(
      `/api/admin/impersonate/company/${companyId}`,
      { method: 'POST' }
    )
    return res
  } catch (e) {
    return { success: false, message: e instanceof Error ? e.message : 'Impersonation failed' }
  }
}

export interface AiLearningConfig {
  learningEnabled?: boolean
  defaultLearnFromChats?: boolean
  allowCompanyOverride?: boolean
  maxSamplesPerCompany?: number
  promptSampleLimit?: number
  retentionDays?: number
  piiRedactionEnabled?: boolean
  storeFaqExchanges?: boolean
  storeAgentReplies?: boolean
  faqEmbeddingsEnabled?: boolean
  learningEmbeddingsEnabled?: boolean
  faqSemanticMinScore?: number
  learningSemanticMinScore?: number
  faqDirectMinScore?: number
  minReplyLength?: number
  maxPromptTokens?: number
  embeddingModelKey?: string
  requireLearningReview?: boolean
  autoDetectLanguage?: boolean
  fallbackLanguage?: string
  aiCostMarkupPercent?: number
}

export interface PlatformSettings {
  platformName?: string | null
  primaryColor?: string | null
  secondaryColor?: string | null
  appLogo?: string | null
  supportEmail?: string | null
  maintenanceMode?: boolean
  defaultTimezone?: string | null
  maintenanceMessage?: string | null
  allowNewRegistrations?: boolean
  requireEmailVerification?: boolean
  aiModel?: string | null
  maxTokensPerRequest?: number | null
  rateLimitPerMinute?: number | null
  smtpHost?: string | null
  smtpPort?: number | null
  smtpEncryption?: string | null
  smtpUser?: string | null
  smtpPassword?: string | null
  fromEmail?: string | null
  fromName?: string | null
  whatsappWebhookVerifyToken?: string | null
  metaAppSecret?: string | null
  whatsappEmbeddedAppId?: string | null
  whatsappEmbeddedConfigId?: string | null
  whatsappEmbeddedAppSecret?: string | null
  whatsappEmbeddedRedirectUri?: string | null
  whatsappEnableCoexist?: boolean
  whatsappEmbeddedSignupEnabled?: boolean
  whatsappManualConnectEnabled?: boolean
  whatsappBillingModel?: 'tech_provider' | 'solution_partner'
  whatsappBillingModelLabel?: string | null
  whatsappExtendedCreditLineId?: string | null
  whatsappCreditSharingSystemToken?: string | null
  whatsappWabaCurrency?: string | null
  whatsappSolutionPartnerReady?: boolean
  whatsappBillingRequiresMetaPayment?: boolean
  whatsappWebhookUrl?: string | null
  whatsappEmbeddedSignupReady?: boolean
  whatsappEmbeddedSignupActive?: boolean
  openaiApiKey?: string | null
  openaiModel?: string | null
  openaiMaxTokens?: number | null
  sessionTimeoutMinutes?: number | null
  maxLoginAttempts?: number | null
  passwordMinLength?: number | null
  require2fa?: boolean
  ipAllowlistEnabled?: boolean
  auditLoggingEnabled?: boolean
  notifyNewRegistrations?: boolean
  notifyFailedPayments?: boolean
  notifySecurityAlerts?: boolean
  notifySystemErrors?: boolean
  notifyUsageAlerts?: boolean
  notifyDailySummary?: boolean
  landingTrustedCompanies?: string[]
  aiLearningConfig?: AiLearningConfig
}

export interface UpdatePlatformSettingsData {
  platformName?: string
  primaryColor?: string
  secondaryColor?: string
  logo?: File
  supportEmail?: string
  maintenanceMode?: boolean
  defaultTimezone?: string
  maintenanceMessage?: string
  allowNewRegistrations?: boolean
  requireEmailVerification?: boolean
  aiModel?: string
  maxTokensPerRequest?: number
  rateLimitPerMinute?: number
  smtpHost?: string
  smtpPort?: number
  smtpEncryption?: string
  smtpUser?: string
  smtpPassword?: string
  fromEmail?: string
  fromName?: string
  whatsappWebhookVerifyToken?: string
  metaAppSecret?: string
  whatsappEmbeddedAppId?: string
  whatsappEmbeddedConfigId?: string
  whatsappEmbeddedAppSecret?: string
  whatsappEmbeddedRedirectUri?: string
  whatsappEnableCoexist?: boolean
  whatsappEmbeddedSignupEnabled?: boolean
  whatsappManualConnectEnabled?: boolean
  whatsappBillingModel?: 'tech_provider' | 'solution_partner'
  whatsappExtendedCreditLineId?: string
  whatsappCreditSharingSystemToken?: string
  whatsappWabaCurrency?: string
  openaiApiKey?: string
  openaiModel?: string
  openaiMaxTokens?: number
  sessionTimeoutMinutes?: number
  maxLoginAttempts?: number
  passwordMinLength?: number
  require2fa?: boolean
  ipAllowlistEnabled?: boolean
  auditLoggingEnabled?: boolean
  notifyNewRegistrations?: boolean
  notifyFailedPayments?: boolean
  notifySecurityAlerts?: boolean
  notifySystemErrors?: boolean
  notifyUsageAlerts?: boolean
  notifyDailySummary?: boolean
  landingTrustedCompanies?: string[]
  aiLearningConfig?: AiLearningConfig
}

/** Public app branding (name, logo, colors) for theme/invoices. GET /api/app-branding */
export interface AppBranding {
  applicationName: string
  appLogo: string | null
  primaryColor: string | null
  secondaryColor: string | null
  requireEmailVerification?: boolean
}

/**
 * Get platform settings (admin only)
 * Laravel: GET /api/admin/settings
 */
export interface AdminAiLearningStats {
  config: AiLearningConfig
  stats: {
    totalLearningSamples: number
    pendingReviewSamples?: number
    companiesWithSamples: number
    activeFaqs: number
    faqsWithEmbeddings: number
    embeddingCoveragePercent: number
    approvedLearningSamples?: number
    learningSamplesWithEmbeddings?: number
    learningEmbeddingCoveragePercent?: number
    learningQuality?: { deadSamples: number; lowQualitySamples: number }
    samplesBySource: Record<string, number>
    topCompaniesBySamples: Array<{ companyId: string; companyName: string; samples: number }>
    oldestSampleAt?: string | null
    newestSampleAt?: string | null
  }
}

export async function getAdminAiLearningStats(): Promise<AdminAiLearningStats> {
  return apiRequest<AdminAiLearningStats>('/api/admin/ai-learning/stats')
}

export async function purgeAiLearningSamples(data: {
  confirm: string
  companyId?: number
}): Promise<{ success: boolean; deleted?: number; message?: string }> {
  try {
    return await apiRequest('/api/admin/ai-learning/purge', { method: 'POST', body: data })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function pruneExpiredAiLearningSamples(): Promise<{ success: boolean; deleted?: number; message?: string }> {
  try {
    return await apiRequest('/api/admin/ai-learning/prune-expired', { method: 'POST' })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function syncFaqEmbeddingsAdmin(companyId?: number): Promise<{ success: boolean; message?: string }> {
  try {
    return await apiRequest('/api/admin/ai-learning/sync-faq-embeddings', {
      method: 'POST',
      body: companyId ? { companyId } : {},
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function syncLearningEmbeddingsAdmin(companyId?: number): Promise<{ success: boolean; message?: string }> {
  try {
    return await apiRequest('/api/admin/ai-learning/sync-learning-embeddings', {
      method: 'POST',
      body: companyId ? { companyId } : {},
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function syncProductEmbeddingsAdmin(companyId?: number): Promise<{ success: boolean; message?: string }> {
  try {
    return await apiRequest('/api/admin/ai-learning/sync-product-embeddings', {
      method: 'POST',
      body: companyId ? { companyId } : {},
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function submitMessageLearningFeedback(
  chatId: string,
  messageId: string,
  feedback: -1 | 1,
): Promise<{ success: boolean; message?: string; learningFeedback?: number }> {
  try {
    return await apiRequest(`/api/company/chats/${chatId}/messages/${messageId}/learning-feedback`, {
      method: 'POST',
      body: { feedback },
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export interface AiLearningSampleRow {
  id: string
  companyId: string
  companyName: string
  customerMessage: string
  assistantReply: string
  source: string
  status: string
  language?: string | null
  createdAt?: string
  reviewNotes?: string | null
}

export async function listAiLearningSamples(params?: {
  status?: string
  companyId?: number
  perPage?: number
}): Promise<{ samples: AiLearningSampleRow[]; meta: { currentPage: number; lastPage: number; total: number } }> {
  const q = new URLSearchParams()
  if (params?.status) q.set('status', params.status)
  if (params?.companyId) q.set('companyId', String(params.companyId))
  if (params?.perPage) q.set('perPage', String(params.perPage))
  const suffix = q.toString() ? `?${q}` : ''
  return apiRequest(`/api/admin/ai-learning/samples${suffix}`)
}

export async function reviewAiLearningSample(
  id: string,
  data: { action: 'approve' | 'reject'; reviewNotes?: string; assistantReply?: string },
): Promise<{ success: boolean; message?: string }> {
  try {
    return await apiRequest(`/api/admin/ai-learning/samples/${id}/review`, { method: 'POST', body: data })
  } catch (e) {
    return handleApiError(e)
  }
}

export interface CompanyAiProviderRow {
  slug: string
  name: string
  apiKeyConfigured: boolean
  apiBaseUrl?: string | null
  isEnabled: boolean
  effectiveKeySource: 'platform' | 'company'
}

export async function getCompanyAiProviders(): Promise<{
  credentialMode: string
  effectiveCredentialMode?: string
  aiPlanCapabilities?: {
    plan: string
    allowedModelModes: string[]
    allowByok: boolean
    allowedCredentialModes: string[]
    aiCostLimitUsd?: number | null
  }
  providers: CompanyAiProviderRow[]
}> {
  return apiRequest('/api/company/ai-providers')
}

export async function updateCompanyAiProvider(
  slug: string,
  data: { apiKey?: string; apiBaseUrl?: string; isEnabled?: boolean; credentialMode?: string },
): Promise<{ success: boolean; message?: string }> {
  try {
    return await apiRequest(`/api/company/ai-providers/${slug}`, { method: 'PUT', body: data })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function getCompanyAiUsage(period = '30d'): Promise<{
  summary: Record<string, unknown>
  byUseCase: Array<{ useCase: string; requests: number; tokens: number }>
  byCredentialSource?: Array<{ source: string; requests: number; billedCostUsd: number }>
  learningEmbeddingCoveragePercent?: number
}> {
  return apiRequest(`/api/company/ai-usage?period=${encodeURIComponent(period)}`)
}

export async function getPlatformSettings(): Promise<PlatformSettings> {
  if (useMockApi()) {
    await delay(300)
    return {
      platformName: 'RelayIQ',
      supportEmail: 'support@chatflow.ai',
      maintenanceMode: false,
      defaultTimezone: 'UTC',
      maintenanceMessage: null,
      allowNewRegistrations: true,
      smtpHost: 'smtp.sendgrid.net',
      smtpPort: 587,
      smtpEncryption: 'tls',
      smtpUser: 'apikey',
      fromEmail: 'noreply@chatflow.ai',
      fromName: 'RelayIQ',
      sessionTimeoutMinutes: 60,
      maxLoginAttempts: 5,
      passwordMinLength: 8,
      require2fa: true,
      ipAllowlistEnabled: false,
      auditLoggingEnabled: true,
      notifyNewRegistrations: true,
      notifyFailedPayments: true,
      notifySecurityAlerts: true,
      notifySystemErrors: true,
      notifyUsageAlerts: true,
      notifyDailySummary: true,
    }
  }
  return apiRequest<PlatformSettings>('/api/admin/settings')
}

/**
 * Update platform settings (admin only). Use FormData when logo is included.
 * Laravel: PUT /api/admin/settings (JSON or multipart with logo)
 */
export async function updatePlatformSettings(data: UpdatePlatformSettingsData): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(1500)
    return { success: true, message: 'Platform settings updated successfully' }
  }
  try {
    const hasLogo = data.logo != null
    if (hasLogo) {
      const form = new FormData()
      const { logo, ...rest } = data
      form.append('logo', logo!)
      Object.entries(rest).forEach(([k, v]) => {
        if (v === undefined || v === null) return
        form.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : String(v))
      })
      return await apiRequest<{ success: boolean; message?: string }>('/api/admin/settings', {
        method: 'POST',
        body: form,
      })
    }
    return await apiRequest<{ success: boolean; message?: string }>('/api/admin/settings', {
      method: 'PUT',
      body: data,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Get public app branding (no auth). For theme, invoices, email headers.
 * Laravel: GET /api/app-branding
 */
export async function getAppBranding(): Promise<AppBranding> {
  if (useMockApi()) {
    await delay(100)
    return {
      applicationName: 'RelayIQ',
      appLogo: null,
      primaryColor: null,
      secondaryColor: null,
      requireEmailVerification: false,
    }
  }
  const baseUrl = (import.meta.env.VITE_API_URL as string | undefined) || ''
  const res = await fetch(`${baseUrl}/api/app-branding`, { headers: { Accept: 'application/json' } })
  if (!res.ok) throw new Error('Failed to load app branding')
  return res.json()
}

/**
 * Send a test email (admin only). Uses current SMTP settings.
 * Laravel: POST /api/admin/settings/test-email
 */
export async function sendTestEmail(to: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(1500)
    return { success: true, message: `Test email sent to ${to}` }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>('/api/admin/settings/test-email', {
      method: 'POST',
      body: { to },
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Export data (admin only). Use downloadFile(downloadUrl, filename) after success to trigger download.
 * Laravel: POST /api/admin/export
 */
export async function exportData(
  dataType: 'companies' | 'users' | 'subscriptions' | 'revenue' | 'logs',
  format: 'csv' | 'json'
): Promise<{ success: boolean; downloadUrl?: string; filename?: string; message?: string }> {
  if (useMockApi()) {
    await delay(2000)
    const filename = `${dataType}-${Date.now()}.${format}`
    return {
      success: true,
      downloadUrl: `/api/admin/export/download/${filename}`,
      filename,
      message: 'Export generated successfully',
    }
  }
  try {
    return await apiRequest<{ success: boolean; downloadUrl?: string; filename?: string; message?: string }>('/api/admin/export', {
      method: 'POST',
      body: { dataType, format },
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Company export. Use downloadFile(downloadUrl, filename) after success to trigger download.
 * Laravel: POST /api/company/export
 */
export async function companyExportData(
  dataType: 'orders' | 'products' | 'customers' | 'faqs',
  format: 'csv' | 'json'
): Promise<{ success: boolean; downloadUrl?: string; filename?: string; message?: string }> {
  if (useMockApi()) {
    await delay(2000)
    const filename = `${dataType}-${Date.now()}.${format}`
    return { success: true, downloadUrl: `/api/company/export/download/${filename}`, filename, message: 'Export generated successfully' }
  }
  try {
    return await apiRequest<{ success: boolean; downloadUrl?: string; filename?: string; message?: string }>('/api/company/export', {
      method: 'POST',
      body: { dataType, format },
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Import products from CSV (company). Columns: name, description, price, category, status, stock (optional).
 * Laravel: POST /api/company/import/products
 */
export async function importProducts(file: File): Promise<{
  success: boolean
  message?: string
  created?: number
  errors?: { row: number; errors: string[] }[]
}> {
  if (useMockApi()) {
    await delay(1500)
    return { success: true, message: 'Imported 0 product(s) (mock).', created: 0 }
  }
  const form = new FormData()
  form.append('file', file)
  try {
    return await apiRequest<{ success: boolean; message?: string; created?: number; errors?: { row: number; errors: string[] }[] }>('/api/company/import/products', {
      method: 'POST',
      body: form,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

/**
 * Import FAQs from CSV (company). Columns: question, answer, category, keywords (optional), is_active (optional).
 * Laravel: POST /api/company/import/faqs
 */
export async function importFaqs(file: File): Promise<{
  success: boolean
  message?: string
  created?: number
  errors?: { row: number; errors: string[] }[]
}> {
  if (useMockApi()) {
    await delay(1500)
    return { success: true, message: 'Imported 0 FAQ(s) (mock).', created: 0 }
  }
  const form = new FormData()
  form.append('file', file)
  try {
    return await apiRequest<{ success: boolean; message?: string; created?: number; errors?: { row: number; errors: string[] }[] }>('/api/company/import/faqs', {
      method: 'POST',
      body: form,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

// ============================================
// GROWTH ENGINE ACTIONS
// ============================================

export async function generateSmartGrowthContent(data: {
  count?: number
  platform?: string
  topic?: string
}): Promise<{ success: boolean; posts?: GrowthPost[]; message?: string; aiGenerated?: boolean; aiError?: string }> {
  if (useMockApi()) {
    await delay(1200)
    return { success: true, posts: [] }
  }
  try {
    return await apiRequest('/api/company/growth/content/generate-smart', { method: 'POST', body: data })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function extractGrowthPatterns(periodDays = 30): Promise<{ success: boolean; patterns?: unknown[]; message?: string }> {
  if (useMockApi()) {
    await delay(800)
    return { success: true, patterns: [] }
  }
  try {
    return await apiRequest('/api/company/growth/intelligence/patterns/extract', {
      method: 'POST',
      body: { periodDays },
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function applyGrowthPattern(patternId: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    return await apiRequest(`/api/company/growth/intelligence/patterns/${patternId}/apply`, { method: 'POST' })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function executeGrowthMixPlan(): Promise<{ success: boolean; posts?: GrowthPost[]; message?: string }> {
  if (useMockApi()) {
    await delay(1500)
    return { success: true, posts: [] }
  }
  try {
    return await apiRequest('/api/company/growth/intelligence/execute-mix', { method: 'POST', body: {} })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function scoreGrowthDrafts(): Promise<{ success: boolean; drafts?: unknown[]; message?: string }> {
  if (!useMockApi()) {
    return apiRequest('/api/company/growth/intelligence/score-drafts')
  }
  await delay(400)
  return { success: true, drafts: [] }
}

export async function generateGrowthContent(data: {
  count?: number
  platform?: string
  topic?: string
  audience?: string
  tone?: string
}): Promise<{ success: boolean; posts?: GrowthPost[]; message?: string; aiGenerated?: boolean; aiError?: string }> {
  if (useMockApi()) {
    await delay(1200)
    return { success: true, posts: [] }
  }
  try {
    return await apiRequest('/api/company/growth/content/generate', { method: 'POST', body: data })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function approveGrowthPost(postId: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    return await apiRequest(`/api/company/growth/posts/${postId}/approve`, { method: 'POST' })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function publishGrowthPost(postId: string): Promise<{ success: boolean; message?: string; metaError?: string }> {
  if (useMockApi()) {
    await delay(800)
    return { success: true }
  }
  try {
    return await apiRequest(`/api/company/growth/posts/${postId}/publish`, { method: 'POST' })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function getGrowthPostSharePackage(postId: string): Promise<{
  success: boolean
  trackingUrl?: string
  caption?: string
  clipboardPackage?: string
  message?: string
}> {
  if (useMockApi()) {
    await delay(200)
    return { success: true, trackingUrl: 'https://example.com/g/demo', caption: 'Demo caption', clipboardPackage: 'Demo' }
  }
  try {
    const res = await apiRequest<{ trackingUrl: string; caption: string; clipboardPackage: string }>(
      `/api/company/growth/posts/${postId}/share-package`
    )
    return { success: true, ...res }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function uploadGrowthPostImage(
  postId: string,
  file: File
): Promise<{ success: boolean; url?: string; message?: string }> {
  if (useMockApi()) {
    await delay(500)
    return { success: true, url: 'https://example.com/image.jpg' }
  }
  const form = new FormData()
  form.append('image', file)
  try {
    const res = await apiRequest<{ url: string }>(`/api/company/growth/posts/${postId}/image`, {
      method: 'POST',
      body: form,
    })
    return { success: true, url: res.url }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function generateGrowthPostImage(
  postId: string,
  prompt?: string
): Promise<{ success: boolean; url?: string; message?: string }> {
  if (useMockApi()) {
    await delay(2000)
    return { success: true, url: 'https://example.com/ai-poster.png' }
  }
  try {
    const res = await apiRequest<{ url: string }>(`/api/company/growth/posts/${postId}/generate-image`, {
      method: 'POST',
      body: prompt ? { prompt } : {},
    })
    return { success: true, url: res.url }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function generateGrowthVariants(data: {
  count?: number
  platform?: string
  topic?: string
  saveIndexes?: number[]
}): Promise<{ success: boolean; variants?: unknown[]; savedPosts?: unknown[]; message?: string }> {
  if (useMockApi()) {
    await delay(1000)
    return { success: true, variants: [] }
  }
  try {
    return await apiRequest('/api/company/growth/intelligence/generate-variants', { method: 'POST', body: data })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function exportGrowthAttribution(period = '30d'): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    const { downloadFile } = await import('./api-client')
    await downloadFile(`/api/company/growth/export/attribution?period=${period}`, `attribution-${period}.csv`)
    return { success: true }
  } catch (e) {
    return { success: false, message: e instanceof Error ? e.message : 'Export failed' }
  }
}

export async function scheduleGrowthPost(postId: string, scheduledAt: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    return await apiRequest(`/api/company/growth/posts/${postId}/schedule`, {
      method: 'POST',
      body: { scheduledAt },
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function selectGrowthMetaPage(platform: string, pageId: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(300)
    return { success: true }
  }
  try {
    return await apiRequest('/api/company/growth/meta/select-page', {
      method: 'POST',
      body: { platform, pageId },
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function selectGrowthAdAccount(platform: string, adAccountId: string): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(300)
    return { success: true }
  }
  try {
    return await apiRequest('/api/company/growth/meta/select-ad-account', {
      method: 'POST',
      body: { platform, adAccountId },
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function syncGrowthMetaMetrics(): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    return await apiRequest('/api/company/growth/meta/sync-metrics', { method: 'POST' })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function syncGrowthMetaAds(): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    return await apiRequest('/api/company/growth/meta/sync-ads', { method: 'POST' })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function runGrowthCrmAgent(): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    return await apiRequest('/api/company/growth/crm/run', { method: 'POST' })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function connectGrowthIntegration(data: {
  provider: 'ga4' | 'email' | 'website'
  siteUrl?: string
  measurementId?: string
}): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    return await apiRequest('/api/company/growth/integrations/connect', {
      method: 'POST',
      body: data,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function syncGrowthIntegrations(): Promise<{
  success: boolean
  results?: Record<string, { success: boolean; message: string }>
  message?: string
}> {
  if (useMockApi()) {
    await delay(600)
    return { success: true, results: {} }
  }
  try {
    return await apiRequest('/api/company/growth/integrations/sync', { method: 'POST' })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function generatePortfolioRecommendations(): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(800)
    return { success: true }
  }
  try {
    return await apiRequest('/api/admin/growth-portfolio/generate', { method: 'POST' })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function getGrowthOAuthAuthorizeUrl(platform: string): Promise<{ success: boolean; authorizeUrl?: string; message?: string }> {
  if (useMockApi()) {
    await delay(300)
    return { success: true, authorizeUrl: `https://example.com/oauth/${platform}` }
  }
  try {
    return await apiRequest(`/api/company/growth/oauth/${platform}/authorize`)
  } catch (e) {
    return handleApiError(e)
  }
}

export async function addGrowthAdSpend(data: {
  platform?: string
  campaignName?: string
  amount: number
  currency?: string
  spentAt: string
}): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    return await apiRequest('/api/company/growth/ad-spend', {
      method: 'POST',
      body: data,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function importGrowthAdSpend(file: File): Promise<{ success: boolean; created?: number; message?: string }> {
  if (useMockApi()) {
    await delay(800)
    return { success: true, created: 0 }
  }
  const form = new FormData()
  form.append('file', file)
  try {
    return await apiRequest('/api/company/growth/ad-spend/import', { method: 'POST', body: form })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function connectGrowthSocialAccount(data: {
  platform: string
  accountName?: string
  pageId?: string
  externalAccountId?: string
  accessToken?: string
}): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(500)
    return { success: true }
  }
  try {
    return await apiRequest('/api/company/growth/social-accounts/connect', {
      method: 'POST',
      body: data,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function generateGrowthInsights(): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(800)
    return { success: true }
  }
  try {
    return await apiRequest('/api/company/growth/insights/generate', { method: 'POST' })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function runGrowthAgentPipeline(data?: {
  topic?: string
  platform?: string
  audience?: string
}): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(1000)
    return { success: true }
  }
  try {
    return await apiRequest('/api/company/growth/agents/run', {
      method: 'POST',
      body: data ?? {},
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function addGrowthCompetitor(data: {
  platform: string
  accountName: string
  accountUrl?: string
}): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(400)
    return { success: true }
  }
  try {
    return await apiRequest('/api/company/growth/competitors', {
      method: 'POST',
      body: data,
    })
  } catch (e) {
    return handleApiError(e)
  }
}

// ============================================
// EXECUTIVE AI (Phase 5)
// ============================================

export async function approveExecutiveAction(id: number): Promise<{ success: boolean; message?: string }> {
  try {
    return await apiRequest(`/api/company/executive-ai/approvals/${id}/approve`, { method: 'POST' })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function rejectExecutiveAction(id: number, reason?: string): Promise<{ success: boolean; message?: string }> {
  try {
    return await apiRequest(`/api/company/executive-ai/approvals/${id}/reject`, {
      method: 'POST',
      body: reason ? { reason } : {},
    })
  } catch (e) {
    return handleApiError(e)
  }
}

export async function createCommerceExperiment(data: {
  name: string
  variant_a_message: string
  variant_b_message: string
}): Promise<{ success: boolean; message?: string }> {
  try {
    await apiRequest('/api/company/commerce-experiments', { method: 'POST', body: data })
    return { success: true }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function evaluateCommerceExperiment(id: number): Promise<{ success: boolean; message?: string }> {
  try {
    await apiRequest(`/api/company/commerce-experiments/${id}/evaluate`, { method: 'POST' })
    return { success: true }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function recordIntelligenceOutcome(data: {
  source_type: 'investigation' | 'reasoning' | 'approval'
  source_id: number
  recommended_action: string
  outcome: 'positive' | 'neutral' | 'negative' | 'pending'
  notes?: string
}): Promise<{ success: boolean; message?: string }> {
  try {
    await apiRequest('/api/company/intelligence/outcomes', { method: 'POST', body: data })
    return { success: true }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function reasonIntelligence(data: {
  goal: string
  period?: string
  constraints?: string[]
  simulate?: boolean
  include_plan?: boolean
}): Promise<{ success: boolean; reasoning?: IntelligenceReasoningResult; message?: string }> {
  try {
    const res = await apiRequest<{ reasoning: IntelligenceReasoningResult }>(
      '/api/company/intelligence/reason',
      { method: 'POST', body: data }
    )
    return { success: true, reasoning: res.reasoning }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function detectCommerceEvents(): Promise<{ success: boolean; message?: string }> {
  try {
    await apiRequest('/api/company/commerce-events/detect', { method: 'POST' })
    return { success: true }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function processCommerceAlerts(): Promise<{ success: boolean; message?: string }> {
  try {
    await apiRequest('/api/company/commerce-events/process-alerts', { method: 'POST' })
    return { success: true }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function acknowledgeCommerceEvent(id: number): Promise<{ success: boolean; message?: string }> {
  try {
    await apiRequest(`/api/company/commerce-events/${id}/acknowledge`, { method: 'POST' })
    return { success: true }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function runCommerceSpecialistPipeline(): Promise<{ success: boolean; message?: string }> {
  try {
    await apiRequest('/api/company/commerce-specialists/run', { method: 'POST', body: {} })
    return { success: true }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function refreshCompanyBrain(): Promise<{ success: boolean; message?: string }> {
  try {
    await apiRequest('/api/company/company-brain/refresh', { method: 'POST' })
    return { success: true }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function investigateOwnerAnalytics(data: {
  question: string
  period?: '7d' | '30d' | '90d'
}): Promise<{ success: boolean; investigation?: Record<string, unknown>; message?: string }> {
  try {
    const res = await apiRequest<{ investigation: Record<string, unknown> }>(
      '/api/company/owner-analytics/investigate',
      { method: 'POST', body: data }
    )
    return { success: true, investigation: res.investigation }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function connectCommerceIntegration(data: {
  connectorType: string
  config?: Record<string, string>
}): Promise<{ success: boolean; message?: string }> {
  try {
    const res = await apiRequest<{ success: boolean; message?: string }>(
      '/api/company/integrations/connect',
      { method: 'POST', body: data }
    )
    return { success: res.success, message: res.message }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function syncCommerceIntegration(connectorType: string): Promise<{ success: boolean; message?: string }> {
  try {
    const res = await apiRequest<{ success: boolean; message?: string }>(
      '/api/company/integrations/sync',
      { method: 'POST', body: { connectorType } }
    )
    return { success: res.success, message: res.message }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function disconnectCommerceIntegration(connectorType: string): Promise<{ success: boolean; message?: string }> {
  try {
    await apiRequest(`/api/company/integrations/${connectorType}`, { method: 'DELETE' })
    return { success: true }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function syncBusinessTimeline(): Promise<{ success: boolean; synced?: number; message?: string }> {
  try {
    const res = await apiRequest<{ synced: number }>('/api/company/business-timeline/sync', { method: 'POST' })
    return { success: true, synced: res.synced }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function syncBusinessGraph(): Promise<{ success: boolean; stats?: { nodes: number; edges: number }; message?: string }> {
  try {
    const res = await apiRequest<{ stats: { nodes: number; edges: number } }>('/api/company/business-graph/sync', {
      method: 'POST',
    })
    return { success: true, stats: res.stats }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function startOnboardingInterview(): Promise<{
  success: boolean
  sessionId?: string
  message?: string
  complete?: boolean
}> {
  try {
    const res = await apiRequest<{ sessionId: string; message: string; complete: boolean }>(
      '/api/company/onboarding-interview/start',
      { method: 'POST' }
    )
    return { success: true, ...res }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function respondOnboardingInterview(data: {
  sessionId: string
  message: string
}): Promise<{ success: boolean; message?: string; complete?: boolean; extracted?: Record<string, unknown> }> {
  try {
    const res = await apiRequest<{ message: string; complete: boolean; extracted?: Record<string, unknown> }>(
      '/api/company/onboarding-interview/respond',
      { method: 'POST', body: data }
    )
    return { success: true, ...res }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function searchBusinessMemory(data: {
  query: string
  limit?: number
}): Promise<{ success: boolean; data?: import('@/lib/api-hooks').MemorySearchResponse; message?: string }> {
  try {
    const res = await apiRequest<import('@/lib/api-hooks').MemorySearchResponse>('/api/company/memory-search', {
      method: 'POST',
      body: data,
    })
    return { success: true, data: res }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function installMarketplaceModule(
  moduleKey: string,
  config?: { webhook_base_url?: string; webhook_secret?: string }
): Promise<{ success: boolean; message?: string }> {
  try {
    await apiRequest(`/api/company/marketplace/modules/${encodeURIComponent(moduleKey)}/install`, {
      method: 'POST',
      body: config ? { config } : {},
    })
    return { success: true }
  } catch (e) {
    return handleApiError(e)
  }
}

export async function uninstallMarketplaceModule(
  moduleKey: string
): Promise<{ success: boolean; message?: string }> {
  try {
    await apiRequest(`/api/company/marketplace/modules/${encodeURIComponent(moduleKey)}/install`, {
      method: 'DELETE',
    })
    return { success: true }
  } catch (e) {
    return handleApiError(e)
  }
}
