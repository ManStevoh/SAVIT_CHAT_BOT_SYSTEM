import { AdminSidebar } from '@/components/admin/sidebar'
import { ProtectedRoute } from '@/components/auth/ProtectedRoute'
import { DashboardNavbar } from '@/components/dashboard/navbar'
import type { ReactNode } from 'react'

export default function AdminLayout({ children }: { children: ReactNode }) {
  return (
    <ProtectedRoute requireAdmin>
      <div className="min-h-screen bg-background">
        <AdminSidebar />
        <div className="pl-64 min-w-0">
          <DashboardNavbar />
          <main className="min-w-0 max-w-full overflow-x-hidden p-6">{children}</main>
        </div>
      </div>
    </ProtectedRoute>
  )
}
