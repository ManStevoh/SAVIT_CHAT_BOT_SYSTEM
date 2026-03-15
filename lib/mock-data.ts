// Mock data for API-ready components
// Replace these with actual API calls in production
//
// ========== EXAMPLE MOCK JSON FOR API INTEGRATION ==========
// Use these shapes when implementing backend endpoints. Each list endpoint
// can return paginated data: { items: T[], total, page, totalPages } where applicable.
//
// Chats:    GET /api/company/chats          -> Chat[]
// Messages: GET /api/company/chats/:id/messages -> Message[]
// Orders:   GET /api/company/orders         -> { orders: Order[], total, page, totalPages }
// Customers: GET /api/company/customers    -> { customers: Customer[], total, page, totalPages }
// Products: GET /api/company/products      -> Product[]
// FAQs:     GET /api/company/faqs          -> FAQ[]
// Subscriptions: GET /api/company/subscription -> Subscription (current)
// Analytics: GET /api/company/analytics?period=7d -> AnalyticsData
// Revenue (admin): GET /api/admin/revenue  -> RevenueData
// AI Usage (admin): GET /api/admin/ai-usage -> AIUsageData
// Companies (admin): GET /api/admin/companies -> Company[]
// Users (admin): GET /api/admin/users      -> User[]
// Subscriptions (admin): GET /api/admin/subscriptions -> Subscription[]
// Logs (admin): GET /api/admin/logs        -> SystemLog[]
// Overview (admin): GET /api/admin/overview -> { totalCompanies, activeCompanies, totalUsers, totalRevenue, ... }
// ==========================================================

export interface Chat {
  id: string
  customerName: string
  customerPhone: string
  customerAvatar?: string
  lastMessage: string
  lastMessageTime: string
  unreadCount: number
  status: 'active' | 'pending' | 'resolved'
  aiHandled: boolean
}

export interface Message {
  id: string
  chatId: string
  content: string
  sender: 'customer' | 'bot' | 'agent'
  timestamp: string
  status: 'sent' | 'delivered' | 'read'
}

export interface Order {
  id: string
  orderNumber: string
  customerName: string
  customerPhone: string
  products: OrderProduct[]
  total: number
  status: 'pending' | 'confirmed' | 'shipped' | 'delivered' | 'cancelled'
  paymentStatus: 'pending' | 'paid' | 'refunded'
  createdAt: string
  updatedAt: string
}

export interface OrderProduct {
  id: string
  name: string
  quantity: number
  price: number
}

export interface Customer {
  id: string
  name: string
  phone: string
  email?: string
  avatar?: string
  totalOrders: number
  totalSpent: number
  lastOrderDate: string
  createdAt: string
}

export interface Product {
  id: string
  name: string
  description: string
  price: number
  category: string
  image?: string
  stock: number
  status: 'active' | 'inactive'
  createdAt: string
}

export interface FAQ {
  id: string
  question: string
  answer: string
  category: string
  keywords: string[]
  isActive: boolean
  usageCount: number
  createdAt: string
}

export interface Subscription {
  id: string
  companyId: string
  companyName: string
  plan: 'starter' | 'professional' | 'enterprise'
  status: 'active' | 'cancelled' | 'expired' | 'trial'
  startDate: string
  endDate: string
  amount: number
  billingCycle: 'monthly' | 'yearly'
}

export interface Plan {
  id: string
  name: string
  slug: string
  price?: string
  priceDisplay?: string
  priceAmount?: number | null
  description?: string
  features: string[]
  popular: boolean
  cta?: string
  sortOrder?: number
}

export interface Company {
  id: string
  name: string
  email: string
  phone: string
  logo?: string
  plan: 'starter' | 'professional' | 'enterprise'
  status: 'active' | 'suspended' | 'pending'
  totalChats: number
  totalOrders: number
  createdAt: string
}

export interface User {
  id: string
  name: string
  email: string
  role: 'admin' | 'company_owner' | 'company_user'
  companyId?: string
  companyName?: string
  avatar?: string
  status: 'active' | 'inactive'
  lastLogin: string
  createdAt: string
}

export interface SystemLog {
  id: string
  type: 'info' | 'warning' | 'error' | 'success'
  message: string
  source: string
  details?: string
  timestamp: string
}

export interface AnalyticsData {
  totalMessages: number
  totalOrders: number
  totalRevenue: number
  totalCustomers: number
  messagesChange: number
  ordersChange: number
  revenueChange: number
  customersChange: number
  messagesPerDay: ChartDataPoint[]
  ordersPerDay: ChartDataPoint[]
  revenuePerDay: ChartDataPoint[]
  topProducts: TopProduct[]
  customerGrowth: ChartDataPoint[]
}

