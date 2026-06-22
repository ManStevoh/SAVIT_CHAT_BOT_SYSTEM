"use client"

import { useLanding } from "@/lib/api-hooks"
import { CompanyLogo, parseCompanyBrand } from "@/components/landing/company-logo"

const FALLBACK_COMPANIES = [
  "FoodHub",
  "ShopEase",
  "TechStore",
  "FashionCo",
  "QuickBite",
  "HomeGoods",
  "RetailPro",
  "FreshMart",
]

export function TrustedCompanies() {
  const { data } = useLanding()
  const companies = data?.trustedCompanies?.length
    ? data.trustedCompanies
    : FALLBACK_COMPANIES

  const brands = companies.map(parseCompanyBrand)
  const doubled = [...brands, ...brands]

  return (
    <section className="border-y border-border/60 bg-muted/30 py-10">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <p className="mb-8 text-center text-xs font-medium uppercase tracking-widest text-muted-foreground">
          Trusted by growing businesses
        </p>
        <div className="relative overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_8%,black_92%,transparent)]">
          <div className="flex w-max animate-marquee items-center gap-6">
            {doubled.map((brand, i) => (
              <CompanyLogo
                key={`${brand.name}-${i}`}
                name={brand.name}
                logoUrl={brand.logoUrl}
              />
            ))}
          </div>
        </div>
      </div>
    </section>
  )
}
