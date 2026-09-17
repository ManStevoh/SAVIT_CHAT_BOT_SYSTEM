"use client"

import Link from "next/link"
import { usePathname, useSearchParams } from "next/navigation"
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
  Clock,
  CheckCircle,
  XCircle,
  LayoutList,
  Palette,
  Link2,
  SlidersHorizontal,
  CalendarDays,
  History,
  Wrench,
  UtensilsCrossed,
  ScanLine,
  Printer,
  Bot,
  Building2,
} from "lucide-react"
import { useEffect, useMemo, useState } from "react"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"
import { useCompanySettings, useSubscription } from "@/lib/api-hooks"
import { isStarterPlan } from "@/lib/use-plan"
import type { LucideIcon } from "lucide-react"

/** Nav destinations that require Growth/Custom (hidden on Starter to keep the workspace focused). */
const GROWTH_ONLY_HREFS = new Set([
  "/dashboard/growth",
  "/dashboard/whatsapp/campaigns",
  "/dashboard/business-intelligence",
  "/dashboard/executive",
  "/dashboard/cognitive",
  "/dashboard/agent-ops",
  "/dashboard/mission-control",
  "/dashboard/marketplace",
])

export type DashboardNavItem = {
  name: string
  href: string
  icon: LucideIcon
  children?: DashboardNavItem[]
}

export type OrderPipelineStatus = "all" | "pending" | "waiting_shipping" | "shipped_delivered" | "failed"

export const orderPipelineItems: DashboardNavItem[] = [
  { name: "All Orders", href: "/dashboard/orders", icon: LayoutList },
  { name: "Pending", href: "/dashboard/orders?status=pending", icon: Clock },
  { name: "Ready to Ship", href: "/dashboard/orders?status=waiting_shipping", icon: Truck },
  { name: "Shipped & Delivered", href: "/dashboard/orders?status=shipped_delivered", icon: CheckCircle },
  { name: "Failed / Cancelled", href: "/dashboard/orders?status=failed", icon: XCircle },
]

export function ordersHref(status: OrderPipelineStatus = "all", search?: string) {
  const params = new URLSearchParams()
  if (status !== "all") params.set("status", status)
  if (search?.trim()) params.set("search", search.trim())
  const q = params.toString()
  return q ? `/dashboard/orders?${q}` : "/dashboard/orders"
}

export function parseOrderPipelineStatus(value: string | null | undefined): OrderPipelineStatus {
  if (value === "pending" || value === "waiting_shipping" || value === "shipped_delivered" || value === "failed") {
    return value
  }
  return "all"
}

export type StorefrontTab = "design" | "link" | "advanced"

export const storefrontNavItems: DashboardNavItem[] = [
  { name: "Design", href: "/dashboard/storefront", icon: Palette },
  { name: "Store link & settings", href: "/dashboard/storefront?tab=link", icon: Link2 },
  { name: "Advanced settings", href: "/dashboard/storefront?tab=advanced", icon: SlidersHorizontal },
]

export function parseStorefrontTab(value: string | null | undefined): StorefrontTab {
  if (value === "link" || value === "settings") return "link"
  if (value === "advanced") return "advanced"
  return "design"
}

export type BookingsTab = "upcoming" | "calendar" | "past" | "setup"

export const bookingsNavItems: DashboardNavItem[] = [
  { name: "Upcoming", href: "/dashboard/bookings", icon: Calendar },
  { name: "Calendar", href: "/dashboard/bookings?tab=calendar", icon: CalendarDays },
  { name: "Past", href: "/dashboard/bookings?tab=past", icon: History },
  { name: "Setup", href: "/dashboard/bookings?tab=setup", icon: Wrench },
]

export function parseBookingsTab(value: string | null | undefined): BookingsTab {
  if (value === "calendar" || value === "past" || value === "setup" || value === "schedule") {
    return value === "schedule" ? "upcoming" : value
  }
  return "upcoming"
}

export type DineInTab = "tables" | "scan" | "print"

export const dineInNavItems: DashboardNavItem[] = [
  { name: "Tables", href: "/dashboard/dine-in", icon: UtensilsCrossed },
  { name: "Scan behavior", href: "/dashboard/dine-in?tab=scan", icon: ScanLine },
  { name: "Print QRs", href: "/dashboard/dine-in?tab=print", icon: Printer },
]

export function parseDineInTab(value: string | null | undefined): DineInTab {
  if (value === "scan" || value === "print") return value
  return "tables"
}

export type AnalyticsTab = "messages" | "orders" | "customers" | "products"

export const analyticsNavItems: DashboardNavItem[] = [
  { name: "Messages", href: "/dashboard/analytics", icon: MessageSquare },
  { name: "Orders", href: "/dashboard/analytics?tab=orders", icon: ShoppingCart },
  { name: "Customers", href: "/dashboard/analytics?tab=customers", icon: Users },
  { name: "Products", href: "/dashboard/analytics?tab=products", icon: Package },
]

export function parseAnalyticsTab(value: string | null | undefined, product?: string | null): AnalyticsTab {
  if (product?.trim()) return "products"
  if (value === "orders" || value === "customers" || value === "products") return value
  return "messages"
}

