import { AppBrandingProvider } from '@/components/providers/AppBrandingProvider'
import { CookieConsentBanner } from '@/components/compliance/CookieConsentBanner'
import { ThemeProvider } from '@/components/theme-provider'
import { Toaster as SonnerToaster } from '@/components/ui/sonner'
import { Toaster } from '@/components/ui/toaster'
import '../css/globals.css'
import { createInertiaApp } from '@inertiajs/react'
import { createRoot } from 'react-dom/client'
import type { ReactNode } from 'react'
import type React from 'react'
import AdminLayout from './layouts/AdminLayout'
import AuthLayout from './layouts/AuthLayout'
import DashboardLayout from './layouts/DashboardLayout'

const appName = import.meta.env.VITE_APP_NAME || 'RelayIQ'

function resolveLayout(name: string) {
  if (name.startsWith('Auth/')) return AuthLayout
  if (name.startsWith('dashboard/')) return DashboardLayout
  if (name.startsWith('admin/')) return AdminLayout
  return undefined
}

createInertiaApp({
  title: (title) => {
    if (!title) return appName
    const normalized = title.trim()
    if (
      normalized === appName ||
      normalized.includes(` — ${appName}`) ||
      normalized.includes(` - ${appName}`) ||
      normalized.endsWith(appName)
    ) {
      return normalized
    }
    return `${normalized} - ${appName}`
  },
  resolve: async (name) => {
    const pages = import.meta.glob('./Pages/**/*.tsx')
    const importPage = pages[`./Pages/${name}.tsx`]
    if (!importPage) {
      throw new Error(`Page not found: ${name}`)
    }
    const module = (await importPage()) as { default: React.ComponentType & { layout?: (page: ReactNode) => ReactNode } }
    const page = module.default
    const Layout = resolveLayout(name)
    if (Layout) {
      page.layout = (pageContent: ReactNode) => <Layout>{pageContent}</Layout>
    }
    return page
  },
  setup({ el, App, props }) {
    createRoot(el).render(
      <ThemeProvider attribute="class" defaultTheme="light" enableSystem={false} storageKey="essem-theme">
        <AppBrandingProvider>
          <App {...props} />
          <CookieConsentBanner />
          <Toaster />
          <SonnerToaster position="top-right" richColors closeButton />
        </AppBrandingProvider>
      </ThemeProvider>,
    )
  },
  progress: {
    color: '#4B5563',
  },
})
