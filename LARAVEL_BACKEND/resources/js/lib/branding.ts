/** RelayIQ product branding — company is Essem Digital Innovation Limited. */
export const BRAND = {
  productName: "RelayIQ",
  legalEntity: "Essem Digital Innovation Limited",
  companyWebsite: "https://relayiq.app",
  tagline: "Every Conversation. Smarter.",
  /** Official channel partnership trust line (public marketing). */
  whatsappPartner: "Official WhatsApp Business Partner",
  /** Short credit for compact UI (auth shells, badges). */
  poweredBy: "Powered by Essem Digital",
  /** One-line product attribution for footers. */
  productOf: "A product of Essem Digital Innovation Limited",
  copyright: (year = new Date().getFullYear()) =>
    `© ${year} Essem Digital Innovation Limited. All rights reserved.`,
  social: [
    { label: "Facebook", href: "https://www.facebook.com/share/1KxpxJ2VtK/" },
    { label: "Instagram", href: "https://www.instagram.com/relayiq.app" },
  ],
} as const

function isExternalSocialUrl(href: string): boolean {
  if (!/^https?:\/\//i.test(href)) return false
  try {
    const host = new URL(href).hostname.toLowerCase()
    return host !== "" && host !== "relayiq.app" && host !== "www.relayiq.app"
  } catch {
    return false
  }
}

/** Official Facebook + Instagram always win over CMS "#" / same-site placeholders. */
export function resolveSocialLinks(
  cmsLinks?: Array<{ label: string; href?: string; url?: string }> | null
): Array<{ label: string; href: string }> {
  const official = BRAND.social.map((link) => ({ label: link.label, href: link.href }))
  const officialLabels = new Set(official.map((link) => link.label.toLowerCase()))
  const extra: Array<{ label: string; href: string }> = []

  for (const link of cmsLinks ?? []) {
    const label = (link.label || "").trim()
    const href = (link.href || link.url || "").trim()
    if (!label || officialLabels.has(label.toLowerCase())) continue
    if (isExternalSocialUrl(href)) {
      extra.push({ label, href })
    }
  }

  return [...official, ...extra]
}
