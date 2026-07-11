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
