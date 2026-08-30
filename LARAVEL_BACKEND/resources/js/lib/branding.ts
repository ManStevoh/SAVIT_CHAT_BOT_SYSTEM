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

/** Prefer CMS social links when they are real URLs; otherwise official profiles. */
export function resolveSocialLinks(
  cmsLinks?: Array<{ label: string; href: string }> | null
): Array<{ label: string; href: string }> {
  const official = BRAND.social.map((link) => ({ label: link.label, href: link.href }))
  const usable = (cmsLinks ?? []).filter((link) => {
    const href = (link.href || "").trim()
    return href !== "" && href !== "#" && !href.toLowerCase().startsWith("javascript:")
  })

  return usable.length > 0 ? usable : official
}
