// API Actions for form submissions and mutations
// When real API is available (NEXT_PUBLIC_API_URL set and NEXT_PUBLIC_USE_MOCK_API not 'true'), all functions call Laravel; mock branches are skipped.

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
} from './mock-data'
import { mockSubscriptions } from './mock-data'
import { useMockApi, apiRequest } from './api-client'

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
}

/**
 * Connect WhatsApp Business number (Meta Cloud API).
 * Laravel: POST /api/company/whatsapp/connect
 */
export async function connectWhatsApp(payload: ConnectWhatsAppPayload): Promise<{ success: boolean; message?: string }> {
  if (useMockApi()) {
    await delay(2000)
    return { success: true, message: 'WhatsApp account connected.' }
  }
  try {
    return await apiRequest<{ success: boolean; message?: string }>('/api/company/whatsapp/connect', {
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
}

export interface WhatsAppEmbeddedConfig {
  enabled: boolean
  appId: string | null
  configId: string | null
  graphVersion: string
}

export interface CompleteEmbeddedSignupPayload {
  code?: string
  phoneNumberId?: string
  whatsappBusinessAccountId?: string
  displayPhoneNumber?: string
  accessToken?: string
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
    return { enabled: false, appId: null, configId: null, graphVersion: 'v21.0' }
  }
  return apiRequest<WhatsAppEmbeddedConfig>('/api/company/whatsapp/embedded/config', { method: 'GET' })
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
  phone: string
): Promise<{ success: boolean; checkoutRequestId?: string; message?: string }> {
  if (useMockApi()) {
    await delay(800)
    return { success: true, checkoutRequestId: 'mock-req-'.concat(planId), message: 'Enter your M-Pesa PIN on your phone.' }
  }
  try {
    const res = await apiRequest<{ checkoutRequestId: string; message: string }>('/api/company/mpesa/initiate', {
      method: 'POST',
      body: { planId, phone },
    })
    return { success: true, checkoutRequestId: res.checkoutRequestId, message: res.message }
  } catch (e) {
    return { success: false, message: e instanceof Error ? e.message : 'M-Pesa initiation failed' }
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
}

/** Public app branding (name, logo, colors) for theme/invoices. GET /api/app-branding */
export interface AppBranding {
  applicationName: string
  appLogo: string | null
  primaryColor: string | null
  secondaryColor: string | null
}

/**
 * Get platform settings (admin only)
 * Laravel: GET /api/admin/settings
 */
export async function getPlatformSettings(): Promise<PlatformSettings> {
  if (useMockApi()) {
    await delay(300)
    return {
      platformName: 'Savit Chat',
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
      fromName: 'Savit Chat',
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
      applicationName: 'Savit Chat',
      appLogo: null,
      primaryColor: null,
      secondaryColor: null,
    }
  }
  const baseUrl = process.env.NEXT_PUBLIC_API_URL || ''
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
