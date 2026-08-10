/** RelayIQ product branding — company is Essem Digital Innovation Limited. */
export const BRAND = {
  productName: "RelayIQ",
  legalEntity: "Essem Digital Innovation Limited",
  companyWebsite: "https://essemdigital.com",
  tagline: "Every Conversation. Smarter.",
  /** Short credit for compact UI (auth shells, badges). */
  poweredBy: "Powered by Essem Digital",
  /** One-line product attribution for footers. */
  productOf: "A product of Essem Digital Innovation Limited",
  copyright: (year = new Date().getFullYear()) =>
    `© ${year} Essem Digital Innovation Limited. All rights reserved.`,
} as const
