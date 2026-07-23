import { LegalCmsPage } from "@/components/lando/legal-cms-page"

const FALLBACK = (
  <>
    <p className="text-sm leading-relaxed">
      <strong>Last updated:</strong> June 2026
    </p>

    <h2>1. Who we are</h2>
    <p>
      RelayIQ is operated by Essem Global Solutions. We provide a multi-tenant SaaS platform
      for WhatsApp business messaging, AI-assisted replies, order management, and related
      services.
    </p>

    <h2>2. Information we collect</h2>
    <p>We collect information you provide when you register and use the platform, including:</p>
    <ul>
      <li>Account details (name, email, company information)</li>
      <li>WhatsApp business configuration and message content routed through the platform</li>
      <li>Customer conversation data processed on your behalf</li>
      <li>Payment and subscription records (processed by Stripe or M-Pesa providers)</li>
      <li>Usage logs for billing, security, and product improvement</li>
    </ul>

    <h2>3. How we use your information</h2>
    <p>We use collected data to:</p>
    <ul>
      <li>Provide, operate, and improve the RelayIQ platform</li>
      <li>Process AI-assisted replies using your configured provider and business content</li>
      <li>Send service-related communications</li>
      <li>Comply with legal obligations and prevent abuse</li>
    </ul>

    <h2>4. Data sharing</h2>
    <p>
      We do not sell your data. We share information only with service providers necessary to
      operate the platform (e.g. Meta/WhatsApp Cloud API, payment processors, AI providers you
      configure) and when required by law.
    </p>

    <h2>5. Security</h2>
    <p>
      We use industry-standard measures including encryption in transit and access controls.
      Each tenant&apos;s data is logically isolated in our multi-tenant architecture.
    </p>

    <h2>6. Your rights</h2>
    <p>
      Depending on your jurisdiction, you may have rights to access, correct, or delete personal
      data. Contact us at{" "}
      <a href="mailto:support@essemglobalsolutions.com">support@essemglobalsolutions.com</a> to
      submit a request.
    </p>

    <h2>7. Changes</h2>
    <p>
      We may update this policy from time to time. Continued use of the service after changes
      constitutes acceptance of the updated policy.
    </p>
  </>
)

export default function PrivacyPage({ seo }: { seo?: import("@/components/seo/SeoHead").SeoPayload | null }) {
  return <LegalCmsPage slug="privacy" fallbackTitle="Privacy Policy" fallbackBody={FALLBACK} initialSeo={seo} />
}
