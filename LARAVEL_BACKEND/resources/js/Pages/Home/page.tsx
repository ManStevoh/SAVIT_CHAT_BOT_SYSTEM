import { LandoCmsPage } from "@/components/lando/cms-page"
import type { CmsPageData } from "@/components/lando/types"
import type { SeoPayload } from "@/components/seo/SeoHead"

export default function HomePage({
  seo,
  cms,
  cmsGlobal,
}: {
  seo?: SeoPayload | null
  cms?: CmsPageData | null
  cmsGlobal?: CmsPageData | null
}) {
  return (
    <LandoCmsPage
      slug="home"
      fallbackTitle="RelayIQ"
      initialSeo={seo}
      initialCms={cms}
      initialCmsGlobal={cmsGlobal}
    />
  )
}
