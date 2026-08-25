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
  ChevronDown,
  Megaphone,
  Calendar,
  Sparkles,
  Percent,
  QrCode,
  Store,
  Truck,
} from "lucide-react"
import { useEffect, useMemo, useState } from "react"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"
import { useCompanySettings } from "@/lib/api-hooks"
import type { LucideIcon } from "lucide-react"

export type DashboardNavItem = {
  name: string
  href: string
  icon: LucideIcon
}

export type DashboardNavGroup = {
  id: string
  label: string
  items: DashboardNavItem[]
  /** Collapsible subgroup (e.g. Advanced AI). */
  collapsible?: boolean
  defaultOpen?: boolean
}

/** Flat list kept for journeys/tests that iterate all destinations. */
export const dashboardNavigation: DashboardNavItem[] = [
  { name: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
  { name: "Chats", href: "/dashboard/chats", icon: MessageSquare },
  { name: "Orders", href: "/dashboard/orders", icon: ShoppingCart },
  { name: "Products", href: "/dashboard/products", icon: Package },
  { name: "Storefront", href: "/dashboard/storefront", icon: Store },
  { name: "Delivery", href: "/dashboard/delivery", icon: Truck },
  { name: "Taxes", href: "/dashboard/taxes", icon: Percent },
  { name: "Customers", href: "/dashboard/customers", icon: Users },
  { name: "Bookings", href: "/dashboard/bookings", icon: Calendar },
  { name: "Dine-in", href: "/dashboard/dine-in", icon: QrCode },
  { name: "Analytics", href: "/dashboard/analytics", icon: BarChart3 },
  { name: "Growth Engine", href: "/dashboard/growth", icon: Rocket },
  { name: "WhatsApp Campaigns", href: "/dashboard/whatsapp/campaigns", icon: Megaphone },
  { name: "FAQ Automation", href: "/dashboard/faq", icon: HelpCircle },
  { name: "Business Intelligence", href: "/dashboard/business-intelligence", icon: LineChart },
  { name: "Executive AI", href: "/dashboard/executive", icon: Brain },
  { name: "Cognitive AI", href: "/dashboard/cognitive", icon: BrainCircuit },
  { name: "Agent Ops", href: "/dashboard/agent-ops", icon: Activity },
  { name: "Mission Control", href: "/dashboard/mission-control", icon: Radar },
  { name: "AI Marketplace", href: "/dashboard/marketplace", icon: Puzzle },
  { name: "Subscription", href: "/dashboard/subscription", icon: CreditCard },
  { name: "Settings", href: "/dashboard/settings", icon: Settings },
]

export const dashboardNavGroups: DashboardNavGroup[] = [
  {
    id: "core",
    label: "Core",
    items: [
      { name: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
      { name: "Chats", href: "/dashboard/chats", icon: MessageSquare },
      { name: "Orders", href: "/dashboard/orders", icon: ShoppingCart },
      { name: "Products", href: "/dashboard/products", icon: Package },
      { name: "Storefront", href: "/dashboard/storefront", icon: Store },
      { name: "Delivery", href: "/dashboard/delivery", icon: Truck },
      { name: "Taxes", href: "/dashboard/taxes", icon: Percent },
      { name: "Customers", href: "/dashboard/customers", icon: Users },
      { name: "Bookings", href: "/dashboard/bookings", icon: Calendar },
      { name: "Dine-in", href: "/dashboard/dine-in", icon: QrCode },
    ],
  },
  {
    id: "growth",
    label: "Growth",
    items: [
      { name: "Analytics", href: "/dashboard/analytics", icon: BarChart3 },
      { name: "Growth Engine", href: "/dashboard/growth", icon: Rocket },
      { name: "WhatsApp Campaigns", href: "/dashboard/whatsapp/campaigns", icon: Megaphone },
      { name: "FAQ Automation", href: "/dashboard/faq", icon: HelpCircle },
      { name: "Business Intelligence", href: "/dashboard/business-intelligence", icon: LineChart },
    ],
  },
  {
    id: "advanced-ai",
    label: "Advanced AI",
    collapsible: true,
    defaultOpen: false,
    items: [
      { name: "Executive AI", href: "/dashboard/executive", icon: Brain },
      { name: "Cognitive AI", href: "/dashboard/cognitive", icon: BrainCircuit },
      { name: "Agent Ops", href: "/dashboard/agent-ops", icon: Activity },
      { name: "Mission Control", href: "/dashboard/mission-control", icon: Radar },
      { name: "AI Marketplace", href: "/dashboard/marketplace", icon: Puzzle },
    ],
  },
  {
    id: "workspace",
    label: "Workspace",
    items: [
      { name: "Subscription", href: "/dashboard/subscription", icon: CreditCard },
      { name: "Settings", href: "/dashboard/settings", icon: Settings },
    ],
  },
]

function isNavActive(pathname: string, href: string) {
  if (href === "/dashboard") {
    return pathname === "/dashboard"
  }
  return pathname === href || pathname.startsWith(href + "/")
}

function NavItemLink({
  item,
  collapsed,
  onNavigate,
}: {
  item: DashboardNavItem
  collapsed?: boolean
  onNavigate?: () => void
}) {
  const pathname = usePathname()
  const isActive = isNavActive(pathname, item.href)

  return (
    <Link
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
}

export function DashboardNavLinks({
  collapsed = false,
  onNavigate,
}: {
  collapsed?: boolean
  onNavigate?: () => void
}) {
  const pathname = usePathname()
  const { data: settings } = useCompanySettings()

  const aiActive = useMemo(
    () =>
      dashboardNavGroups
        .find((g) => g.id === "advanced-ai")
        ?.items.some((item) => isNavActive(pathname, item.href)) ?? false,
    [pathname]
  )
  const [userToggled, setUserToggled] = useState<boolean | null>(null)
  const aiOpen = userToggled !== null ? userToggled : aiActive

  const visibleGroups = useMemo(() => {
    return dashboardNavGroups.map((group) => {
      if (group.id !== "core" || !settings) {
        return group
      }

      const filteredItems = group.items.filter((item) => {
        if (item.href === "/dashboard/dine-in") {
          const isDineInAllowed = settings.enableDineIn || settings.dineInEnabled || settings.businessMode === "restaurant"
          return isDineInAllowed || isNavActive(pathname, item.href)
        }
        if (item.href === "/dashboard/bookings") {
          const isBookingsAllowed = settings.enableBookings ?? (settings.businessMode !== "retail")
          return isBookingsAllowed || isNavActive(pathname, item.href)
        }
        return true
      })

      return { ...group, items: filteredItems }
    })
  }, [settings, pathname])

  return (
    <nav className="flex flex-col gap-4 overflow-y-auto p-3 pb-6">
      {visibleGroups.map((group) => {
        if (group.collapsible) {
          const open = collapsed ? aiActive : aiOpen
          return (
            <div key={group.id} className="space-y-0.5">
              {!collapsed ? (
                <button
                  type="button"
                  onClick={() => setUserToggled((prev) => (prev !== null ? !prev : !aiActive))}
                  className="flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-sidebar-foreground/45 hover:text-sidebar-foreground/70"
                >
                  <span className="inline-flex items-center gap-1.5">
                    <Sparkles className="h-3 w-3" />
                    {group.label}
                  </span>
                  <ChevronDown
                    className={cn(
                      "h-3.5 w-3.5 transition-transform",
                      open && "rotate-180"
                    )}
                  />
                </button>
              ) : (
                <div
                  className="mx-auto mb-1 h-px w-6 bg-sidebar-border"
                  aria-hidden
                />
              )}
              {open &&
                group.items.map((item) => (
                  <NavItemLink
                    key={item.href}
                    item={item}
                    collapsed={collapsed}
                    onNavigate={onNavigate}
                  />
                ))}
            </div>
          )
        }

        return (
          <div key={group.id} className="space-y-0.5">
            {!collapsed && (
              <p className="px-2.5 pb-1 text-[11px] font-semibold uppercase tracking-wide text-sidebar-foreground/45">
                {group.label}
              </p>
            )}
            {collapsed && group.id !== "core" && (
              <div
                className="mx-auto mb-1 h-px w-6 bg-sidebar-border"
                aria-hidden
              />
            )}
            {group.items.map((item) => (
              <NavItemLink
                key={item.href}
                item={item}
                collapsed={collapsed}
                onNavigate={onNavigate}
              />
            ))}
          </div>
        )
      })}
    </nav>
  )
}

export function DashboardSidebar() {
  const [collapsed, setCollapsed] = useState(false)

  useEffect(() => {
    document.documentElement.style.setProperty(
      "--dashboard-sidebar-width",
      collapsed ? "4.5rem" : "15rem"
    )
    return () => {
      document.documentElement.style.removeProperty("--dashboard-sidebar-width")
    }
  }, [collapsed])

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
