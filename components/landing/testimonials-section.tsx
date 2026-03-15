import { Star } from "lucide-react"

const testimonials = [
  {
    name: "Sarah Johnson",
    role: "Owner, QuickBite Restaurant",
    content: "ChatFlow AI transformed our order management. We now handle 3x more orders with the same team. The AI chatbot understands our menu perfectly and customers love the instant responses.",
    rating: 5,
  },
  {
    name: "Michael Chen",
    role: "CEO, TechStore",
    content: "The ROI has been incredible. We reduced response time from hours to seconds and our customer satisfaction scores increased by 45%. Best investment we made this year.",
    rating: 5,
  },
  {
    name: "Emily Rodriguez",
    role: "Marketing Director, FashionCo",
    content: "Setting up was surprisingly easy. Within a day, we had our AI bot handling FAQs and order inquiries. The analytics dashboard helps us understand our customers better.",
    rating: 5,
  },
]

export function TestimonialsSection() {
  return (
    <section id="testimonials" className="py-20 lg:py-32">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-16">
          <h2 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
            Loved by businesses worldwide
          </h2>
          <p className="mt-4 text-lg text-muted-foreground max-w-2xl mx-auto">
            See what our customers have to say about ChatFlow AI
          </p>
        </div>

        <div className="grid gap-8 md:grid-cols-3">
          {testimonials.map((testimonial) => (
            <div
              key={testimonial.name}
              className="rounded-2xl border border-border bg-card p-6 transition-all hover:shadow-lg hover:shadow-primary/5"
            >
              <div className="flex gap-1 mb-4">
                {Array.from({ length: testimonial.rating }).map((_, i) => (
                  <Star key={i} className="h-5 w-5 fill-primary text-primary" />
                ))}
              </div>
              <blockquote className="text-muted-foreground mb-6 leading-relaxed">
                {`"${testimonial.content}"`}
              </blockquote>
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-full bg-primary/20 flex items-center justify-center text-primary font-semibold">
                  {testimonial.name.charAt(0)}
                </div>
                <div>
                  <div className="font-medium text-foreground">{testimonial.name}</div>
                  <div className="text-sm text-muted-foreground">{testimonial.role}</div>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
