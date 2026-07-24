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
  Rocket,
  Brain,
  BrainCircuit,
  Activity,
  LineChart,
  Radar,
  Puzzle,
  CreditCard,
  Settings,
  ChevronLeft,
  ChevronRight,
  Megaphone,
  Calendar,
} from "lucide-react"
import { useState } from "react"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"

export const dashboardNavigation = [
  { name: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
  { name: "Chats", href: "/dashboard/chats", icon: MessageSquare },
  { name: "Customers", href: "/dashboard/customers", icon: Users },
  { name: "Orders", href: "/dashboard/orders", icon: ShoppingCart },
  { name: "Products", href: "/dashboard/products", icon: Package },
  { name: "Bookings", href: "/dashboard/bookings", icon: Calendar },
  { name: "FAQ Automation", href: "/dashboard/faq", icon: HelpCircle },
  { name: "Analytics", href: "/dashboard/analytics", icon: BarChart3 },
  { name: "Executive AI", href: "/dashboard/executive", icon: Brain },
  { name: "Cognitive AI", href: "/dashboard/cognitive", icon: BrainCircuit },
  { name: "Agent Ops", href: "/dashboard/agent-ops", icon: Activity },
  { name: "Mission Control", href: "/dashboard/mission-control", icon: Radar },
  { name: "AI Marketplace", href: "/dashboard/marketplace", icon: Puzzle },
  { name: "Business Intelligence", href: "/dashboard/business-intelligence", icon: LineChart },
  { name: "Growth Engine", href: "/dashboard/growth", icon: Rocket },
  { name: "WhatsApp Campaigns", href: "/dashboard/whatsapp/campaigns", icon: Megaphone },
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
    <nav className="flex flex-col gap-0.5 p-3">
      {dashboardNavigation.map((item) => {
        const isActive =
          pathname === item.href || pathname.startsWith(item.href + "/")
        return (
          <Link
            key={item.name}
            href={item.href}
            onClick={onNavigate}
            className={cn(
              "relative flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium transition-colors",
              isActive
                ? "bg-sidebar-accent text-sidebar-accent-foreground"
                : "text-sidebar-foreground/70 hover:bg-sidebar-accent/60 hover:text-sidebar-foreground",
              collapsed && "justify-center px-2"
            )}
            title={collapsed ? item.name : undefined}
          >
            {isActive && (
              <span className="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 rounded-full bg-primary" />
            )}
            <item.icon
              className={cn("h-4 w-4 shrink-0", isActive && "text-primary")}
              strokeWidth={isActive ? 2 : 1.75}
            />
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
        "fixed left-0 top-0 z-40 hidden h-screen border-r border-sidebar-border bg-sidebar transition-all duration-300 md:block",
        collapsed ? "w-[4.5rem]" : "w-60"
      )}
    >
      <div className="flex h-14 items-center justify-between border-b border-sidebar-border px-3">
        {!collapsed ? (
          <Link href="/dashboard" className="min-w-0 pl-1">
            <AppLogoAndName variant="sidebar" />
          </Link>
        ) : (
          <Link href="/dashboard" className="mx-auto flex justify-center">
            <AppLogoAndName variant="sidebar" iconOnly />
          </Link>
        )}
        <button
          onClick={() => setCollapsed(!collapsed)}
          className={cn(
            "flex h-7 w-7 items-center justify-center rounded-md text-sidebar-foreground/60 transition-colors hover:bg-sidebar-accent hover:text-sidebar-foreground",
            collapsed && "mx-auto"
          )}
          aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
        >
          {collapsed ? (
            <ChevronRight className="h-3.5 w-3.5" />
          ) : (
            <ChevronLeft className="h-3.5 w-3.5" />
          )}
        </button>
      </div>

      <DashboardNavLinks collapsed={collapsed} />
    </aside>
  )
}
