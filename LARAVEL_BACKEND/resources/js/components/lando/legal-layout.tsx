import { LandoCmsPage } from "@/components/lando/cms-page"
import { useCmsGlobal } from "@/lib/api-hooks"
import { LandoNavbar } from "@/components/lando/navbar"
import { LandoFooter } from "@/components/lando/footer"
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
