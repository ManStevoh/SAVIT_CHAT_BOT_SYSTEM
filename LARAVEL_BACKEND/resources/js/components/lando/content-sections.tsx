import { Star } from "lucide-react"
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion"
import { Button } from "@/components/ui/button"
import Link from "next/link"
import { usePlans } from "@/lib/api-hooks"
import { Check } from "lucide-react"
import { cn } from "@/lib/utils"
import { useState, useEffect } from "react"
import { createCheckoutSession } from "@/lib/api-actions"
import { getAuthToken } from "@/lib/api-client"
import { toast } from "sonner"

export function LandoTrustedCompanies({
  title,
  companies = [],
}: {
  title?: string
  companies?: Array<{ name: string; logoUrl?: string } | string>
}) {
  const parsed = companies.map((c) =>
    typeof c === "string" ? { name: c, logoUrl: "" } : c
  )

  return (
    <section className="bg-[#f3f4f6] py-12">
      <div className="mx-auto max-w-6xl px-4 text-center sm:px-6 lg:px-8">
        {title && <p className="text-sm text-gray-600">{title}</p>}
        <div className="mt-8 flex flex-wrap items-center justify-center gap-8 lg:gap-12">
          {parsed.map((company) => (
            <div key={company.name} className="flex items-center gap-2">
              {company.logoUrl ? (
                <img src={company.logoUrl} alt={company.name} className="h-8 max-w-[120px] object-contain opacity-60" />
              ) : (
                <span className="text-lg font-bold text-gray-400">{company.name}</span>
              )}
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

export function LandoTestimonials({
  title,
  description,
  testimonials = [],
}: {
  title?: string
  description?: string
  testimonials?: Array<{ id: string; name: string; role: string; content: string; rating: number }>
}) {
  if (testimonials.length === 0) return null

  return (
    <section className="bg-[#f3f4f6] py-16 lg:py-24">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div className="text-center">
          {title && <h2 className="text-3xl font-bold text-black sm:text-4xl">{title}</h2>}
          {description && <p className="mt-3 text-gray-600">{description}</p>}
        </div>
