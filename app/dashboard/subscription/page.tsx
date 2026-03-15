"use client"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Progress } from "@/components/ui/progress"
import { Badge } from "@/components/ui/badge"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Check, CreditCard, Download, MessageSquare, Users, Zap } from "lucide-react"
// API: GET /api/company/subscription (useSubscription), GET /api/company/subscription/invoices (useSubscriptionInvoices)
import { useSubscription, useSubscriptionInvoices, type BillingInvoice } from "@/lib/api-hooks"

// Available plans: API GET /api/company/plans or static until backend provides
const PLANS_PLACEHOLDER = [
  {
    name: "Starter",
    price: "$29",
    current: false,
    features: ["1 WhatsApp number", "1,000 messages/month", "Basic AI chatbot", "Email support"],
  },
  {
    name: "Growth",
    price: "$99",
    current: true,
    features: ["3 WhatsApp numbers", "10,000 messages/month", "Advanced AI with GPT-4", "Multi-agent inbox", "Analytics dashboard", "Priority support"],
  },
  {
    name: "Enterprise",
    price: "Custom",
    current: false,
    features: ["Unlimited WhatsApp numbers", "Unlimited messages", "Custom AI training", "Dedicated account manager", "Custom integrations", "SLA guarantee"],
  },
]

export default function SubscriptionPage() {
  const { data: subscription, error, isLoading, mutate } = useSubscription()
  const { data: billingHistory = [] } = useSubscriptionInvoices()

  if (isLoading && !subscription) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Subscription</h1>
          <p className="text-muted-foreground">Manage your subscription and billing</p>
        </div>
        <Card>
          <CardContent className="p-8">
            <div className="flex items-center justify-center gap-2 text-muted-foreground">
              <span className="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
              Loading subscription...
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Subscription</h1>
          <p className="text-muted-foreground">Manage your subscription and billing</p>
        </div>
        <Card className="border-destructive/50">
          <CardContent className="p-6">
            <p className="text-destructive">Failed to load subscription. Please try again.</p>
            <Button variant="outline" className="mt-2" onClick={() => mutate()}>
              Retry
            </Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  const planName = subscription?.plan === "professional" ? "Growth" : subscription?.plan === "starter" ? "Starter" : subscription?.plan === "enterprise" ? "Enterprise" : "Growth"
  const planPrice = subscription?.amount ? `$${subscription.amount}` : "$99"
  const renewalDate = subscription?.endDate ? new Date(subscription.endDate).toLocaleDateString("en-US", { month: "long", day: "numeric", year: "numeric" }) : "April 14, 2024"
  const status = subscription?.status ?? "active"

  // Usage: TODO from API GET /api/company/subscription/usage when available
  const usage = [
    { name: "Messages", used: 7234, limit: 10000, icon: MessageSquare },
    { name: "WhatsApp Numbers", used: 2, limit: 3, icon: Zap },
    { name: "Team Members", used: 4, limit: 10, icon: Users },
  ]

  const plans = PLANS_PLACEHOLDER.map((p) => ({ ...p, current: p.name === planName }))
  const billingList = billingHistory.length > 0 ? billingHistory : [
    { id: "INV-001", date: "Mar 14, 2024", amount: planPrice + ".00", status: "paid" },
    { id: "INV-002", date: "Feb 14, 2024", amount: planPrice + ".00", status: "paid" },
  ] as BillingInvoice[]

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Subscription</h1>
        <p className="text-muted-foreground">Manage your subscription and billing</p>
      </div>

      {/* Current Plan — from useSubscription() */}
      <Card>
        <CardHeader>
          <CardTitle>Current Plan</CardTitle>
          <CardDescription>Your subscription details</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10">
                <Zap className="h-8 w-8 text-primary" />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <h3 className="text-2xl font-bold text-foreground">{planName}</h3>
                  <Badge>{status}</Badge>
                </div>
                <p className="text-muted-foreground">
                  {planPrice}/month • Renews on {renewalDate}
                </p>
              </div>
            </div>
            <div className="flex gap-2">
              <Button variant="outline">Change Plan</Button>
              <Button variant="outline">Cancel</Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Usage — TODO: GET /api/company/subscription/usage */}
      <Card>
        <CardHeader>
          <CardTitle>Usage</CardTitle>
          <CardDescription>Current billing period usage</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-6">
            {usage.map((item) => (
              <div key={item.name} className="space-y-2">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <item.icon className="h-4 w-4 text-muted-foreground" />
                    <span className="font-medium text-foreground">{item.name}</span>
                  </div>
                  <span className="text-sm text-muted-foreground">
                    {item.used.toLocaleString()} / {item.limit.toLocaleString()}
                  </span>
                </div>
                <Progress value={(item.used / item.limit) * 100} className="h-2" />
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Available Plans — API: GET /api/company/plans (optional) */}
      <Card>
        <CardHeader>
          <CardTitle>Available Plans</CardTitle>
          <CardDescription>Compare and switch plans</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid gap-6 md:grid-cols-3">
            {plans.map((plan) => (
              <div
                key={plan.name}
                className={`relative rounded-xl border p-6 ${
                  plan.current ? "border-primary bg-primary/5" : "border-border"
                }`}
              >
                {plan.current && (
                  <Badge className="absolute -top-3 left-1/2 -translate-x-1/2">
                    Current Plan
                  </Badge>
                )}
                <div className="text-center mb-6">
                  <h3 className="text-lg font-semibold text-foreground">{plan.name}</h3>
                  <div className="mt-2">
                    <span className="text-3xl font-bold text-foreground">{plan.price}</span>
                    {plan.price !== "Custom" && (
                      <span className="text-muted-foreground">/month</span>
                    )}
                  </div>
                </div>
                <ul className="space-y-3 mb-6">
                  {plan.features.map((feature) => (
                    <li key={feature} className="flex items-center gap-2 text-sm text-muted-foreground">
                      <Check className="h-4 w-4 text-primary shrink-0" />
                      {feature}
                    </li>
                  ))}
                </ul>
                <Button
                  className="w-full"
                  variant={plan.current ? "secondary" : "default"}
                  disabled={plan.current}
                >
                  {plan.current ? "Current Plan" : plan.price === "Custom" ? "Contact Sales" : "Upgrade"}
                </Button>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Billing History — API: GET /api/company/subscription/invoices */}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <div>
            <CardTitle>Billing History</CardTitle>
            <CardDescription>Your recent invoices</CardDescription>
          </div>
          <Button variant="outline" size="sm">
            <CreditCard className="h-4 w-4 mr-2" />
            Update Payment
          </Button>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Invoice</TableHead>
                <TableHead>Date</TableHead>
                <TableHead>Amount</TableHead>
                <TableHead>Status</TableHead>
                <TableHead></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {billingList.map((invoice) => (
                <TableRow key={invoice.id}>
                  <TableCell className="font-medium text-foreground">{invoice.id}</TableCell>
                  <TableCell className="text-muted-foreground">{invoice.date}</TableCell>
                  <TableCell className="text-foreground">{invoice.amount}</TableCell>
                  <TableCell>
                    <Badge variant="default">{invoice.status}</Badge>
                  </TableCell>
                  <TableCell>
                    <Button variant="ghost" size="icon">
                      <Download className="h-4 w-4" />
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}