export interface ChartDataPoint {
  date: string
  value: number
  label?: string
}

export interface TopProduct {
  id: string
  name: string
  sales: number
  revenue: number
}

export interface RevenueData {
  totalRevenue: number
  mrr: number
  arr: number
  revenueChange: number
  revenueByPlan: { plan: string; amount: number; count: number }[]
  revenueByMonth: ChartDataPoint[]
  topCompanies: { id: string; name: string; revenue: number }[]
}

export interface AIUsageData {
  totalRequests: number
  totalTokens: number
  avgResponseTime: number
  successRate: number
  requestsChange: number
  tokensChange: number
  usageByDay: ChartDataPoint[]
  usageByCompany: { companyId: string; companyName: string; requests: number; tokens: number }[]
  modelUsage: { model: string; requests: number; tokens: number }[]
}

// Mock Data Examples
export const mockChats: Chat[] = [
  {
    id: '1',
    customerName: 'Ahmed Hassan',
    customerPhone: '+201234567890',
    lastMessage: 'I want to order the iPhone 15 Pro',
    lastMessageTime: '2 min ago',
    unreadCount: 3,
    status: 'active',
    aiHandled: true,
  },
  {
    id: '2',
    customerName: 'Sara Mohamed',
    customerPhone: '+201098765432',
    lastMessage: 'What is the delivery time?',
    lastMessageTime: '15 min ago',
    unreadCount: 1,
    status: 'pending',
    aiHandled: false,
  },
  {
    id: '3',
    customerName: 'Omar Ali',
    customerPhone: '+201112223334',
    lastMessage: 'Thank you for your help!',
    lastMessageTime: '1 hour ago',
    unreadCount: 0,
    status: 'resolved',
    aiHandled: true,
  },
]

export const mockMessages: Message[] = [
  {
    id: '1',
    chatId: '1',
    content: 'Hello! I want to order the iPhone 15 Pro',
    sender: 'customer',
    timestamp: '10:30 AM',
    status: 'read',
  },
  {
    id: '2',
    chatId: '1',
    content: 'Hi Ahmed! The iPhone 15 Pro is available in stock. Would you like the 256GB or 512GB version?',
    sender: 'bot',
    timestamp: '10:30 AM',
    status: 'delivered',
  },
  {
    id: '3',
    chatId: '1',
    content: 'I want the 256GB version in Natural Titanium',
    sender: 'customer',
    timestamp: '10:32 AM',
    status: 'read',
  },
]

export const mockOrders: Order[] = [
  {
    id: '1',
    orderNumber: 'ORD-2024-001',
    customerName: 'Ahmed Hassan',
    customerPhone: '+201234567890',
    products: [
      { id: '1', name: 'iPhone 15 Pro 256GB', quantity: 1, price: 45000 },
    ],
    total: 45000,
    status: 'pending',
    paymentStatus: 'pending',
    createdAt: '2024-01-15T10:30:00Z',
    updatedAt: '2024-01-15T10:30:00Z',
  },
  {
    id: '2',
    orderNumber: 'ORD-2024-002',
    customerName: 'Sara Mohamed',
    customerPhone: '+201098765432',
    products: [
      { id: '2', name: 'AirPods Pro 2', quantity: 2, price: 5000 },
    ],
    total: 10000,
    status: 'confirmed',
    paymentStatus: 'paid',
    createdAt: '2024-01-14T14:20:00Z',
    updatedAt: '2024-01-15T09:00:00Z',
  },
  {
    id: '3',
    orderNumber: 'ORD-2024-003',
    customerName: 'Omar Ali',
    customerPhone: '+201112223334',
    products: [
      { id: '3', name: 'MacBook Air M3', quantity: 1, price: 55000 },
      { id: '4', name: 'Magic Mouse', quantity: 1, price: 3500 },
    ],
    total: 58500,
    status: 'shipped',
    paymentStatus: 'paid',
    createdAt: '2024-01-13T09:15:00Z',
    updatedAt: '2024-01-14T16:30:00Z',
  },
]

export const mockCustomers: Customer[] = [
  {
    id: '1',
    name: 'Ahmed Hassan',
    phone: '+201234567890',
    email: 'ahmed@example.com',
    totalOrders: 5,
    totalSpent: 125000,
    lastOrderDate: '2024-01-15',
    createdAt: '2023-06-10',
  },
  {
    id: '2',
    name: 'Sara Mohamed',
    phone: '+201098765432',
    email: 'sara@example.com',
    totalOrders: 3,
    totalSpent: 45000,
    lastOrderDate: '2024-01-14',
    createdAt: '2023-08-22',
  },
  {
    id: '3',
    name: 'Omar Ali',
    phone: '+201112223334',
    totalOrders: 8,
    totalSpent: 250000,
    lastOrderDate: '2024-01-13',
    createdAt: '2023-03-15',
  },
]

