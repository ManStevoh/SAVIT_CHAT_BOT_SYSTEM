"use client"

import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion"
import { useLanding } from "@/lib/api-hooks"
import { SectionHeader } from "@/components/shared/section-header"

const FALLBACK_FAQS = [
  { id: "1", question: "How does the WhatsApp integration work?", answer: "We use the official WhatsApp Business Cloud API. Connect your Meta Business account, verify your number, and messages route through RelayIQ. Embedded signup is available when enabled by your platform admin." },
  { id: "2", question: "Can I train the AI with my own data?", answer: "Yes. Add FAQs, product catalogs, and business policies in your dashboard. The AI uses this content plus conversation learning (when enabled) to reply in your brand voice." },
  { id: "3", question: "What happens when the AI can't answer a question?", answer: "Conversations can be handed off to a human agent at any time. Your team sees full chat history, takes over in the inbox, and the bot pauses until you release the conversation." },
  { id: "4", question: "Is my customer data secure?", answer: "Data is encrypted in transit (TLS) and stored securely. Each company's data is isolated in our multi-tenant architecture. You control your AI provider credentials and can review usage in the dashboard." },
  { id: "5", question: "What payments do you support?", answer: "M-Pesa STK push for Kenyan customers and Stripe for card payments and subscriptions. Customers can pay directly inside the WhatsApp conversation flow." },
  { id: "6", question: "What's included in the free trial?", answer: "The 14-day free trial gives you access to core platform features. No credit card is required to start. Plan limits apply based on the tier you choose after the trial." },
]

export function FAQSection() {
  const { data } = useLanding()
  const faqs = data?.faqs?.length ? data.faqs : FALLBACK_FAQS

  return (
    <section id="faq" className="section-padding landing-divider">
      <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
        <SectionHeader
          label="FAQ"
          title="Common questions"
          description="Quick answers before you sign up."
        />

        <Accordion type="single" collapsible className="w-full">
            {faqs.map((faq, index) => (
              <AccordionItem
                key={faq.id}
                value={`item-${faq.id}-${index}`}
                className="border-border/60"
              >
                <AccordionTrigger className="text-left text-sm font-medium text-foreground hover:no-underline">
                  {faq.question}
                </AccordionTrigger>
                <AccordionContent className="text-sm leading-relaxed text-muted-foreground">
                  {faq.answer}
                </AccordionContent>
              </AccordionItem>
            ))}
          </Accordion>
      </div>
    </section>
  )
}
