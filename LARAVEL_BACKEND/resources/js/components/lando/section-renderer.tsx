import type { CmsPageData } from "./types"
import {
  LandoHeroSection,
  LandoPageHero,
  LandoIntroCard,
  LandoFeatureBlock,
  LandoHowToJoin,
  LandoCtaSection,
  LandoCapabilities,
  LandoGrowthEngine,
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
            imageAlt={str(content.imageAlt)}
          />
        )
      }
      if (pageSlug === "pricing") {
        return <LandoPageHero title={str(content.title)} description={str(content.description)} />
      }
      return (
        <LandoHeroSection
          kicker={str(content.kicker)}
          title={str(content.title)}
          description={str(content.description)}
          primaryCtaText={str(content.primaryCtaText)}
          primaryCtaHref={str(content.primaryCtaHref)}
          secondaryCtaText={str(content.secondaryCtaText)}
          secondaryCtaHref={str(content.secondaryCtaHref)}
          imageUrl={str(content.imageUrl)}
          imageAlt={str(content.imageAlt)}
          showFlowSimulation={content.showFlowSimulation === true}
        />
      )

    case "capabilities":
      return (
        <LandoCapabilities
          title={str(content.title)}
          description={str(content.description)}
          items={arr(content.items)}
        />
      )

    case "trusted_companies": {
      const companies = arr<{ name: string; logoUrl?: string }>(content.companies)
      const fromApi = pageData.trustedCompanies
      const merged =
        companies.length > 0
          ? companies
          : (fromApi ?? []).map((c) =>
              typeof c === "string" ? { name: c, logoUrl: "" } : c
            )
      return <LandoTrustedCompanies title={str(content.title)} companies={merged} />
    }

    case "intro_card":
      return (
        <LandoIntroCard
          title={str(content.title)}
          description={str(content.description)}
          ctaText={str(content.ctaText)}
          ctaHref={str(content.ctaHref)}
          imageUrl={str(content.imageUrl)}
          imageAlt={str(content.imageAlt)}
        />
      )

    case "feature_1":
    case "feature_2":
    case "feature_3":
    case "feature_4":
      return (
        <LandoFeatureBlock
          label={str(content.label)}
          title={str(content.title)}
          description={str(content.description)}
          ctaText={str(content.ctaText)}
          ctaHref={str(content.ctaHref)}
          imageUrl={str(content.imageUrl)}
          imageAlt={str(content.imageAlt)}
          imagePosition={content.imagePosition === "right" ? "right" : "left"}
        />
      )

    case "growth_engine":
      return (
        <LandoGrowthEngine
          label={str(content.label)}
          title={str(content.title)}
          description={str(content.description)}
          points={arr<string>(content.points)}
          ctaText={str(content.ctaText)}
          ctaHref={str(content.ctaHref)}
          imageUrl={str(content.imageUrl)}
          imageAlt={str(content.imageAlt)}
        />
      )

    case "how_to_join":
      return (
        <LandoHowToJoin
          title={str(content.title)}
          description={str(content.description)}
          ctaText={str(content.ctaText)}
          ctaHref={str(content.ctaHref)}
          imageUrl={str(content.imageUrl)}
          imageAlt={str(content.imageAlt)}
          steps={arr(content.steps)}
        />
      )

    case "testimonials":
      return (
        <LandoTestimonials
          title={str(content.title)}
          description={str(content.description)}
          testimonials={pageData.testimonials ?? []}
        />
      )

    case "pricing_plans":
      return <LandoPricingPlans popularBadge={str(content.popularBadge, "Most Popular")} />

    case "compare_features":
      return (
        <LandoCompareFeatures
          title={str(content.title, "Compare Features")}
          columns={arr(content.columns)}
        />
      )

    case "faq":
      return (
        <LandoFaqSection title={str(content.title)} faqs={pageData.faqs ?? []} />
      )

    case "mission":
      return <LandoMission title={str(content.title)} description={str(content.description)} />

    case "efficiency":
      return (
        <LandoEfficiency
          title={str(content.title)}
          description={str(content.description) || undefined}
          ctaText={str(content.ctaText) || undefined}
          ctaHref={str(content.ctaHref) || undefined}
        />
      )

    case "team":
      return (
        <LandoTeam
          title={str(content.title)}
          description={str(content.description)}
          members={arr(content.members)}
        />
      )

    case "cta":
      return (
        <LandoCtaSection
          title={str(content.title)}
          description={str(content.description)}
          ctaText={str(content.ctaText)}
          ctaHref={str(content.ctaHref)}
          imageUrl={str(content.imageUrl)}
          imageAlt={str(content.imageAlt)}
          showImage={pageSlug === "home"}
        />
      )

    default:
      return null
  }
}
