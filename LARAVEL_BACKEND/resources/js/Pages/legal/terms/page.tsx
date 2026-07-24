import { LegalCmsPage } from "@/components/lando/legal-cms-page"

const FALLBACK = (
  <>
    <p className="text-sm leading-relaxed">
      <strong>Last updated:</strong> June 2026
    </p>

    <h2>1. Acceptance</h2>
    <p>
      By creating an account or using RelayIQ, you agree to these Terms of Service. If you
      are using the service on behalf of a company, you represent that you have authority to
      bind that company.
    </p>

    <h2>2. Service description</h2>
    <p>
      RelayIQ provides WhatsApp business messaging, AI-assisted automation, order management,
      payment integrations, and related tools. Features vary by subscription plan.
    </p>

    <h2>3. Your responsibilities</h2>
    <p>You agree to:</p>
    <ul>
      <li>Comply with Meta&apos;s WhatsApp Business and Commerce policies</li>
      <li>Obtain necessary consents from your customers for messaging and data processing</li>
      <li>Keep your account credentials secure</li>
      <li>Use the service only for lawful business purposes</li>
    </ul>

    <h2>4. Subscriptions and billing</h2>
    <p>
      Paid plans are billed according to the pricing shown at checkout. Free trials convert to
      paid subscriptions unless cancelled before the trial ends. WhatsApp conversation fees
      charged by Meta may apply separately.
    </p>

    <h2>5. AI-generated content</h2>
    <p>
      AI replies are generated based on your configuration and content. You are responsible for
      reviewing automated responses and ensuring they meet your business and legal requirements.
    </p>

    <h2>6. Limitation of liability</h2>
    <p>
      The service is provided &quot;as is&quot; to the maximum extent permitted by law. RelayIQ is not liable for indirect, incidental, or consequential damages arising
      from use of the platform.
    </p>

    <h2>7. Termination</h2>
    <p>
      You may cancel your subscription at any time. We may suspend or terminate accounts that
      violate these terms or applicable law.
    </p>

    <h2>8. Contact</h2>
    <p>
      For questions about these terms, contact{" "}
      <a href="mailto:support@essemdigital.com">support@essemdigital.com</a>.
    </p>
  </>
)

export default function TermsPage({ seo }: { seo?: import("@/components/seo/SeoHead").SeoPayload | null }) {
  return <LegalCmsPage slug="terms" fallbackTitle="Terms of Service" fallbackBody={FALLBACK} initialSeo={seo} />
}
