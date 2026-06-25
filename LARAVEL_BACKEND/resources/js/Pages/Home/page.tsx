import { Head } from "@inertiajs/react"
import { LandingNavbar } from "@/components/landing/navbar"
import { HeroSection } from "@/components/landing/hero-section"
import { TrustedCompanies } from "@/components/landing/trusted-companies"
import { FeaturesSection } from "@/components/landing/features-section"
import { UseCasesSection } from "@/components/landing/use-cases-section"
import { HowItWorks } from "@/components/landing/how-it-works"
import { ProductScreenshots } from "@/components/landing/product-screenshots"
import { GrowthEngineSection } from "@/components/landing/growth-engine-section"
import { IntegrationsSection } from "@/components/landing/integrations-section"
import { PricingSection } from "@/components/landing/pricing-section"
import { TestimonialsSection } from "@/components/landing/testimonials-section"
import { TrustBadges } from "@/components/landing/trust-badges"
import { FAQSection } from "@/components/landing/faq-section"
import { CtaSection } from "@/components/landing/cta-section"
import { Footer } from "@/components/landing/footer"

const META = {
  title: "Savit Chat — AI WhatsApp Sales & Order Automation",
  description:
    "Turn WhatsApp into your best sales channel. AI replies, order flows, M-Pesa & Stripe payments, multi-agent inbox, and Growth Engine attribution — all in one platform.",
}

export default function LandingPage() {
  return (
    <>
      <Head>
        <title>{META.title}</title>
        <meta name="description" content={META.description} />
        <meta property="og:title" content={META.title} />
        <meta property="og:description" content={META.description} />
        <meta property="og:type" content="website" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content={META.title} />
        <meta name="twitter:description" content={META.description} />
      </Head>

      <main className="landing-page min-h-screen bg-background">
        <LandingNavbar />
        <HeroSection />
        <TrustedCompanies />
        <FeaturesSection />
        <UseCasesSection />
        <HowItWorks />
        <ProductScreenshots />
        <GrowthEngineSection />
        <IntegrationsSection />
        <PricingSection />
        <TestimonialsSection />
        <TrustBadges />
        <FAQSection />
        <CtaSection />
        <Footer />
      </main>
    </>
  )
}
