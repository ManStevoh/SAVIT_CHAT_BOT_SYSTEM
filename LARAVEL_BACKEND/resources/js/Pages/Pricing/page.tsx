import { LandoCmsPage } from "@/components/lando/cms-page"
import type { SeoPayload } from "@/components/seo/SeoHead"

export default function PricingPage({ seo }: { seo?: SeoPayload | null }) {
  return <LandoCmsPage slug="pricing" fallbackTitle="Pricing — RelayIQ" initialSeo={seo} />
}
