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
  Bot,
  FileText,
  Settings,
  MessageSquare,
  Layers,
  Wallet,
} from "lucide-react"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"

const navigation = [
  { name: "Platform Overview", href: "/admin", icon: LayoutDashboard },
  { name: "Companies", href: "/admin/companies", icon: Building2 },
  { name: "Users", href: "/admin/users", icon: Users },
  { name: "Plans", href: "/admin/plans", icon: Layers },
  { name: "Subscriptions", href: "/admin/subscriptions", icon: CreditCard },
  { name: "Revenue", href: "/admin/revenue", icon: DollarSign },
  { name: "Payment Gateways", href: "/admin/payment-gateways", icon: Wallet },
  { name: "AI Usage", href: "/admin/ai-usage", icon: Bot },
  { name: "System Logs", href: "/admin/logs", icon: FileText },
  { name: "Settings", href: "/admin/settings", icon: Settings },
]

export function AdminSidebar() {
  const pathname = usePathname()

  return (
    <aside className="fixed left-0 top-0 z-40 h-screen w-64 border-r border-border bg-sidebar">
      <div className="flex h-16 items-center gap-2 border-b border-sidebar-border px-4">
        <AppLogoAndName
          variant="admin"
          showAdminBadge
          suffix={<span className="ml-2 text-xs text-muted-foreground">Admin</span>}
        />
      </div>

      <nav className="flex flex-col gap-1 p-2">
        {navigation.map((item) => {
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

      <div className="absolute bottom-4 left-4 right-4">
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