export const mockProducts: Product[] = [
  {
    id: '1',
    name: 'iPhone 15 Pro 256GB',
    description: 'Latest iPhone with A17 Pro chip',
    price: 45000,
    category: 'Phones',
    stock: 25,
    status: 'active',
    createdAt: '2023-09-22',
  },
  {
    id: '2',
    name: 'AirPods Pro 2',
    description: 'Active noise cancellation wireless earbuds',
    price: 5000,
    category: 'Accessories',
    stock: 100,
    status: 'active',
    createdAt: '2023-09-15',
  },
  {
    id: '3',
    name: 'MacBook Air M3',
    description: '15-inch Retina display with M3 chip',
    price: 55000,
    category: 'Laptops',
    stock: 15,
    status: 'active',
    createdAt: '2024-01-05',
  },
  {
    id: '4',
    name: 'iPad Pro 12.9"',
    description: 'M2 chip with Liquid Retina XDR display',
    price: 42000,
    category: 'Tablets',
    stock: 0,
    status: 'inactive',
    createdAt: '2023-10-26',
  },
]

export const mockFAQs: FAQ[] = [
  {
    id: '1',
    question: 'What are your delivery times?',
    answer: 'We deliver within 2-3 business days in Cairo and 3-5 days for other governorates.',
    category: 'Shipping',
    keywords: ['delivery', 'shipping', 'time', 'days'],
    isActive: true,
    usageCount: 156,
    createdAt: '2023-06-01',
  },
  {
    id: '2',
    question: 'What payment methods do you accept?',
    answer: 'We accept cash on delivery, credit cards, and bank transfers.',
    category: 'Payment',
    keywords: ['payment', 'pay', 'cash', 'card', 'transfer'],
    isActive: true,
    usageCount: 203,
    createdAt: '2023-06-01',
  },
  {
    id: '3',
    question: 'What is your return policy?',
    answer: 'You can return any product within 14 days of purchase in its original condition.',
    category: 'Returns',
    keywords: ['return', 'refund', 'policy', 'exchange'],
    isActive: true,
    usageCount: 89,
    createdAt: '2023-06-15',
  },
]

export const mockSubscriptions: Subscription[] = [
  {
    id: '1',
    companyId: '1',
    companyName: 'Tech Store Egypt',
    plan: 'professional',
    status: 'active',
    startDate: '2024-01-01',
    endDate: '2024-02-01',
    amount: 299,
    billingCycle: 'monthly',
  },
  {
    id: '2',
    companyId: '2',
    companyName: 'Fashion Hub',
    plan: 'starter',
    status: 'active',
    startDate: '2024-01-10',
    endDate: '2024-02-10',
    amount: 49,
    billingCycle: 'monthly',
  },
  {
    id: '3',
    companyId: '3',
    companyName: 'Electronics Plus',
    plan: 'enterprise',
    status: 'trial',
    startDate: '2024-01-12',
    endDate: '2024-01-26',
    amount: 0,
    billingCycle: 'monthly',
  },
]

export const mockCompanies: Company[] = [
  {
    id: '1',
    name: 'Tech Store Egypt',
    email: 'contact@techstore.eg',
    phone: '+201234567890',
    plan: 'professional',
    status: 'active',
    totalChats: 1250,
    totalOrders: 450,
    createdAt: '2023-06-15',
  },
  {
    id: '2',
    name: 'Fashion Hub',
    email: 'info@fashionhub.com',
    phone: '+201098765432',
    plan: 'starter',
    status: 'active',
    totalChats: 320,
    totalOrders: 85,
    createdAt: '2023-09-20',
  },
  {
    id: '3',
    name: 'Electronics Plus',
    email: 'sales@electronicsplus.eg',
    phone: '+201112223334',
    plan: 'enterprise',
    status: 'pending',
    totalChats: 50,
    totalOrders: 12,
    createdAt: '2024-01-12',
  },
]

