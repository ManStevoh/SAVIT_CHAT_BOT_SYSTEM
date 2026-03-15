import { AdminSidebar } from "@/components/admin/sidebar"
import { DashboardNavbar } from "@/components/dashboard/navbar"
import { ProtectedRoute } from "@/components/auth/ProtectedRoute"

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <ProtectedRoute requireAdmin>
      <div className="min-h-screen bg-background">
        <AdminSidebar />
        <div className="pl-64">
          <DashboardNavbar />
          <main className="p-6">{children}</main>
        </div>
      </div>
    </ProtectedRoute>
  )
}
