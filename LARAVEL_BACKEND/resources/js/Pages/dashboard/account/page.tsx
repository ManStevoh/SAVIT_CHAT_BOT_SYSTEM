import { Head } from "@inertiajs/react"
import { AccountProfilePanel } from "@/components/account/account-profile-panel"

export default function DashboardAccountPage() {
  return (
    <>
      <Head title="My account" />
      <AccountProfilePanel title="My account" />
    </>
  )
}
