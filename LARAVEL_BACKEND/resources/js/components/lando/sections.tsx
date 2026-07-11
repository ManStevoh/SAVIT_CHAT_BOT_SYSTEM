import Link from "next/link"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

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
