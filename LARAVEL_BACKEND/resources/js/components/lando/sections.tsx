import Link from "next/link"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"
import { LandoHeroFlowSimulation } from "./hero-flow-simulation"

interface LandoHeroProps {
  kicker?: string
  title: string
  description?: string
  primaryCtaText?: string
  primaryCtaHref?: string
  secondaryCtaText?: string
  secondaryCtaHref?: string
  imageUrl?: string
  imageAlt?: string
  showFlowSimulation?: boolean
}

export function LandoHeroSection({
  kicker,
  title,
  description,
  primaryCtaText,
  primaryCtaHref,
  secondaryCtaText,
  secondaryCtaHref,
  imageUrl,
  imageAlt = "",
  showFlowSimulation = false,
}: LandoHeroProps) {
  return (
    <section className="lando-hero bg-[#f3f4f6] pt-28 pb-16 lg:pt-32 lg:pb-24">
      <div className="mx-auto grid max-w-6xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
        <div>
          {kicker && (
            <p className="mb-4 text-xs font-semibold tracking-widest text-gray-500 uppercase">
              — {kicker}
            </p>
          )}
          <h1 className="text-4xl font-bold leading-tight text-black sm:text-5xl lg:text-[3.25rem] lg:leading-[1.1]">
            {title}
          </h1>
          {description && (
            <p className="mt-5 max-w-lg text-base leading-relaxed text-gray-600 sm:text-lg">
              {description}
            </p>
          )}
          {(primaryCtaText || secondaryCtaText) && (
            <div className="mt-8 flex flex-wrap gap-3">
              {primaryCtaText && primaryCtaHref && (
                <Button
                  asChild
                  className="h-11 rounded-lg bg-[#2563eb] px-6 text-white hover:bg-[#1d4ed8]"
                >
                  <Link href={primaryCtaHref}>{primaryCtaText}</Link>
                </Button>
              )}
              {secondaryCtaText && secondaryCtaHref && (
                <Button
                  asChild
                  variant="outline"
                  className="h-11 rounded-lg border-black bg-white px-6 text-black hover:bg-gray-50"
                >
                  <Link href={secondaryCtaHref}>{secondaryCtaText}</Link>
                </Button>
              )}
            </div>
          )}
        </div>

        {showFlowSimulation ? (
          <LandoHeroFlowSimulation />
        ) : (
          imageUrl && (
            <div className="flex justify-center lg:justify-end">
              <img
                src={imageUrl}
                alt={imageAlt}
                className="max-h-[420px] w-full max-w-md object-contain"
              />
            </div>
          )
        )}
      </div>
    </section>
  )
}

export function LandoPageHero({ title, description }: { title: string; description?: string }) {
  return (
    <section className="bg-[#f3f4f6] pt-28 pb-12 text-center lg:pt-32">
      <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <h1 className="text-4xl font-bold text-black sm:text-5xl">{title}</h1>
        {description && (
          <p className="mx-auto mt-4 max-w-xl text-base text-gray-600 sm:text-lg">{description}</p>
        )}
      </div>
    </section>
  )
}

export function LandoIntroCard({
  title,
  description,
  ctaText,
  ctaHref,
  imageUrl,
  imageAlt = "",
}: {
  title: string
  description?: string
  ctaText?: string
  ctaHref?: string
  imageUrl?: string
  imageAlt?: string
}) {
  return (
    <section className="bg-[#f3f4f6] py-12 lg:py-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div className="overflow-hidden rounded-3xl bg-white p-8 shadow-sm lg:p-12">
          <div className="grid items-center gap-10 lg:grid-cols-2">
            <div>
              <h2 className="text-3xl font-bold text-black sm:text-4xl">{title}</h2>
              {description && <p className="mt-4 text-base text-gray-600 sm:text-lg">{description}</p>}
              {ctaText && ctaHref && (
                <Button asChild className="mt-8 h-11 rounded-lg bg-[#2563eb] px-6 text-white hover:bg-[#1d4ed8]">
                  <Link href={ctaHref}>{ctaText}</Link>
                </Button>
              )}
            </div>
            {imageUrl && (
              <img src={imageUrl} alt={imageAlt} className="mx-auto max-h-72 w-full object-contain" />
            )}
          </div>
        </div>
      </div>
    </section>
  )
}

