import { ProtectedRoute } from '@/components/auth/ProtectedRoute'
import { DashboardNavbar } from '@/components/dashboard/navbar'
import { DashboardSidebar } from '@/components/dashboard/sidebar'
import { Loader2 } from 'lucide-react'
import { Suspense, type ReactNode } from 'react'

function DashboardFallback() {
  return (
    <div className="flex min-h-[40vh] items-center justify-center">
      <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
    </div>
  )
}

export default function DashboardLayout({ children }: { children: ReactNode }) {
  return (
    <ProtectedRoute>
      <div className="min-h-screen w-full bg-muted/20 text-foreground">
        <DashboardSidebar />
        <div className="min-h-screen transition-all duration-300 md:pl-60">
          <Suspense fallback={<div className="h-14 border-b border-border/50" />}>
            <DashboardNavbar />
          </Suspense>
          <main className="min-h-[calc(100vh-3.5rem)] p-6 lg:p-8">
            <Suspense fallback={<DashboardFallback />}>{children}</Suspense>
          </main>
        </div>
      </div>
    </ProtectedRoute>
  )
}