export const mockUsers: User[] = [
  {
    id: '1',
    name: 'Mohamed Admin',
    email: 'admin@wazzbot.com',
    role: 'admin',
    status: 'active',
    lastLogin: '2024-01-15T10:30:00Z',
    createdAt: '2023-01-01',
  },
  {
    id: '2',
    name: 'Ahmed Owner',
    email: 'ahmed@techstore.eg',
    role: 'company_owner',
    companyId: '1',
    companyName: 'Tech Store Egypt',
    status: 'active',
    lastLogin: '2024-01-15T09:15:00Z',
    createdAt: '2023-06-15',
  },
  {
    id: '3',
    name: 'Sara User',
    email: 'sara@fashionhub.com',
    role: 'company_user',
    companyId: '2',
    companyName: 'Fashion Hub',
    status: 'active',
    lastLogin: '2024-01-14T16:45:00Z',
    createdAt: '2023-10-01',
  },
]

export const mockSystemLogs: SystemLog[] = [
  {
    id: '1',
    type: 'success',
    message: 'New company registered: Electronics Plus',
    source: 'Auth Service',
    timestamp: '2024-01-15T10:30:00Z',
  },
  {
    id: '2',
    type: 'info',
    message: 'WhatsApp webhook received for Tech Store Egypt',
    source: 'WhatsApp API',
    timestamp: '2024-01-15T10:28:00Z',
  },
  {
    id: '3',
    type: 'warning',
    message: 'AI response time exceeded threshold (3.5s)',
    source: 'AI Service',
    details: 'Company: Fashion Hub, Chat ID: 1234',
    timestamp: '2024-01-15T10:25:00Z',
  },
  {
    id: '4',
    type: 'error',
    message: 'Failed to send WhatsApp message',
    source: 'WhatsApp API',
    details: 'Error: Rate limit exceeded',
    timestamp: '2024-01-15T10:20:00Z',
  },
]

export const mockAnalytics: AnalyticsData = {
  totalMessages: 12450,
  totalOrders: 856,
  totalRevenue: 2450000,
  totalCustomers: 1234,
  messagesChange: 12.5,
  ordersChange: 8.3,
  revenueChange: 15.2,
  customersChange: 5.7,
  messagesPerDay: [
    { date: 'Mon', value: 420 },
    { date: 'Tue', value: 380 },
    { date: 'Wed', value: 450 },
    { date: 'Thu', value: 520 },
    { date: 'Fri', value: 380 },
    { date: 'Sat', value: 280 },
    { date: 'Sun', value: 320 },
  ],
  ordersPerDay: [
    { date: 'Mon', value: 45 },
    { date: 'Tue', value: 38 },
    { date: 'Wed', value: 52 },
    { date: 'Thu', value: 61 },
    { date: 'Fri', value: 48 },
    { date: 'Sat', value: 35 },
    { date: 'Sun', value: 28 },
  ],
  revenuePerDay: [
    { date: 'Mon', value: 125000 },
    { date: 'Tue', value: 98000 },
    { date: 'Wed', value: 145000 },
    { date: 'Thu', value: 178000 },
    { date: 'Fri', value: 132000 },
    { date: 'Sat', value: 89000 },
    { date: 'Sun', value: 72000 },
  ],
  topProducts: [
    { id: '1', name: 'iPhone 15 Pro', sales: 125, revenue: 5625000 },
    { id: '2', name: 'MacBook Air M3', sales: 45, revenue: 2475000 },
    { id: '3', name: 'AirPods Pro 2', sales: 230, revenue: 1150000 },
    { id: '4', name: 'iPad Pro', sales: 38, revenue: 1596000 },
  ],
  customerGrowth: [
    { date: 'Jan', value: 120 },
    { date: 'Feb', value: 145 },
    { date: 'Mar', value: 180 },
    { date: 'Apr', value: 220 },
    { date: 'May', value: 195 },
    { date: 'Jun', value: 260 },
  ],
}

export const mockRevenueData: RevenueData = {
  totalRevenue: 125000,
  mrr: 45000,
  arr: 540000,
  revenueChange: 18.5,
  revenueByPlan: [
    { plan: 'Starter', amount: 9800, count: 200 },
    { plan: 'Professional', amount: 23900, count: 80 },
    { plan: 'Enterprise', amount: 11300, count: 15 },
  ],
  revenueByMonth: [
    { date: 'Jul', value: 32000 },
    { date: 'Aug', value: 35000 },
    { date: 'Sep', value: 38000 },
    { date: 'Oct', value: 41000 },
    { date: 'Nov', value: 43000 },
    { date: 'Dec', value: 45000 },
  ],
  topCompanies: [
    { id: '1', name: 'Tech Store Egypt', revenue: 8970 },
    { id: '2', name: 'Electronics Plus', revenue: 5970 },
    { id: '3', name: 'Fashion Hub', revenue: 2990 },
  ],
}

