import { LandoCmsPage } from "@/components/lando/cms-page"
import { useCmsGlobal } from "@/lib/api-hooks"
import { LandoNavbar } from "@/components/lando/navbar"
import { LandoFooter, mobileAppFromFooterContent } from "@/components/lando/footer"
import type { CmsLink, CmsSection } from "@/components/lando/types"

function getSectionContent(sections: CmsSection[], key: string) {
  return sections.find((s) => s.key === key)?.content ?? {}
}

interface LegalLayoutProps {
  title: string
  activePath?: string
  children: React.ReactNode
}

export function LegalLayout({ title, children, activePath = "/" }: LegalLayoutProps) {
  const { data: globalData } = useCmsGlobal()
  const globalSections = globalData?.sections ?? []
  const navbarContent = getSectionContent(globalSections, "navbar")
  const footerContent = getSectionContent(globalSections, "footer")

  return (
    <div className="lando-page min-h-screen bg-[#f3f4f6]">
      <LandoNavbar
        links={(navbarContent.links as CmsLink[]) ?? []}
        loginLabel={String(navbarContent.loginLabel ?? "Log in")}
        loginHref={String(navbarContent.loginHref ?? "/login")}
        signupLabel={String(navbarContent.signupLabel ?? "Sign up")}
        signupHref={String(navbarContent.signupHref ?? "/register")}
        activePath={activePath}
      />
      <main className="mx-auto max-w-3xl px-4 pb-20 pt-28 sm:px-6 lg:px-8 lg:pt-32">
        <h1 className="text-3xl font-bold tracking-tight text-black sm:text-4xl">{title}</h1>
        <div className="prose prose-sm mt-10 max-w-none text-gray-600 prose-headings:font-semibold prose-headings:text-black prose-a:text-[#2563eb]">
          {children}
        </div>
      </main>
      <LandoFooter
        copyright={String(footerContent.copyright ?? "")}
        navLinks={(footerContent.navLinks as CmsLink[]) ?? []}
        socialLinks={(footerContent.socialLinks as CmsLink[]) ?? []}
        legalLinks={(footerContent.legalLinks as CmsLink[]) ?? []}
        mobileApp={mobileAppFromFooterContent(footerContent)}
      />
    </div>
  )
}
