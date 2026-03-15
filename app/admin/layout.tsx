import { AdminSidebar } from "@/components/admin/sidebar"
import { DashboardNavbar } from "@/components/dashboard/navbar"

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <div className="min-h-screen bg-background">
      <AdminSidebar />
      <div className="pl-64">
        <DashboardNavbar />
        <main className="p-6">{children}</main>
      </div>
    </div>
  )
}
