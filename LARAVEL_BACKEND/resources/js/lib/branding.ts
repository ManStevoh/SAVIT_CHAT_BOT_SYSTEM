/** RelayIQ product branding — company is Essem Digital Innovation Limited. */
export const BRAND = {
  productName: "RelayIQ",
  legalEntity: "Essem Digital Innovation Limited",
  companyWebsite: "https://essemdigital.com",
  tagline: "Every Conversation. Smarter.",
  poweredBy: "Powered by Essem Digital Innovation Limited",
  productOf: "RelayIQ is a product of Essem Digital Innovation Limited.",
  copyright: (year = new Date().getFullYear()) =>
    `© ${year} Essem Digital Innovation Limited. RelayIQ is a product of Essem Digital Innovation Limited. All rights reserved.`,
} as const
