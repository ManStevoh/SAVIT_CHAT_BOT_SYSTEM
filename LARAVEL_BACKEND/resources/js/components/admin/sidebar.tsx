"use client"

import Link from "next/link"
import { usePathname } from "next/navigation"
import { cn } from "@/lib/utils"
import {
  LayoutDashboard,
  Building2,
  Users,
  CreditCard,
  DollarSign,
  Rocket,
  Bot,
  FileText,
  Settings,
  MessageSquare,
  Layers,
  Wallet,
  Quote,
  HelpCircle,
  Newspaper,
  Layout,
  Tag,
} from "lucide-react"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"

const navigationMain = [
  { name: "Platform Overview", href: "/admin", icon: LayoutDashboard },
  { name: "Companies", href: "/admin/companies", icon: Building2 },
  { name: "Users", href: "/admin/users", icon: Users },
  { name: "Plans", href: "/admin/plans", icon: Layers },
  { name: "Offers & Coupons", href: "/admin/offers", icon: Tag },
  { name: "Subscriptions", href: "/admin/subscriptions", icon: CreditCard },
  { name: "Revenue", href: "/admin/revenue", icon: DollarSign },
  { name: "Growth Portfolio", href: "/admin/growth", icon: Rocket },
]

const navigationConfig = [
  { name: "Settings", href: "/admin/settings", icon: Settings, subtitle: "APIs, email, security" },
  { name: "AI Models", href: "/admin/ai-models", icon: Bot, subtitle: "Providers, costs, defaults" },
  { name: "WhatsApp", href: "/admin/whatsapp", icon: MessageSquare, subtitle: "Company connections" },
  { name: "Payment Gateways", href: "/admin/payment-gateways", icon: Wallet, subtitle: "Stripe, M-Pesa" },
]

const navigationOther = [
  { name: "Website CMS", href: "/admin/cms", icon: Layout },
  { name: "Blog", href: "/admin/blog", icon: Newspaper },
  { name: "Testimonials", href: "/admin/testimonials", icon: Quote },
  { name: "Landing FAQ", href: "/admin/landing-faqs", icon: HelpCircle },
  { name: "AI Usage", href: "/admin/ai-usage", icon: Bot },
  { name: "AI Learning", href: "/admin/ai-learning", icon: Bot },
  { name: "System Logs", href: "/admin/logs", icon: FileText },
]

export function AdminSidebar() {
  const pathname = usePathname()

  return (
    <aside className="fixed left-0 top-0 z-40 flex h-screen w-64 flex-col border-r border-border bg-sidebar">
      <div className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border px-4">
        <AppLogoAndName
          variant="admin"
          showAdminBadge
          suffix={<span className="ml-2 text-xs text-muted-foreground">Admin</span>}
        />
      </div>

      <nav className="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto p-2">
        {navigationMain.map((item) => {
          const isActive = pathname === item.href || (item.href !== "/admin" && pathname.startsWith(item.href))
          return (
            <Link
              key={item.name}
              href={item.href}
              className={cn(
                "flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors",
                isActive
                  ? "bg-sidebar-accent text-sidebar-accent-foreground"
                  : "text-sidebar-foreground hover:bg-sidebar-accent/50"
              )}
            >
              <item.icon className={cn("h-5 w-5 shrink-0", isActive && "text-primary")} />
              <span>{item.name}</span>
            </Link>
          )
        })}
        <div className="mt-2 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
          Configuration
        </div>
        {navigationConfig.map((item) => {
          const isActive = pathname === item.href || (item.href !== "/admin" && pathname.startsWith(item.href))
          return (
            <Link
              key={item.name}
              href={item.href}
              className={cn(
                "flex flex-col items-start gap-0.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors",
                isActive
                  ? "bg-sidebar-accent text-sidebar-accent-foreground"
                  : "text-sidebar-foreground hover:bg-sidebar-accent/50"
              )}
            >
              <span className="flex items-center gap-3">
                <item.icon className={cn("h-5 w-5 shrink-0", isActive && "text-primary")} />
                {item.name}
              </span>
              {"subtitle" in item && item.subtitle && (
                <span className="ml-8 text-xs font-normal text-muted-foreground">{item.subtitle}</span>
              )}
            </Link>
          )
        })}
        <div className="mt-2 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
          Content &amp; logs
        </div>
        {navigationOther.map((item) => {
          const isActive = pathname === item.href || (item.href !== "/admin" && pathname.startsWith(item.href))
          return (
            <Link
              key={item.name}
              href={item.href}
              className={cn(
                "flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors",
                isActive
                  ? "bg-sidebar-accent text-sidebar-accent-foreground"
                  : "text-sidebar-foreground hover:bg-sidebar-accent/50"
              )}
            >
              <item.icon className={cn("h-5 w-5 shrink-0", isActive && "text-primary")} />
              <span>{item.name}</span>
            </Link>
          )
        })}
      </nav>

      <div className="shrink-0 border-t border-sidebar-border p-4">
        <Link
          href="/dashboard"
          className="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
        >
          <MessageSquare className="h-4 w-4" />
          Switch to User Dashboard
        </Link>
      </div>
    </aside>
  )
}