export type FaqTab = "faqs" | "settings"

export const faqNavItems: DashboardNavItem[] = [
  { name: "FAQ Responses", href: "/dashboard/faq", icon: HelpCircle },
  { name: "Bot Settings", href: "/dashboard/faq?tab=settings", icon: Bot },
]

export function parseFaqTab(value: string | null | undefined): FaqTab {
  return value === "settings" ? "settings" : "faqs"
}

export type SettingsTab = "profile" | "branding" | "order-payments" | "whatsapp" | "ai" | "team"

export const settingsNavItems: DashboardNavItem[] = [
  { name: "Business", href: "/dashboard/settings", icon: Building2 },
  { name: "Brand", href: "/dashboard/settings?tab=branding", icon: Palette },
  { name: "Payments", href: "/dashboard/settings?tab=order-payments", icon: CreditCard },
  { name: "WhatsApp", href: "/dashboard/settings?tab=whatsapp", icon: MessageSquare },
  { name: "AI assistant", href: "/dashboard/settings?tab=ai", icon: Bot },
  { name: "Team", href: "/dashboard/settings?tab=team", icon: Users },
]

export function parseSettingsTab(value: string | null | undefined): SettingsTab {
  switch (value) {
    case "branding":
    case "appearance":
      return "branding"
    case "order-payments":
      return "order-payments"
    case "whatsapp":
      return "whatsapp"
    case "ai":
    case "byok":
    case "notifications":
      return "ai"
    case "team":
      return "team"
    default:
      return "profile"
  }
}

const defaultQueryByPath: Record<string, { key: string; emptyValues: string[] }> = {
  "/dashboard/orders": { key: "status", emptyValues: ["", "all"] },
  "/dashboard/storefront": { key: "tab", emptyValues: ["", "design"] },
  "/dashboard/bookings": { key: "tab", emptyValues: ["", "upcoming", "schedule"] },
  "/dashboard/dine-in": { key: "tab", emptyValues: ["", "tables"] },
  "/dashboard/analytics": { key: "tab", emptyValues: ["", "messages"] },
  "/dashboard/faq": { key: "tab", emptyValues: ["", "faqs"] },
  "/dashboard/settings": { key: "tab", emptyValues: ["", "profile"] },
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
      { name: "Orders", href: "/dashboard/orders", icon: ShoppingCart, children: orderPipelineItems },
      { name: "Products", href: "/dashboard/products", icon: Package },
      { name: "Storefront", href: "/dashboard/storefront", icon: Store, children: storefrontNavItems },
      { name: "Delivery", href: "/dashboard/delivery", icon: Truck },
      { name: "Taxes", href: "/dashboard/taxes", icon: Percent },
      { name: "Customers", href: "/dashboard/customers", icon: Users },
      { name: "Bookings", href: "/dashboard/bookings", icon: Calendar, children: bookingsNavItems },
      { name: "Dine-in", href: "/dashboard/dine-in", icon: QrCode, children: dineInNavItems },
    ],
  },
  {
    id: "growth",
    label: "Growth",
    items: [
      { name: "Analytics", href: "/dashboard/analytics", icon: BarChart3, children: analyticsNavItems },
      { name: "Growth Engine", href: "/dashboard/growth", icon: Rocket },
      { name: "WhatsApp Campaigns", href: "/dashboard/whatsapp/campaigns", icon: Megaphone },
      { name: "FAQ Automation", href: "/dashboard/faq", icon: HelpCircle, children: faqNavItems },
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
      { name: "Settings", href: "/dashboard/settings", icon: Settings, children: settingsNavItems },
    ],
  },
]

function navPath(href: string) {
  return href.split("?")[0]
}

function isPathActive(pathname: string, href: string) {
  const path = navPath(href)
  if (path === "/dashboard") {
    return pathname === "/dashboard"
  }
  return pathname === path || pathname.startsWith(path + "/")
}

function isNavActive(pathname: string, href: string, search = "") {
  if (!isPathActive(pathname, href)) {
    return false
  }

  const query = href.includes("?") ? href.slice(href.indexOf("?") + 1) : ""
  const current = new URLSearchParams(search)

  if (query) {
    const wanted = new URLSearchParams(query)
    for (const [key, value] of wanted.entries()) {
      if (current.get(key) !== value) {
        return false
      }
    }
    return true
  }

  const defaults = defaultQueryByPath[navPath(href)]
  if (defaults) {
    const value = current.get(defaults.key) ?? ""
    return defaults.emptyValues.includes(value)
  }

  return true
}

