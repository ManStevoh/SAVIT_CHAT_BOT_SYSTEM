"use client"

import { useState, useEffect } from "react"
import Link from "next/link"
import { usePathname, useRouter, useSearchParams } from "next/navigation"
import { logout } from "@/lib/api-actions"
import { clearAuthCookie } from "@/lib/auth-cookie"
import { useNotifications } from "@/lib/api-hooks"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { Drawer, DrawerContent, DrawerTrigger, DrawerClose } from "@/components/ui/drawer"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Search, Bell, Moon, Sun, User, Settings, LogOut, Menu } from "lucide-react"
import { useTheme } from "next-themes"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"
import { DashboardNavLinks } from "@/components/dashboard/sidebar"

type StoredUser = { name?: string; email?: string }

function getStoredUser(): StoredUser | null {
  if (typeof window === "undefined") return null
  const raw = localStorage.getItem("auth_user") ?? sessionStorage.getItem("auth_user")
  if (!raw) return null
  try {
    return JSON.parse(raw) as StoredUser
  } catch {
    return null
  }
}

function getInitials(user: StoredUser | null): string {
  if (!user?.name) return "?"
  const parts = user.name.trim().split(/\s+/)
  if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase().slice(0, 2)
  return user.name.slice(0, 2).toUpperCase()
}

export function DashboardNavbar() {
  const { resolvedTheme, setTheme } = useTheme()
  const [loggingOut, setLoggingOut] = useState(false)
  const [user, setUser] = useState<StoredUser | null>(null)
  const [mobileNavOpen, setMobileNavOpen] = useState(false)
  const [topSearch, setTopSearch] = useState("")
  const pathname = usePathname()
  const router = useRouter()
  const searchParams = useSearchParams()
  const isAdmin = pathname?.startsWith("/admin") ?? false
  const profileHref = isAdmin ? "/admin/settings" : "/dashboard/settings"
  const settingsHref = isAdmin ? "/admin/settings" : "/dashboard/settings"

  useEffect(() => {
    setUser(getStoredUser())
  }, [])

  useEffect(() => {
    const initial = searchParams?.get("search") ?? ""
    setTopSearch(initial)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pathname])

  const { data: notificationsData } = useNotifications()
  const notifications = notificationsData?.items ?? []
  const unreadCount = notificationsData?.unreadCount ?? 0

  return (
    <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-border bg-background/95 backdrop-blur px-6">
      <div className="flex items-center gap-4 flex-1">
        {!isAdmin && (
          <Drawer open={mobileNavOpen} onOpenChange={setMobileNavOpen} direction="left">
            <DrawerTrigger asChild>
              <Button
                variant="ghost"
                size="icon"
                className="md:hidden text-muted-foreground hover:text-foreground"
                aria-label="Open navigation"
              >
                <Menu className="h-5 w-5" />
              </Button>
            </DrawerTrigger>
            <DrawerContent className="p-0">
              <div className="flex h-16 items-center border-b border-border px-4">
                <AppLogoAndName variant="sidebar" />
              </div>
              <DashboardNavLinks onNavigate={() => setMobileNavOpen(false)} />
              <div className="p-2">
                <DrawerClose asChild>
                  <Button variant="ghost" className="w-full justify-start text-muted-foreground">
                    Close
                  </Button>
                </DrawerClose>
              </div>
            </DrawerContent>
          </Drawer>
        )}

        <form
          className="relative w-full max-w-md"
          onSubmit={(e) => {
            e.preventDefault()
            const q = topSearch.trim()
            const target = isAdmin
              ? "/admin/companies"
              : pathname?.startsWith("/dashboard/orders")
                ? "/dashboard/orders"
                : pathname?.startsWith("/dashboard/customers")
                  ? "/dashboard/customers"
                  : "/dashboard/chats"

            router.push(q ? `${target}?search=${encodeURIComponent(q)}` : target)
          }}
        >
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            placeholder={isAdmin ? "Search companies..." : "Search conversations, orders..."}
            className="pl-10"
            value={topSearch}
            onChange={(e) => setTopSearch(e.target.value)}
          />
        </form>
      </div>

      <div className="flex items-center gap-2">
        <Button
          variant="ghost"
          size="icon"
          onClick={() => {
            const current = resolvedTheme ?? "light"
            setTheme(current === "dark" ? "light" : "dark")
          }}
          className="text-muted-foreground hover:text-foreground"
          aria-label="Toggle color theme"
        >
          {(resolvedTheme ?? "light") === "dark" ? (
            <Sun className="h-5 w-5" />
          ) : (
            <Moon className="h-5 w-5" />
          )}
        </Button>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" className="relative text-muted-foreground hover:text-foreground">
              <Bell className="h-5 w-5" />
              {unreadCount > 0 && (
                <span className="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground">
                  {unreadCount > 99 ? "99+" : unreadCount}
                </span>
              )}
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-80">
            <DropdownMenuLabel>Notifications</DropdownMenuLabel>
            <DropdownMenuSeparator />
            {notifications.length === 0 ? (
              <div className="py-6 text-center text-sm text-muted-foreground">
                No new notifications
              </div>
            ) : (
              notifications.map((n) => (
                <DropdownMenuItem key={n.id} className="flex flex-col items-start gap-1 py-3">
                  <span className="font-medium text-foreground">{n.title}</span>
                  {n.body != null && n.body !== "" && (
                    <span className="text-xs text-muted-foreground">{n.body}</span>
                  )}
                </DropdownMenuItem>
              ))
            )}
          </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" className="flex items-center gap-2">
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-medium text-primary-foreground">
                {getInitials(user)}
              </div>
              <span className="hidden text-sm font-medium text-foreground sm:inline">
                {user?.name ?? user?.email ?? "Account"}
              </span>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-56">
            <DropdownMenuLabel>My Account</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
              <Link href={profileHref} className="flex items-center gap-2">
                <User className="h-4 w-4" />
                Profile
              </Link>
            </DropdownMenuItem>
            <DropdownMenuItem asChild>
              <Link href={settingsHref} className="flex items-center gap-2">
                <Settings className="h-4 w-4" />
                Settings
              </Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              className="flex items-center gap-2 text-destructive"
              disabled={loggingOut}
              onClick={async () => {
                setLoggingOut(true)
                try {
                  await logout()
                } finally {
                  clearAuthCookie()
                  localStorage.removeItem('auth_token')
                  localStorage.removeItem('auth_user')
                  sessionStorage.removeItem('auth_token')
                  sessionStorage.removeItem('auth_user')
                  router.push('/login')
                }
              }}
            >
              <LogOut className="h-4 w-4" />
              {loggingOut ? 'Logging out…' : 'Log out'}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  )
}
