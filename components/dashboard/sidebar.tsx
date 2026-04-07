"use client"

import Link from "next/link"
import { usePathname } from "next/navigation"
import { cn } from "@/lib/utils"
import {
  LayoutDashboard,
  MessageSquare,
  Users,
  ShoppingCart,
  Package,
  HelpCircle,
  BarChart3,
  CreditCard,
  Settings,
  ChevronLeft,
  ChevronRight,
} from "lucide-react"
import { useState } from "react"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"

export const dashboardNavigation = [
  { name: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
  { name: "Chats", href: "/dashboard/chats", icon: MessageSquare },
  { name: "Customers", href: "/dashboard/customers", icon: Users },
  { name: "Orders", href: "/dashboard/orders", icon: ShoppingCart },
  { name: "Products", href: "/dashboard/products", icon: Package },
  { name: "FAQ Automation", href: "/dashboard/faq", icon: HelpCircle },
  { name: "Analytics", href: "/dashboard/analytics", icon: BarChart3 },
  { name: "Subscription", href: "/dashboard/subscription", icon: CreditCard },
  { name: "Settings", href: "/dashboard/settings", icon: Settings },
]

export function DashboardNavLinks({
  collapsed = false,
  onNavigate,
}: {
  collapsed?: boolean
  onNavigate?: () => void
}) {
  const pathname = usePathname()

  return (
    <nav className="flex flex-col gap-1 p-2">
      {dashboardNavigation.map((item) => {
        const isActive = pathname === item.href || pathname.startsWith(item.href + "/")
        return (
          <Link
            key={item.name}
            href={item.href}
            onClick={onNavigate}
            className={cn(
              "flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors",
              isActive
                ? "bg-sidebar-accent text-sidebar-accent-foreground"
                : "text-sidebar-foreground hover:bg-sidebar-accent/50",
              collapsed && "justify-center"
            )}
            title={collapsed ? item.name : undefined}
          >
            <item.icon className={cn("h-5 w-5 shrink-0", isActive && "text-primary")} />
            {!collapsed && <span>{item.name}</span>}
          </Link>
        )
      })}
    </nav>
  )
}

export function DashboardSidebar() {
  const [collapsed, setCollapsed] = useState(false)

  return (
    <aside
      className={cn(
        "fixed left-0 top-0 z-40 hidden h-screen border-r border-border bg-sidebar transition-all duration-300 md:block",
        collapsed ? "w-16" : "w-64"
      )}
    >
      <div className="flex h-16 items-center justify-between border-b border-sidebar-border px-4">
        {!collapsed && (
          <Link href="/dashboard">
            <AppLogoAndName variant="sidebar" />
          </Link>
        )}
        {collapsed && (
          <Link href="/dashboard" className="mx-auto flex justify-center">
            <AppLogoAndName variant="sidebar" iconOnly />
          </Link>
        )}
        <button
          onClick={() => setCollapsed(!collapsed)}
          className={cn(
            "flex h-6 w-6 items-center justify-center rounded-md text-sidebar-foreground hover:bg-sidebar-accent",
            collapsed && "mx-auto mt-2"
          )}
        >
          {collapsed ? (
            <ChevronRight className="h-4 w-4" />
          ) : (
            <ChevronLeft className="h-4 w-4" />
          )}
        </button>
      </div>

      <DashboardNavLinks collapsed={collapsed} />
    </aside>
  )
}
