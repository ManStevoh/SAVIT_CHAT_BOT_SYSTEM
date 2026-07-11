"use client"

import { Head } from "@inertiajs/react"
import { usePathname } from "next/navigation"
import { LandoNavbar } from "./navbar"
import { LandoFooter } from "./footer"
import { LandoSectionRenderer } from "./section-renderer"
import { useCmsPage, useCmsGlobal } from "@/lib/api-hooks"
import type { CmsLink, CmsSection } from "./types"

interface LandoCmsPageProps {
  slug: string
  fallbackTitle?: string
}

function getSectionContent(sections: CmsSection[], key: string) {
  return sections.find((s) => s.key === key)?.content ?? {}
}

export function LandoCmsPage({ slug, fallbackTitle }: LandoCmsPageProps) {
  const pathname = usePathname()
  const { data: pageData, isLoading } = useCmsPage(slug)
  const { data: globalData } = useCmsGlobal()

  const globalSections = globalData?.sections ?? []
  const navbarContent = getSectionContent(globalSections, "navbar")
  const footerContent = getSectionContent(globalSections, "footer")

  const navLinks = (navbarContent.links as CmsLink[] | undefined) ?? []
  const metaTitle = pageData?.page.metaTitle ?? pageData?.page.title ?? fallbackTitle
  const metaDescription = pageData?.page.metaDescription ?? ""

  const enabledSections =
    pageData?.sections
      .filter((s) => s.isEnabled)
      .sort((a, b) => a.sortOrder - b.sortOrder) ?? []

  return (
    <>
      <Head>
        <title>{metaTitle}</title>
        {metaDescription && <meta name="description" content={metaDescription} />}
      </Head>

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