export function LandoFeatureBlock({
  label,
  title,
  description,
  ctaText,
  ctaHref,
  imageUrl,
  imageAlt = "",
  imagePosition = "left",
}: {
  label?: string
  title: string
  description?: string
  ctaText?: string
  ctaHref?: string
  imageUrl?: string
  imageAlt?: string
  imagePosition?: "left" | "right"
}) {
  const image = imageUrl ? (
    <img src={imageUrl} alt={imageAlt} className="mx-auto max-h-80 w-full object-contain" />
  ) : null

  return (
    <section className="bg-[#f3f4f6] py-12 lg:py-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div className="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
          {imagePosition === "left" && image}
          <div className={cn(imagePosition === "right" ? "lg:order-1" : "lg:order-2")}>
            {label && (
              <p className="mb-3 text-xs font-bold tracking-widest text-gray-500 uppercase">{label}</p>
            )}
            <h2 className="text-3xl font-bold text-black sm:text-4xl">{title}</h2>
            {description && <p className="mt-4 text-base leading-relaxed text-gray-600">{description}</p>}
            {ctaText && ctaHref && (
              <Button
                asChild
                variant="outline"
                className="mt-8 h-11 rounded-lg border-black bg-transparent px-6 text-black hover:bg-gray-50"
              >
                <Link href={ctaHref}>{ctaText}</Link>
              </Button>
            )}
          </div>
          {imagePosition === "right" && image}
        </div>
      </div>
    </section>
  )
}

export function LandoHowToJoin({
  title,
  description,
  ctaText,
  ctaHref,
  imageUrl,
  imageAlt = "",
  steps = [],
}: {
  title: string
  description?: string
  ctaText?: string
  ctaHref?: string
  imageUrl?: string
  imageAlt?: string
  steps?: Array<{ title: string; description: string }>
}) {
  return (
    <section id="how-to-join" className="bg-[#f3f4f6] py-12 lg:py-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div className="overflow-hidden rounded-3xl bg-white p-8 shadow-sm lg:p-12">
          <div className="grid gap-10 lg:grid-cols-2 lg:gap-16">
            <div>
              {imageUrl && (
                <img src={imageUrl} alt={imageAlt} className="mb-8 max-h-48 w-full object-contain" />
              )}
              <h2 className="text-3xl font-bold text-black sm:text-4xl">{title}</h2>
              {description && <p className="mt-3 text-gray-600">{description}</p>}
              {ctaText && ctaHref && (
                <Button asChild className="mt-8 h-11 rounded-lg bg-[#2563eb] px-6 text-white hover:bg-[#1d4ed8]">
                  <Link href={ctaHref}>{ctaText}</Link>
                </Button>
              )}
            </div>
            <div className="divide-y divide-gray-200">
              {steps.map((step) => (
                <div key={step.title} className="py-6 first:pt-0 last:pb-0">
                  <h3 className="text-lg font-bold text-black">{step.title}</h3>
                  <p className="mt-2 text-gray-600">{step.description}</p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}

export function LandoCtaSection({
  title,
  description,
  ctaText,
  ctaHref,
  imageUrl,
  imageAlt = "",
  showImage = true,
}: {
  title: string
  description?: string
  ctaText?: string
  ctaHref?: string
  imageUrl?: string
  imageAlt?: string
  showImage?: boolean
}) {
  return (
    <section className="bg-[#f3f4f6] py-12 lg:py-16">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div className="overflow-hidden rounded-3xl bg-white p-8 shadow-sm lg:p-12">
          <div className={cn("grid items-center gap-10", showImage && imageUrl && "lg:grid-cols-2")}>
            <div>
              <h2 className="text-3xl font-bold text-black sm:text-4xl">{title}</h2>
              {description && <p className="mt-3 text-gray-600">{description}</p>}
              {ctaText && ctaHref && (
                <Button asChild className="mt-8 h-11 rounded-lg bg-[#2563eb] px-6 text-white hover:bg-[#1d4ed8]">
                  <Link href={ctaHref}>{ctaText}</Link>
                </Button>
              )}
            </div>
            {showImage && imageUrl && (
              <img src={imageUrl} alt={imageAlt} className="mx-auto max-h-64 w-full object-contain" />
            )}
          </div>
        </div>
      </div>
    </section>
  )
}
