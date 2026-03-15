import { DashboardSidebar } from "@/components/dashboard/sidebar"
import { DashboardNavbar } from "@/components/dashboard/navbar"
import { ProtectedRoute } from "@/components/auth/ProtectedRoute"

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <ProtectedRoute>
      <div className="min-h-screen bg-background">
        <DashboardSidebar />
        <div className="pl-64 transition-all duration-300">
          <DashboardNavbar />
          <main className="p-6">{children}</main>
        </div>
      </div>
    </ProtectedRoute>
  )
}
