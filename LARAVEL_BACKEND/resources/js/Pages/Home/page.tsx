import { LandoCmsPage } from "@/components/lando/cms-page"
import type { SeoPayload } from "@/components/seo/SeoHead"

export default function HomePage({ seo }: { seo?: SeoPayload | null }) {
  return <LandoCmsPage slug="home" fallbackTitle="RelayIQ" initialSeo={seo} />
}
