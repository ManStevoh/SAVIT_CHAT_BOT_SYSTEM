import { LandoCmsPage } from "@/components/lando/cms-page"
import type { CmsPageData } from "@/components/lando/types"
import type { SeoPayload } from "@/components/seo/SeoHead"

/**
 * Generic CMS-driven marketing page used for SEO landings (features, keyword pages).
 */
export default function MarketingCmsPage({
  slug,
  seo,
  cms,
  cmsGlobal,
  fallbackTitle,
}: {
  slug: string
  seo?: SeoPayload | null
  cms?: CmsPageData | null
  cmsGlobal?: CmsPageData | null
  fallbackTitle?: string
}) {
  return (
    <LandoCmsPage
      slug={slug}
      fallbackTitle={fallbackTitle || `${slug} — RelayIQ`}
      initialSeo={seo}
      initialCms={cms}
      initialCmsGlobal={cmsGlobal}
    />
  )
}
