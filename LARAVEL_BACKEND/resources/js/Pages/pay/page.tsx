'use client'

import { FormEvent, useEffect, useState } from 'react'
import { router } from '@inertiajs/react'
import {
  CheckCircle2,
  CreditCard,
  Lock,
  Smartphone,
  ShieldCheck,
  ArrowRight,
  Check,
  ShoppingBag,
  FileText,
  Loader2,
  Copy,
  Clock,
  ExternalLink,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

type OrderPayload = {
  orderNumber: string
  customerName: string
  customerEmail?: string | null
  customerPhone?: string | null
  totalFormatted: string
  paymentStatus: string
  paymentMethod?: string | null
  invoiceToken?: string | null
  items: { name: string; quantity: number; lineSubtotal: number }[]
}

type PaymentOptions = {
  options: {
    id: string
    label: string
    category: string
    instructions?: string | null
    requiresPhone: boolean
    requiresEmail?: boolean
  }[]
  cod: boolean
  stripe: boolean
  paystack: boolean
  pesapal?: boolean
  flutterwave?: boolean
  paypal?: boolean
  mpesa: boolean
  manual: boolean
}

type Props = {
  token: string
  order: OrderPayload
  company: {
    name: string
    customDomain?: string | null
    storeSlug?: string | null
    whatsappNumber?: string | null
  }
  paymentOptions: PaymentOptions
  initialMethod?: string | null
  manualSubmitted?: boolean
  status?: string
  errors?: Record<string, string>
}

// Cleanly extracts and formats Kenyan numbers (e.g. 0746 271 812) and removes noise like "to prevent reversal"
function parseManualDetails(rawInstructions?: string | null, companyName?: string) {
  if (!rawInstructions) return null

  // Clean out unnecessary noise
  let text = rawInstructions.replace(/to prevent reversal/gi, '').trim()

  // Match Kenyan phone numbers
  const match = text.match(/(?:\+?254|0)?([17]\d{2}[\s\-]?\d{3}[\s\-]?\d{3})/i)
  let rawPhone = ''
  let formattedPhone = ''

  if (match) {
    const digits = match[0].replace(/\D/g, '')
    if (digits.startsWith('254') && digits.length === 12) {
      rawPhone = '0' + digits.slice(3)
    } else if (digits.length === 10 && (digits.startsWith('07') || digits.startsWith('01'))) {
      rawPhone = digits
    } else if (digits.length === 9) {
      rawPhone = '0' + digits
    } else {
      rawPhone = digits
    }

    if (rawPhone.length === 10) {
      formattedPhone = `${rawPhone.slice(0, 4)} ${rawPhone.slice(4, 7)} ${rawPhone.slice(7)}`
    } else {
      formattedPhone = match[0].trim()
    }
  }

  const isPochi = /pochi|pichi|biashara/i.test(text)
  const isTill = /till|buy goods/i.test(text)
  const isPaybill = /paybill/i.test(text)

  let badge = 'M-Pesa (Pochi la Biashara)'
  if (isTill) badge = 'Buy Goods / Till'
  else if (isPaybill) badge = 'M-Pesa Paybill'
  else if (isPochi) badge = 'Pochi la Biashara'

  return {
    rawPhone,
    formattedPhone: formattedPhone || rawPhone,
    badge,
    accountName: companyName,
    isPochi,
    isTill,
    isPaybill,
  }
}

export default function PublicPayPage({
  token,
  order,
  company,
  paymentOptions,
  initialMethod,
  manualSubmitted = false,
  status,
  errors = {},
}: Props) {
  const getInitialMethod = () => {
    if (initialMethod) return initialMethod
    if (typeof window !== 'undefined') {
      const params = new URLSearchParams(window.location.search)
      return params.get('method') || params.get('gateway') || ''
    }
    return ''
  }

  const [method, setMethod] = useState<string>(getInitialMethod)
  const [phone, setPhone] = useState(order.customerPhone ?? '')
  const [email, setEmail] = useState(order.customerEmail ?? '')
  const [transactionCode, setTransactionCode] = useState('')
  const [copied, setCopied] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [showEditForm, setShowEditForm] = useState(false)

  const isPaid = order.paymentStatus === 'paid'
  const isManualAwaiting =
    !isPaid &&
    (manualSubmitted || (order.paymentMethod === 'manual' && Boolean(status))) &&
    !showEditForm

  // Live polling for STK Push / Webhook / Manual approval confirmation
  useEffect(() => {
    if (isPaid) return

    // Poll every 4 seconds to check if status was updated by store owner
    const interval = setInterval(() => {
      router.reload({
        only: ['order', 'status', 'manualSubmitted'],
      })
    }, 4000)

    return () => clearInterval(interval)
  }, [isPaid, token])

  const copyNumber = (num: string) => {
    if (typeof window !== 'undefined' && num) {
      navigator.clipboard.writeText(num.replace(/\s+/g, ''))
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    }
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    if (!method) return
    setSubmitting(true)
    router.post(
      `/pay/${token}`,
      {
        method,
        phone: phone ? phone.trim() : null,
        email: email ? email.trim() : null,
        transaction_code: transactionCode ? transactionCode.trim() : null,
      },
      {
        onFinish: () => {
          setSubmitting(false)
          setShowEditForm(false)
        },
      }
    )
  }

  const availableMethods = paymentOptions.options
  const selectedMethod = availableMethods.find((option) => option.id === method)
  const manualDetails =
    method === 'manual' ? parseManualDetails(selectedMethod?.instructions, company.name) : null

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50/80 px-4 py-12 dark:bg-slate-950">
      <div className="w-full max-w-md space-y-6 rounded-3xl border border-slate-200/80 bg-white p-7 shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
        {/* Header Branding */}
        <div className="space-y-1 text-center sm:text-left">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-400">
              {company.name}
            </span>
            <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
              #{order.orderNumber}
            </span>
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
            Payment Checkout
          </h1>
          <div className="mt-2 flex items-baseline justify-between rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/50">
            <span className="text-sm text-slate-500 dark:text-slate-400">Total Amount</span>
            <span className="text-2xl font-extrabold text-slate-900 dark:text-white">
              {order.totalFormatted}
            </span>
          </div>
        </div>

        {/* Global Errors */}
        {errors.method && (
          <p className="rounded-2xl bg-red-50 p-3.5 text-xs font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">
            {errors.method}
          </p>
        )}
        {errors.email && (
          <p className="rounded-2xl bg-red-50 p-3.5 text-xs font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">
            {errors.email}
          </p>
        )}

        {/* STATE 1: Paid Successfully */}
        {isPaid ? (
          <div className="space-y-5 rounded-2xl border border-emerald-200/80 bg-emerald-50/90 p-6 text-center dark:border-emerald-900/50 dark:bg-emerald-950/30">
            <CheckCircle2 className="mx-auto h-12 w-12 text-emerald-600 dark:text-emerald-400" />
            <div className="space-y-1">
              <h2 className="text-xl font-bold text-emerald-950 dark:text-emerald-200">
                Payment Received!
              </h2>
              <p className="text-xs text-emerald-800 dark:text-emerald-300">
                Thank you, <strong className="font-semibold">{order.customerName}</strong>. Your
                payment for Order #{order.orderNumber} is confirmed.
              </p>
            </div>

            <div className="space-y-1.5 border-t border-emerald-200/70 pt-3 text-left text-xs dark:border-emerald-900/50">
              <div className="font-bold uppercase tracking-wider text-emerald-800/70 dark:text-emerald-400">
                Order Details
              </div>
              {order.items.map((item, idx) => (
                <div
                  key={idx}
                  className="flex justify-between text-emerald-900 dark:text-emerald-200"
                >
                  <span>
                    {item.quantity} × {item.name}
                  </span>
                  <span className="font-semibold">{item.lineSubtotal.toFixed(2)}</span>
                </div>
              ))}
            </div>

            {/* Navigation Action Buttons */}
            <div className="flex flex-col gap-2 pt-2">
              <Button
                asChild
                size="lg"
                className="w-full gap-2 bg-slate-900 text-white hover:bg-slate-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"
              >
                <a
                  href={
                    company.customDomain
                      ? '/'
                      : company.storeSlug
                        ? `/s/${company.storeSlug}`
                        : '/'
                  }
                >
                  <ShoppingBag className="h-4 w-4" /> Return to Store
                </a>
              </Button>

              {order.invoiceToken && (
                <Button
                  asChild
                  type="button"
                  variant="outline"
                  size="sm"
                  className="w-full gap-2 border-emerald-300 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-900/40"
                >
                  <a href={`/invoice/${order.invoiceToken}`} target="_blank" rel="noreferrer">
                    <FileText className="h-4 w-4" /> View / Print Receipt
                  </a>
                </Button>
              )}
            </div>
          </div>
        ) : isManualAwaiting ? (
          /* STATE 2: Manual Payment Confirmation in Progress */
          <div className="space-y-5 rounded-2xl border border-amber-200/80 bg-amber-50/80 p-6 text-center dark:border-amber-900/50 dark:bg-amber-950/30">
            <div className="relative mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400">
              <Clock className="h-7 w-7 animate-pulse" />
            </div>

            <div className="space-y-1.5">
              <span className="inline-flex items-center gap-1 rounded-full bg-amber-200/70 px-2.5 py-0.5 text-[11px] font-semibold text-amber-900 dark:bg-amber-900/60 dark:text-amber-200">
                <Loader2 className="h-3 w-3 animate-spin" /> Awaiting Confirmation
              </span>
              <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                Payment Confirmation in Progress
              </h2>
              <p className="text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                Thank you, <strong className="font-semibold">{order.customerName}</strong>! We have
                received your payment details for <strong>Order #{order.orderNumber}</strong>. The
                seller (<strong>{company.name}</strong>) has been notified to verify your payment so
                your goods can proceed to the next stage.
              </p>
            </div>

            {/* Verification Status Details */}
            <div className="space-y-2 rounded-xl border border-amber-200/60 bg-white/70 p-4 text-left text-xs dark:border-amber-900/40 dark:bg-slate-900/60">
              <div className="flex justify-between text-slate-600 dark:text-slate-300">
                <span>Amount:</span>
                <span className="font-bold text-slate-900 dark:text-white">
                  {order.totalFormatted}
                </span>
              </div>
              <div className="flex justify-between text-slate-600 dark:text-slate-300">
                <span>Payment Method:</span>
                <span className="font-semibold text-slate-900 dark:text-white">
                  M-Pesa (Pochi la Biashara)
                </span>
              </div>
              <div className="flex justify-between text-slate-600 dark:text-slate-300">
                <span>Status:</span>
                <span className="font-semibold text-amber-700 dark:text-amber-400">
                  Seller is verifying payment
                </span>
              </div>
            </div>

            <p className="text-[11px] text-slate-500 dark:text-slate-400">
              This page automatically updates the moment the seller confirms payment. No further
              action is required on your end.
            </p>

            {/* Navigation & WhatsApp */}
            <div className="flex flex-col gap-2 pt-1">
              <Button
                asChild
                size="lg"
                className="w-full gap-2 bg-slate-900 text-white hover:bg-slate-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"
              >
                <a
                  href={
                    company.customDomain
                      ? '/'
                      : company.storeSlug
                        ? `/s/${company.storeSlug}`
                        : '/'
                  }
                >
                  <ShoppingBag className="h-4 w-4" /> Return to Store
                </a>
              </Button>

              {company.storeSlug && (
                <Button
                  asChild
                  type="button"
                  variant="outline"
                  size="sm"
                  className="w-full gap-2 border-slate-200 text-slate-700 hover:bg-slate-100 dark:border-slate-800 dark:text-slate-300"
                >
                  <a href={`/s/${company.storeSlug}/track`}>
                    <ExternalLink className="h-4 w-4" /> Track Order Status
                  </a>
                </Button>
              )}

              <button
                type="button"
                onClick={() => setShowEditForm(true)}
                className="pt-1 text-center text-[11px] font-medium text-slate-400 underline hover:text-slate-600 dark:hover:text-slate-200"
              >
                Need to re-enter payment reference or change method?
              </button>
            </div>
          </div>
        ) : (
          /* STATE 3: Payment Selection & Submission Form */
          <form onSubmit={submit} className="space-y-5">
            {/* Status Feedback Banner */}
            {status && (
              <div className="flex items-start gap-2.5 rounded-2xl border border-emerald-200/60 bg-emerald-50 p-4 text-xs font-medium text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300">
                <Loader2 className="mt-0.5 h-4 w-4 shrink-0 animate-spin text-emerald-600 dark:text-emerald-400" />
                <div>
                  <p className="font-bold">{status}</p>
                  <p className="mt-0.5 text-[11px] text-emerald-700 dark:text-emerald-400">
                    Waiting for payment confirmation. This page updates automatically.
                  </p>
                </div>
              </div>
            )}

            <div className="space-y-2.5">
              <Label className="text-xs font-bold uppercase tracking-wider text-slate-500">
                Choose Payment Method
              </Label>
              {availableMethods.length > 0 ? (
                <div className="grid gap-2">
                  {availableMethods.map((m) => {
                    const isSelected = method === m.id
                    return (
                      <button
                        key={m.id}
                        type="button"
                        onClick={() => setMethod(m.id)}
                        className={`flex items-center justify-between rounded-2xl border p-3.5 text-left text-sm font-medium transition-all ${
                          isSelected
                            ? 'border-slate-900 bg-slate-900 text-white shadow-md ring-2 ring-slate-900/20 dark:border-white dark:bg-white dark:text-slate-900 dark:ring-white/20'
                            : 'border-slate-200 bg-white text-slate-800 hover:border-slate-400 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-700'
                        }`}
                      >
                        <div className="flex items-center gap-3">
                          {m.id === 'mpesa' ||
                          (m.id === 'manual' && /pochi|mpesa|till/i.test(m.label)) ? (
                            <Smartphone className="h-5 w-5 shrink-0" />
                          ) : m.id === 'stripe' ||
                            m.id === 'paystack' ||
                            m.id === 'flutterwave' ||
                            m.id === 'paypal' ? (
                            <CreditCard className="h-5 w-5 shrink-0" />
                          ) : (
                            <ShieldCheck className="h-5 w-5 shrink-0" />
                          )}
                          <span>{m.label}</span>
                        </div>
                        {isSelected && <Check className="h-4 w-4 shrink-0 stroke-[3]" />}
                      </button>
                    )
                  })}
                </div>
              ) : (
                <p className="rounded-2xl border border-amber-200/60 bg-amber-50 p-4 text-sm font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                  No online payment methods are currently configured for this store. Please contact
                  the business for help.
                </p>
              )}
            </div>

            {/* Email for Card / Receipts */}
            {selectedMethod?.requiresEmail && (
              <div className="space-y-1.5">
                <Label
                  htmlFor="pay-email"
                  className="text-xs font-semibold text-slate-700 dark:text-slate-300"
                >
                  Email for Payment Receipt
                </Label>
                <Input
                  id="pay-email"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="you@example.com"
                  autoComplete="email"
                  className="rounded-xl"
                />
                <p className="text-xs text-slate-500">
                  Optional. Receipt will be sent to this email address.
                </p>
              </div>
            )}

            {/* STK Push Phone Number */}
            {selectedMethod?.requiresPhone && (
              <div className="space-y-1.5">
                <Label className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                  M-Pesa Phone Number
                </Label>
                <Input
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="e.g. 0712345678"
                  className="rounded-xl"
                />
                <p className="text-xs text-slate-500">
                  Enter your M-Pesa registered phone number to receive the payment prompt.
                </p>
              </div>
            )}

            {/* Dedicated Manual / Pochi la Biashara Instruction Card */}
            {method === 'manual' && manualDetails && (
              <div className="space-y-4 rounded-2xl border border-emerald-200/70 bg-emerald-50/60 p-4.5 dark:border-emerald-900/60 dark:bg-emerald-950/20">
                <div className="flex items-center justify-between">
                  <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">
                    <Smartphone className="h-3.5 w-3.5" /> {manualDetails.badge}
                  </span>
                  <span className="text-xs font-bold text-emerald-800 dark:text-emerald-300">
                    {order.totalFormatted}
                  </span>
                </div>

                {/* Prominently Highlighted Recipient Number */}
                {manualDetails.formattedPhone && (
                  <div className="rounded-xl border border-emerald-200/90 bg-white p-3.5 shadow-sm dark:border-emerald-900/80 dark:bg-slate-900">
                    <span className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                      Send Payment To (Number)
                    </span>
                    <div className="mt-1 flex items-center justify-between">
                      <span className="font-mono text-xl font-extrabold tracking-wider text-slate-900 dark:text-white">
                        {manualDetails.formattedPhone}
                      </span>
                      <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        onClick={() => copyNumber(manualDetails.rawPhone)}
                        className="h-8 gap-1.5 rounded-lg border border-slate-200 bg-slate-100 text-xs font-semibold text-slate-700 hover:bg-slate-200 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-200"
                      >
                        {copied ? (
                          <Check className="h-3.5 w-3.5 text-emerald-600" />
                        ) : (
                          <Copy className="h-3.5 w-3.5" />
                        )}
                        {copied ? 'Copied!' : 'Copy'}
                      </Button>
                    </div>
                    {manualDetails.accountName && (
                      <p className="mt-1.5 text-xs text-slate-600 dark:text-slate-400">
                        Name: <strong className="font-semibold">{manualDetails.accountName}</strong>
                      </p>
                    )}
                  </div>
                )}

                {/* Step-by-Step Instructions */}
                <div className="space-y-1 text-xs text-slate-700 dark:text-slate-300">
                  <p className="font-bold text-slate-900 dark:text-white">How to Pay:</p>
                  <ol className="list-decimal space-y-1 pl-4 leading-relaxed">
                    <li>Open M-Pesa on your phone.</li>
                    <li>
                      Select <strong>Lipa na M-Pesa</strong> →{' '}
                      <strong>{manualDetails.badge}</strong>.
                    </li>
                    <li>
                      Enter phone number{' '}
                      <strong className="font-mono font-bold">
                        {manualDetails.formattedPhone}
                      </strong>
                      .
                    </li>
                    <li>
                      Enter amount{' '}
                      <strong className="font-semibold">{order.totalFormatted}</strong>.
                    </li>
                    <li>Enter your M-Pesa PIN and complete payment.</li>
                  </ol>
                </div>

                {/* Customer Verification Code & Phone Inputs */}
                <div className="space-y-2 border-t border-emerald-200/60 pt-3">
                  <div className="space-y-1">
                    <Label
                      htmlFor="manual-tx-code"
                      className="text-xs font-semibold text-slate-800 dark:text-slate-200"
                    >
                      M-Pesa Transaction Code (Optional)
                    </Label>
                    <Input
                      id="manual-tx-code"
                      value={transactionCode}
                      onChange={(e) => setTransactionCode(e.target.value.toUpperCase())}
                      placeholder="e.g. QA12BC34DE"
                      className="font-mono uppercase tracking-wider rounded-xl bg-white dark:bg-slate-900"
                    />
                    <p className="text-[11px] text-slate-500">
                      Helps the seller confirm your payment even faster.
                    </p>
                  </div>

                  <div className="space-y-1">
                    <Label
                      htmlFor="manual-paying-phone"
                      className="text-xs font-semibold text-slate-800 dark:text-slate-200"
                    >
                      Your Paying Phone Number
                    </Label>
                    <Input
                      id="manual-paying-phone"
                      value={phone}
                      onChange={(e) => setPhone(e.target.value)}
                      placeholder="e.g. 0712345678"
                      className="rounded-xl bg-white dark:bg-slate-900"
                    />
                  </div>
                </div>
              </div>
            )}

            {/* Fallback instruction display if not standard format */}
            {method !== 'manual' && selectedMethod?.instructions && (
              <div className="whitespace-pre-line break-words rounded-2xl border border-slate-200/60 bg-slate-50 p-4 text-xs leading-relaxed text-slate-700 [overflow-wrap:anywhere] dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-300">
                {selectedMethod.instructions.split(/(https?:\/\/[^\s]+)/g).map((part, i) =>
                  part.match(/^https?:\/\//) ? (
                    <a
                      key={i}
                      href={part}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="break-all font-medium text-blue-600 underline hover:text-blue-800 dark:text-blue-400"
                    >
                      {part}
                    </a>
                  ) : (
                    part
                  )
                )}
              </div>
            )}

            <Button
              type="submit"
              disabled={!method || submitting}
              size="lg"
              className="w-full gap-2 rounded-2xl bg-slate-900 py-6 text-base font-semibold shadow-md transition-all hover:bg-slate-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"
            >
              {submitting
                ? 'Submitting Details…'
                : method === 'manual'
                  ? 'I Have Sent the Payment'
                  : method === 'mpesa'
                    ? 'Send M-Pesa Prompt'
                    : 'Proceed to Pay'}{' '}
              <ArrowRight className="h-5 w-5" />
            </Button>

            <div className="flex items-center justify-center gap-1.5 pt-1 text-xs text-slate-400">
              <Lock className="h-3.5 w-3.5" /> 256-Bit Encrypted & Secure Checkout
            </div>
          </form>
        )}
      </div>
    </div>
  )
}

