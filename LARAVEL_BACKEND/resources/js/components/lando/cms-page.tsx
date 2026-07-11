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
