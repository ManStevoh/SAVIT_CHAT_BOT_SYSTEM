"use client"

import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion"
import { useLanding } from "@/lib/api-hooks"

/** Used only when GET /api/landing returns no faqs; real API data always takes precedence. */
const FALLBACK_FAQS = [
  { id: "1", question: "How does the WhatsApp integration work?", answer: "We use the official WhatsApp Business API to connect your business number. The setup process takes about 10 minutes and requires your business to be verified on Facebook Business Manager. Once connected, all messages are routed through our platform." },
  { id: "2", question: "Can I train the AI with my own data?", answer: "Absolutely! You can upload FAQs, product catalogs, pricing information, and business policies. Our AI learns from this data to provide accurate, contextual responses. You can also fine-tune responses based on customer interactions." },
  { id: "3", question: "What happens when the AI can't answer a question?", answer: "When our AI encounters a question it can't confidently answer, it seamlessly hands off the conversation to a human agent. Your team receives a notification with the full conversation context, ensuring a smooth customer experience." },
  { id: "4", question: "Is my customer data secure?", answer: "Security is our top priority. All data is encrypted in transit and at rest. We're SOC 2 compliant and GDPR ready. Your data is never used to train general AI models - your business data remains yours." },
  { id: "5", question: "Can I integrate with my existing CRM or e-commerce platform?", answer: "Yes! We offer native integrations with popular platforms like Shopify, WooCommerce, Salesforce, and HubSpot. We also provide a comprehensive API for custom integrations." },
  { id: "6", question: "What's included in the free trial?", answer: "The 14-day free trial includes full access to all features of the Growth plan. No credit card required to start. You can send up to 1,000 messages during the trial period." },
]

export function FAQSection() {
  const { data } = useLanding()
  const faqs = (data?.faqs?.length ? data.faqs : FALLBACK_FAQS)

  return (
    <section id="faq" className="py-20 lg:py-32 bg-card/30 border-y border-border/50">
      <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-12">
          <h2 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
            Frequently asked questions
          </h2>
          <p className="mt-4 text-lg text-muted-foreground">
            {"Everything you need to know about Savit Chat"}
          </p>
        </div>

        <Accordion type="single" collapsible className="w-full">
          {faqs.map((faq, index) => (
            <AccordionItem key={faq.id} value={`item-${faq.id}-${index}`} className="border-border">
              <AccordionTrigger className="text-left text-foreground hover:text-primary">
                {faq.question}
              </AccordionTrigger>
              <AccordionContent className="text-muted-foreground">
                {faq.answer}
              </AccordionContent>
            </AccordionItem>
          ))}
        </Accordion>
      </div>
    </section>
  )
}
