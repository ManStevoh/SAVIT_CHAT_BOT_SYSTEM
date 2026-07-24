"use client"

import { usePathname } from "next/navigation"
import { LandoNavbar } from "@/components/lando/navbar"
import { LandoFooter, mobileAppFromFooterContent } from "@/components/lando/footer"
import { useCmsGlobal } from "@/lib/api-hooks"
import { BRAND } from "@/lib/branding"
import type { CmsLink, CmsSection } from "@/components/lando/types"

function getSectionContent(sections: CmsSection[], key: string) {
  return sections.find((s) => s.key === key)?.content ?? {}
}

export function AuthBranding({
  children,
}: {
  children: React.ReactNode
}) {
  const pathname = usePathname()
  const { data: globalData } = useCmsGlobal()
  const globalSections = globalData?.sections ?? []
  const navbarContent = getSectionContent(globalSections, "navbar")
  const footerContent = getSectionContent(globalSections, "footer")
  const authContent = getSectionContent(globalSections, "auth_shell")

  const imageUrl = String(authContent.imageUrl ?? "/images/lando/lando-hero.png")
  const imageAlt = String(authContent.imageAlt ?? "Platform illustration")

  return (
    <div className="lando-page min-h-screen bg-[#f3f4f6]">
      <LandoNavbar
        links={(navbarContent.links as CmsLink[]) ?? []}
        loginLabel={String(navbarContent.loginLabel ?? "Log in")}
        loginHref={String(navbarContent.loginHref ?? "/login")}
        signupLabel={String(navbarContent.signupLabel ?? "Sign up")}
        signupHref={String(navbarContent.signupHref ?? "/register")}
        activePath={pathname}
      />

      <div className="mx-auto grid min-h-[calc(100vh-4rem)] max-w-6xl items-center gap-8 px-4 py-12 sm:px-6 lg:grid-cols-2 lg:gap-12 lg:px-8 lg:py-16">
        <div className="flex justify-center lg:justify-center">
          <img
            src={imageUrl}
            alt={imageAlt}
            className="max-h-48 w-full max-w-xs object-contain sm:max-h-64 lg:max-h-[480px] lg:max-w-md"
          />
        </div>

        <div className="w-full max-w-md justify-self-center lg:max-w-lg lg:justify-self-end">
          {children}
        </div>
      </div>

      <LandoFooter
        copyright={String(footerContent.copyright ?? BRAND.copyright())}
        navLinks={(footerContent.navLinks as CmsLink[]) ?? []}
        socialLinks={(footerContent.socialLinks as CmsLink[]) ?? []}
        legalLinks={(footerContent.legalLinks as CmsLink[]) ?? []}
        mobileApp={mobileAppFromFooterContent(footerContent)}
      />
    </div>
  )
}
