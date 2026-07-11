import type { CmsPageData } from "./types"
import {
  LandoHeroSection,
  LandoPageHero,
  LandoIntroCard,
  LandoFeatureBlock,
  LandoHowToJoin,
  LandoCtaSection,
} from "./sections"
import {
  LandoTrustedCompanies,
  LandoTestimonials,
  LandoPricingPlans,
  LandoCompareFeatures,
  LandoFaqSection,
  LandoAboutHero,
  LandoMission,
  LandoEfficiency,
  LandoTeam,
  LandoContactSection,
} from "./content-sections"

type Content = Record<string, unknown>

function str(v: unknown, fallback = ""): string {
  return typeof v === "string" ? v : fallback
}

function arr<T>(v: unknown): T[] {
  return Array.isArray(v) ? (v as T[]) : []
}

interface SectionRendererProps {
  pageSlug: string
  sectionKey: string
  content: Content
  pageData: CmsPageData
}

export function LandoSectionRenderer({ pageSlug, sectionKey, content, pageData }: SectionRendererProps) {
  switch (sectionKey) {
    case "hero":
      if (pageSlug === "contact") {
        return (
          <LandoContactSection
            title={str(content.title)}
            description={str(content.description)}
            imageUrl={str(content.imageUrl)}
            imageAlt={str(content.imageAlt)}
            nameLabel={str(content.nameLabel, "Name")}
            namePlaceholder={str(content.namePlaceholder)}
            emailLabel={str(content.emailLabel, "Email")}
            emailPlaceholder={str(content.emailPlaceholder)}
            messageLabel={str(content.messageLabel, "Message")}
            messagePlaceholder={str(content.messagePlaceholder)}
            submitText={str(content.submitText, "Send message")}
            successMessage={str(content.successMessage)}
          />
        )
      }
      if (pageSlug === "about") {
        return (
          <LandoAboutHero
            title={str(content.title)}
            description={str(content.description)}
            imageUrl={str(content.imageUrl)}
