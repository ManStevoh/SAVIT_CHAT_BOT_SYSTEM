"use client"

import { usePathname } from "next/navigation"
import { LandoNavbar } from "./navbar"
import { LandoFooter, mobileAppFromFooterContent } from "./footer"
import { LandoSectionRenderer } from "./section-renderer"
import { useCmsPage, useCmsGlobal } from "@/lib/api-hooks"
import type { CmsLink, CmsSection } from "./types"
import { SeoHead, buildSeoFromCmsPage, type SeoPayload } from "@/components/seo/SeoHead"

interface LandoCmsPageProps {
  slug: string
  fallbackTitle?: string
  initialSeo?: SeoPayload | null
}

function getSectionContent(sections: CmsSection[], key: string) {
  return sections.find((s) => s.key === key)?.content ?? {}
}

export function LandoCmsPage({ slug, fallbackTitle, initialSeo }: LandoCmsPageProps) {
  const pathname = usePathname()
  const { data: pageData, isLoading } = useCmsPage(slug)
  const { data: globalData } = useCmsGlobal()

  const globalSections = globalData?.sections ?? []
  const navbarContent = getSectionContent(globalSections, "navbar")
  const footerContent = getSectionContent(globalSections, "footer")

  const navLinks = (navbarContent.links as CmsLink[] | undefined) ?? []
  const seo = buildSeoFromCmsPage(pageData?.page, initialSeo, fallbackTitle)

  const enabledSections =
    pageData?.sections
      .filter((s) => s.isEnabled)
      .sort((a, b) => a.sortOrder - b.sortOrder) ?? []

  return (
    <>
      <SeoHead seo={seo} fallbackTitle={fallbackTitle} />

      <div className="lando-page min-h-screen bg-[#f3f4f6]">
        <LandoNavbar
          links={navLinks}
          loginLabel={String(navbarContent.loginLabel ?? "Log in")}
          loginHref={String(navbarContent.loginHref ?? "/login")}
          signupLabel={String(navbarContent.signupLabel ?? "Sign up")}
          signupHref={String(navbarContent.signupHref ?? "/register")}
          activePath={pathname}
        />

        {isLoading && (
          <div className="flex min-h-[50vh] items-center justify-center pt-28">
            <span className="h-8 w-8 animate-spin rounded-full border-2 border-[#2563eb] border-t-transparent" />
          </div>
        )}

        {!isLoading &&
          pageData &&
          enabledSections.map((section) => (
            <LandoSectionRenderer
              key={section.key}
              pageSlug={slug}
              sectionKey={section.key}
              content={section.content}
              pageData={pageData}
            />
          ))}

        <LandoFooter
          copyright={String(footerContent.copyright ?? "")}
          navLinks={(footerContent.navLinks as CmsLink[]) ?? []}
          socialLinks={(footerContent.socialLinks as CmsLink[]) ?? []}
          legalLinks={(footerContent.legalLinks as CmsLink[]) ?? []}
          mobileApp={mobileAppFromFooterContent(footerContent)}
        />
      </div>
    </>
  )
}
