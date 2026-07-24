import Link from "next/link"
import { LandingNavbar } from "@/components/landing/navbar"
import { Footer } from "@/components/landing/footer"

interface LegalLayoutProps {
  title: string
  children: React.ReactNode
}

export function LegalLayout({ title, children }: LegalLayoutProps) {
  return (
    <div className="min-h-screen bg-background">
      <LandingNavbar />
      <main className="mx-auto max-w-3xl px-4 pb-20 pt-28 sm:px-6 lg:px-8 lg:pt-32">
        <p className="landing-kicker mb-2">Legal</p>
        <h1 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">{title}</h1>
        <div className="prose prose-sm mt-10 max-w-none text-muted-foreground prose-headings:font-semibold prose-headings:text-foreground prose-a:text-primary">
          {children}
        </div>
        <p className="mt-12 text-sm text-muted-foreground">
          Questions?{" "}
          <a href="mailto:support@essemdigital.com" className="text-primary hover:underline">
            support@essemdigital.com
          </a>
          {" · "}
          <Link href="/" className="text-primary hover:underline">
            Back to home
          </Link>
        </p>
      </main>
      <Footer />
    </div>
  )
}
