export function TrustedCompanies() {
  const companies = [
    "FoodHub",
    "ShopEase",
    "TechStore",
    "FashionCo",
    "QuickBite",
    "HomeGoods",
  ]

  return (
    <section className="border-y border-border/50 bg-card/30 py-12">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <p className="text-center text-sm font-medium text-muted-foreground mb-8">
          Trusted by leading businesses worldwide
        </p>
        <div className="flex flex-wrap items-center justify-center gap-x-12 gap-y-6">
          {companies.map((company) => (
            <div
              key={company}
              className="text-xl font-bold text-muted-foreground/60 hover:text-muted-foreground transition-colors"
            >
              {company}
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
