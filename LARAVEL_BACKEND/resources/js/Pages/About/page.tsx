import { LandoCmsPage } from "@/components/lando/cms-page"
import type { SeoPayload } from "@/components/seo/SeoHead"

export default function AboutPage({ seo }: { seo?: SeoPayload | null }) {
  return <LandoCmsPage slug="about" fallbackTitle="About us — RelayIQ" initialSeo={seo} />
}
