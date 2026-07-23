import { LandoCmsPage } from "@/components/lando/cms-page"
import type { SeoPayload } from "@/components/seo/SeoHead"

export default function ContactPage({ seo }: { seo?: SeoPayload | null }) {
  return <LandoCmsPage slug="contact" fallbackTitle="Contact — RelayIQ" initialSeo={seo} />
}