export const mockAIUsage: AIUsageData = {
  totalRequests: 1250000,
  totalTokens: 85000000,
  avgResponseTime: 1.2,
  successRate: 99.2,
  requestsChange: 22.5,
  tokensChange: 18.3,
  usageByDay: [
    { date: 'Mon', value: 45000 },
    { date: 'Tue', value: 42000 },
    { date: 'Wed', value: 48000 },
    { date: 'Thu', value: 52000 },
    { date: 'Fri', value: 46000 },
    { date: 'Sat', value: 32000 },
    { date: 'Sun', value: 28000 },
  ],
  usageByCompany: [
    { companyId: '1', companyName: 'Tech Store Egypt', requests: 125000, tokens: 8500000 },
    { companyId: '2', companyName: 'Fashion Hub', requests: 45000, tokens: 3200000 },
    { companyId: '3', companyName: 'Electronics Plus', requests: 12000, tokens: 850000 },
  ],
  modelUsage: [
    { model: 'GPT-4', requests: 450000, tokens: 45000000 },
    { model: 'GPT-3.5 Turbo', requests: 800000, tokens: 40000000 },
  ],
}

// Pricing plans for landing page
export const pricingPlans = [
  {
    id: 'starter',
    name: 'Starter',
    description: 'Perfect for small businesses getting started',
    price: 49,
    yearlyPrice: 39,
    features: [
      '500 messages/month',
      '1 WhatsApp number',
      'Basic AI responses',
      'Order management',
      'Email support',
    ],
    popular: false,
  },
  {
    id: 'professional',
    name: 'Professional',
    description: 'For growing businesses with higher demands',
    price: 149,
    yearlyPrice: 119,
    features: [
      '5,000 messages/month',
      '3 WhatsApp numbers',
      'Advanced AI with custom training',
      'Product catalog integration',
      'Analytics dashboard',
      'Priority support',
    ],
    popular: true,
  },
  {
    id: 'enterprise',
    name: 'Enterprise',
    description: 'For large organizations with custom needs',
    price: 499,
    yearlyPrice: 399,
    features: [
      'Unlimited messages',
      'Unlimited WhatsApp numbers',
      'Custom AI model training',
      'API access',
      'Dedicated account manager',
      'SLA guarantee',
      'Custom integrations',
    ],
    popular: false,
  },
]

// Testimonials for landing page
export const testimonials = [
  {
    id: '1',
    name: 'Ahmed El-Sayed',
    role: 'CEO',
    company: 'Tech Store Egypt',
    content: 'WazzBot transformed our customer service. We now handle 3x more inquiries with the same team, and our response time dropped from hours to seconds.',
    avatar: '/avatars/ahmed.jpg',
    rating: 5,
  },
  {
    id: '2',
    name: 'Sara Hassan',
    role: 'Operations Manager',
    company: 'Fashion Hub',
    content: 'The AI understands our products perfectly. It handles 80% of customer questions automatically, and the order management integration is seamless.',
    avatar: '/avatars/sara.jpg',
    rating: 5,
  },
  {
    id: '3',
    name: 'Omar Mahmoud',
    role: 'Founder',
    company: 'Electronics Plus',
    content: 'We saw a 40% increase in sales within the first month. The AI assistant qualifies leads and even upsells products intelligently.',
    avatar: '/avatars/omar.jpg',
    rating: 5,
  },
]

// FAQ items for landing page
export const landingFAQs = [
  {
    id: '1',
    question: 'How does WazzBot integrate with WhatsApp?',
    answer: 'WazzBot uses the official WhatsApp Business API to connect with your business number. Setup takes less than 5 minutes, and we guide you through the entire process.',
  },
  {
    id: '2',
    question: 'Can the AI be trained on my products?',
    answer: 'Yes! You can upload your product catalog, FAQs, and custom responses. Our AI learns your business context to provide accurate, personalized responses to customers.',
  },
  {
    id: '3',
    question: 'What happens when the AI cannot answer a question?',
    answer: 'When the AI encounters a question it cannot confidently answer, it automatically escalates to a human agent. You will receive a notification and can take over the conversation seamlessly.',
  },
  {
    id: '4',
    question: 'Is my customer data secure?',
    answer: 'Absolutely. We use enterprise-grade encryption, comply with GDPR, and never share your data with third parties. All conversations are stored securely and you have full control over data retention.',
  },
  {
    id: '5',
    question: 'Can I try WazzBot before committing?',
    answer: 'Yes! We offer a 14-day free trial on all plans. No credit card required. You can test all features and see the results before making a decision.',
  },
]
