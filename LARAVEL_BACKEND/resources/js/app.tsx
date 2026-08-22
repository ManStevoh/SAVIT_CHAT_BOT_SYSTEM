import { AppErrorBoundary } from '@/components/AppErrorBoundary'
import { AppBrandingProvider } from '@/components/providers/AppBrandingProvider'
import { CookieConsentBanner } from '@/components/compliance/CookieConsentBanner'
import { ThemeProvider } from '@/components/theme-provider'
import { Toaster as SonnerToaster } from '@/components/ui/sonner'
import { Toaster } from '@/components/ui/toaster'
import { SWRConfig } from 'swr'
import '../css/globals.css'
import { createInertiaApp, router } from '@inertiajs/react'
import { createRoot } from 'react-dom/client'
import type { ReactNode } from 'react'
import type React from 'react'
import AdminLayout from './layouts/AdminLayout'
import AuthLayout from './layouts/AuthLayout'
import DashboardLayout from './layouts/DashboardLayout'

const appName = import.meta.env.VITE_APP_NAME || 'RelayIQ'

function resolveLayout(name: string) {
  const lower = name.toLowerCase()
  if (lower.startsWith('auth/')) return AuthLayout
  if (lower.startsWith('dashboard/')) return DashboardLayout
  if (lower.startsWith('admin/')) return AdminLayout
  return undefined
}

function shouldSkipAppTitleSuffix(title: string): boolean {
  try {
    const seo = ((router as unknown as { page?: { props?: { seo?: { skipAppTitleSuffix?: boolean } } } }).page?.props)?.seo
    if (seo?.skipAppTitleSuffix) return true
  } catch {
    // ignore
  }

  try {
    const path = typeof window !== 'undefined' ? window.location.pathname : ''
    if (/^\/(s|b)\//.test(path) || path.startsWith('/pay/') || path.startsWith('/invoice/')) {
      return true
    }
  } catch {
    // ignore
  }

  return false
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

    if (shouldSkipAppTitleSuffix(normalized)) {
      return normalized
    }

    return `${normalized} - ${appName}`
  },
  resolve: async (name) => {
    const pages = import.meta.glob('./Pages/**/*.tsx')
    const importPage = pages[`./Pages/${name}.tsx`]
    if (!importPage) {
      // After a deploy/rebuild, an open tab can still run an old bundle that
      // doesn't know about newly added pages (e.g. Solutions). Force a full
      // load so the browser picks up the current Vite manifest.
      if (typeof window !== 'undefined') {
        window.location.reload()
      }
      return new Promise(() => {}) as Promise<
        React.ComponentType & { layout?: (page: ReactNode) => ReactNode }
      >
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
      <AppErrorBoundary>
        <SWRConfig
          value={{
            revalidateOnFocus: false,
            revalidateOnReconnect: false,
            shouldRetryOnError: false,
            dedupingInterval: 8000,
          }}
        >
          <ThemeProvider attribute="class" defaultTheme="light" enableSystem={false} storageKey="essem-theme">
            <AppBrandingProvider>
              <App {...props} />
              <CookieConsentBanner />
              <Toaster />
              <SonnerToaster position="top-right" richColors closeButton />
            </AppBrandingProvider>
          </ThemeProvider>
        </SWRConfig>
      </AppErrorBoundary>,
    )
  },
  progress: {
    color: '#4B5563',
  },
})
