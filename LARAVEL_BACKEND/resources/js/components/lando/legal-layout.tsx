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
