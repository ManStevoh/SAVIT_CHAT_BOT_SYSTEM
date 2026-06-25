"use client"

import { useLanding } from "@/lib/api-hooks"
import { CompanyLogo, parseCompanyBrand } from "@/components/landing/company-logo"

const INTEGRATION_MARKS = ["WhatsApp Business API", "M-Pesa", "Stripe", "OpenAI", "Anthropic", "Gemini"]

export function TrustedCompanies() {
  const { data } = useLanding()
  const hasRealLogos = Boolean(data?.trustedCompanies?.some((c) => {
    const brand = parseCompanyBrand(c)
    return Boolean(brand.logoUrl)
  }))

  if (hasRealLogos && data?.trustedCompanies?.length) {
    const brands = data.trustedCompanies.map(parseCompanyBrand)

    return (
      <section className="landing-divider bg-muted/20 py-8">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <p className="mb-5 text-center text-sm text-muted-foreground">
            Used by teams like
          </p>
          <div className="flex flex-wrap items-center justify-center gap-4">
            {brands.map((brand) => (
              <CompanyLogo key={brand.name} name={brand.name} logoUrl={brand.logoUrl} />
            ))}
          </div>
        </div>
      </section>
    )
  }

  return (
    <section className="landing-divider bg-muted/20 py-8">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <p className="mb-5 text-center text-sm text-muted-foreground">
          Built on the tools your business already uses
        </p>
        <div className="flex flex-wrap items-center justify-center gap-3">
          {INTEGRATION_MARKS.map((name) => (
            <span
              key={name}
              className="rounded-md border border-border/70 bg-card px-3 py-1.5 text-sm font-medium text-foreground/80"
            >
              {name}
            </span>
          ))}
        </div>
      </div>
    </section>
  )
}
