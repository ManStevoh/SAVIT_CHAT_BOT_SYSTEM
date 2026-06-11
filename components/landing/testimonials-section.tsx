"use client"

import { Star } from "lucide-react"
import { useLanding } from "@/lib/api-hooks"
import { SectionHeader } from "@/components/shared/section-header"
import { FadeIn } from "@/components/shared/fade-in"
const FALLBACK_TESTIMONIALS = [
  {
    id: "1",
    name: "Sarah Johnson",
    role: "Owner, QuickBite Restaurant",
    content:
      "Savit Chat transformed our order management. We handle 3× more orders with the same team. Customers love the instant responses.",
    rating: 5,
    featured: true,
  },
  {
    id: "2",
    name: "Michael Chen",
    role: "CEO, TechStore",
    content:
      "Response time went from hours to seconds. Customer satisfaction increased 45%. Best investment we made this year.",
    rating: 5,
  },
  {
    id: "3",
    name: "Emily Rodriguez",
    role: "Marketing Director, FashionCo",
    content:
      "Set up in a day. The bot handles FAQs and orders while analytics help us understand customers better.",
    rating: 5,
  },
]

export function TestimonialsSection() {
  const { data, isLoading, error } = useLanding()
  const testimonials = data?.testimonials?.length
    ? data.testimonials.map((t, i) => ({ ...t, featured: i === 0 }))
    : FALLBACK_TESTIMONIALS

  const featured = testimonials.find((t) => "featured" in t && t.featured) ?? testimonials[0]
  const rest = testimonials.filter((t) => t.id !== featured?.id)

  return (
    <section id="testimonials" className="section-padding">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <FadeIn>
          <SectionHeader
            label="Customers"
            title="Loved by businesses worldwide"
            description="See what our customers have to say about Savit Chat."
          />
        </FadeIn>

        {isLoading && !data?.testimonials?.length ? (
          <div className="flex justify-center py-12">
            <span className="h-7 w-7 animate-spin rounded-full border-2 border-primary border-t-transparent" />
          </div>
        ) : (
          <div className="grid gap-5 lg:grid-cols-2">
            {featured && (
              <FadeIn>
                <div className="flex h-full flex-col rounded-xl border border-border/80 bg-card p-8 shadow-premium lg:row-span-2">
                  <div className="mb-5 flex gap-0.5">
                    {Array.from({ length: featured.rating }).map((_, i) => (
                      <Star key={i} className="h-4 w-4 fill-amber-400 text-amber-400" />
                    ))}
                  </div>
                  <blockquote className="flex-1 font-display text-xl leading-relaxed text-foreground lg:text-2xl">
                    &ldquo;{featured.content}&rdquo;
                  </blockquote>
                  <div className="mt-8 flex items-center gap-3 border-t border-border/60 pt-6">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted text-sm font-semibold text-foreground">
                      {featured.name.charAt(0)}
                    </div>
                    <div>
                      <div className="text-sm font-medium text-foreground">{featured.name}</div>
                      <div className="text-xs text-muted-foreground">{featured.role}</div>
                    </div>
                  </div>
                </div>
              </FadeIn>
            )}

            <div className="flex flex-col gap-5">
              {rest.map((testimonial, i) => (
                <FadeIn key={testimonial.id} delay={i * 80}>
                  <div className="rounded-xl border border-border/80 bg-card p-6 transition-all duration-300 hover:shadow-premium">
                    <div className="mb-3 flex gap-0.5">
                      {Array.from({ length: testimonial.rating }).map((_, j) => (
                        <Star key={j} className="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                      ))}
                    </div>
                    <blockquote className="text-sm leading-relaxed text-muted-foreground">
                      &ldquo;{testimonial.content}&rdquo;
                    </blockquote>
                    <div className="mt-4 flex items-center gap-2.5">
                      <div className="flex h-8 w-8 items-center justify-center rounded-full bg-muted text-xs font-semibold text-foreground">
                        {testimonial.name.charAt(0)}
                      </div>
                      <div>
                        <div className="text-sm font-medium text-foreground">{testimonial.name}</div>
                        <div className="text-xs text-muted-foreground">{testimonial.role}</div>
                      </div>
                    </div>
                  </div>
                </FadeIn>
              ))}
            </div>
          </div>
        )}

        {error && !data?.testimonials?.length && (
          <p className="mt-6 text-center text-xs text-muted-foreground">
            Showing sample testimonials. Configure them in Admin → Testimonials.
          </p>
        )}
      </div>
    </section>
  )
}
