"use client"

import { Star } from "lucide-react"
import { useLanding } from "@/lib/api-hooks"
import { SectionHeader } from "@/components/shared/section-header"

const FALLBACK_TESTIMONIALS = [
  {
    id: "1",
    name: "Sarah Johnson",
    role: "Owner, QuickBite Restaurant",
    content:
      "We handle more orders with the same team. Customers get instant replies and pay in chat.",
    rating: 5,
  },
  {
    id: "2",
    name: "Michael Chen",
    role: "CEO, TechStore",
    content: "Response time dropped from hours to seconds. Setup took one afternoon.",
    rating: 5,
  },
  {
    id: "3",
    name: "Emily Rodriguez",
    role: "Marketing Director, FashionCo",
    content: "The bot handles FAQs and orders. Analytics show us what customers actually want.",
    rating: 5,
  },
]

export function TestimonialsSection() {
  const { data, isLoading } = useLanding()
  const isSample = !data?.testimonials?.length
  const testimonials = isSample ? FALLBACK_TESTIMONIALS : data!.testimonials!

  return (
    <section id="testimonials" className="section-padding landing-divider">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <SectionHeader
          label="Customers"
          title="What teams say after going live"
          description={
            isSample
              ? "Add real testimonials in Admin → Testimonials. Examples shown below."
              : "Feedback from businesses using RelayIQ."
          }
        />

        {isLoading && isSample ? (
          <div className="flex justify-center py-12">
            <span className="h-7 w-7 animate-spin rounded-full border-2 border-primary border-t-transparent" />
          </div>
        ) : (
          <div className="grid gap-4 md:grid-cols-3">
            {testimonials.map((testimonial) => (
              <div key={testimonial.id} className="landing-card flex flex-col p-6">
                <div className="mb-4 flex gap-0.5">
                  {Array.from({ length: testimonial.rating }).map((_, i) => (
                    <Star key={i} className="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                  ))}
                </div>
                <blockquote className="flex-1 text-sm leading-relaxed text-foreground">
                  &ldquo;{testimonial.content}&rdquo;
                </blockquote>
                <div className="mt-5 border-t border-border/70 pt-4">
                  <div className="text-sm font-medium text-foreground">{testimonial.name}</div>
                  <div className="text-xs text-muted-foreground">{testimonial.role}</div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </section>
  )
}
