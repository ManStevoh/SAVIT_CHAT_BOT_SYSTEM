import { AuthBranding } from "@/components/branding/AuthBranding"

export default function AuthLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return <AuthBranding>{children}</AuthBranding>
}