function NavItemLink({
  item,
  collapsed,
  onNavigate,
  nested = false,
  forceActive,
}: {
  item: DashboardNavItem
  collapsed?: boolean
  onNavigate?: () => void
  nested?: boolean
  forceActive?: boolean
}) {
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const search = searchParams.toString()
  const isActive = forceActive ?? isNavActive(pathname, item.href, search)

  return (
    <Link
      href={item.href}
      onClick={onNavigate}
      className={cn(
        "relative flex items-center gap-2.5 rounded-lg py-2 text-[13px] font-medium transition-colors",
        nested ? "px-2.5 py-1.5 text-[12px]" : "px-2.5",
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
        className={cn("shrink-0", nested ? "h-3.5 w-3.5" : "h-4 w-4", isActive && "text-primary")}
        strokeWidth={isActive ? 2 : 1.75}
      />
      {!collapsed && <span className="truncate">{item.name}</span>}
    </Link>
  )
}

function ExpandableNavItem({
  item,
  collapsed,
  onNavigate,
}: {
  item: DashboardNavItem
  collapsed?: boolean
  onNavigate?: () => void
}) {
  const pathname = usePathname()
  const sectionActive = isPathActive(pathname, item.href)
  const [userToggled, setUserToggled] = useState<boolean | null>(null)
  const open = collapsed ? false : userToggled !== null ? userToggled : sectionActive

  if (collapsed) {
    return (
      <NavItemLink
        item={item}
        collapsed
        onNavigate={onNavigate}
        forceActive={sectionActive}
      />
    )
  }

  return (
    <div className="space-y-0.5">
      <button
        type="button"
        onClick={() => setUserToggled((prev) => !(prev ?? sectionActive))}
        className={cn(
          "relative flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium transition-colors hover:bg-sidebar-accent/60 hover:text-sidebar-foreground",
          sectionActive ? "text-sidebar-accent-foreground" : "text-sidebar-foreground/70"
        )}
        aria-expanded={open}
      >
        {sectionActive && (
          <span className="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 rounded-full bg-primary" />
        )}
        <item.icon
          className={cn("h-4 w-4 shrink-0", sectionActive && "text-primary")}
          strokeWidth={sectionActive ? 2 : 1.75}
        />
        <span className="min-w-0 flex-1 truncate text-left">{item.name}</span>
        <ChevronDown className={cn("h-3.5 w-3.5 shrink-0 text-sidebar-foreground/50 transition-transform", open && "rotate-180")} />
      </button>
      {open && (
        <div className="ml-3 space-y-0.5 border-l border-sidebar-border pl-2">
          {item.children?.map((child) => (
            <NavItemLink
              key={`${child.name}-${child.href}`}
              item={child}
              nested
              onNavigate={onNavigate}
            />
          ))}
        </div>
      )}
    </div>
  )
}

function renderNavItem(item: DashboardNavItem, collapsed: boolean, onNavigate?: () => void) {
  if (item.children?.length) {
    return (
      <ExpandableNavItem
        key={item.href}
        item={item}
        collapsed={collapsed}
        onNavigate={onNavigate}
      />
    )
  }
  return (
    <NavItemLink
      key={item.href}
      item={item}
      collapsed={collapsed}
      onNavigate={onNavigate}
    />
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
  const { data: subscription } = useSubscription()
  const isStarter = isStarterPlan(subscription?.plan)

  const aiActive = useMemo(
    () =>
      dashboardNavGroups
        .find((g) => g.id === "advanced-ai")
        ?.items.some((item) => isPathActive(pathname, item.href)) ?? false,
    [pathname]
  )
  const [userToggled, setUserToggled] = useState<boolean | null>(null)
  const aiOpen = userToggled !== null ? userToggled : aiActive

  const visibleGroups = useMemo(() => {
    return dashboardNavGroups
      .map((group) => {
        let items = group.items
        if (isStarter) {
          items = items.filter((item) => !GROWTH_ONLY_HREFS.has(navPath(item.href)))
        }
        if (group.id !== "core" || !settings) {
          return { ...group, items }
        }

        const filteredItems = items.filter((item) => {
          if (navPath(item.href) === "/dashboard/bookings") {
            const isBookingsAllowed = settings.enableBookings ?? (settings.businessMode !== "retail")
            return isBookingsAllowed || isPathActive(pathname, item.href)
          }
          return true
        })

        return { ...group, items: filteredItems }
      })
      .filter((group) => group.items.length > 0 || group.id === "core")
  }, [settings, pathname, isStarter])

  const showUpsell = isStarter && !collapsed

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
              {open && group.items.map((item) => renderNavItem(item, collapsed, onNavigate))}
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
            {group.items.map((item) => renderNavItem(item, collapsed, onNavigate))}
          </div>
        )
      })}
      {showUpsell && (
        <div className="mt-2 rounded-xl border border-primary/20 bg-gradient-to-br from-primary/[0.08] to-transparent p-3">
          <p className="flex items-center gap-1.5 text-[13px] font-semibold text-foreground">
            <Sparkles className="h-3.5 w-3.5 text-primary" />
            Unlock WhatsApp + more
          </p>
          <p className="mt-1 text-xs leading-relaxed text-sidebar-foreground/70">
            Growth adds WhatsApp selling, campaigns, 50 products and 1,000 AI chats — KSh 2,000/mo.
          </p>
          <Link
            href="/dashboard/subscription#plans"
            onClick={onNavigate}
            className="mt-2 inline-flex w-full items-center justify-center rounded-lg bg-primary px-2.5 py-1.5 text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
          >
            Compare plans
          </Link>
        </div>
      )}
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
