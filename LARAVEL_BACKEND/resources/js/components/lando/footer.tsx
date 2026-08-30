import Link from "next/link"
import type { ReactNode } from "react"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"
import { BRAND, resolveSocialLinks } from "@/lib/branding"
import { cn } from "@/lib/utils"
import type { CmsLink } from "./types"
import { WhatsAppPartnerBadge } from "./whatsapp-partner-badge"

export interface FooterMobileApp {
  enabled?: boolean
  title?: string
  description?: string
  playStoreUrl?: string
  appStoreUrl?: string
}

interface LandoFooterProps {
  copyright?: string
  navLinks?: CmsLink[]
  socialLinks?: CmsLink[]
  legalLinks?: CmsLink[]
  mobileApp?: FooterMobileApp
}

/** Parse mobile-app block from CMS footer section content. */
export function mobileAppFromFooterContent(content: Record<string, unknown>): FooterMobileApp {
  return {
    enabled: Boolean(content.showMobileApp),
    title: typeof content.mobileAppTitle === "string" ? content.mobileAppTitle : undefined,
    description: typeof content.mobileAppDescription === "string" ? content.mobileAppDescription : undefined,
    playStoreUrl: typeof content.playStoreUrl === "string" ? content.playStoreUrl : undefined,
    appStoreUrl: typeof content.appStoreUrl === "string" ? content.appStoreUrl : undefined,
  }
}

function GooglePlayIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-5 w-5 shrink-0" aria-hidden>
      <path fill="#EA4335" d="M3.6 2.2 13.5 12 3.6 21.8A2 2 0 0 1 2 20.1V3.9a2 2 0 0 1 1.6-1.7Z" />
      <path fill="#FBBC04" d="m13.5 12 3.2-3.2 4.1 2.4a1.5 1.5 0 0 1 0 2.6l-4.1 2.4L13.5 12Z" />
      <path fill="#4285F4" d="M13.5 12 3.6 2.2l9.1 5.3L16.7 8.8 13.5 12Z" />
      <path fill="#34A853" d="M13.5 12 3.6 21.8l9.1-5.3 4-1.4L13.5 12Z" />
    </svg>
  )
}

function AppleIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-5 w-5 shrink-0 fill-white" aria-hidden>
      <path d="M16.4 12.7c0-2.2 1.8-3.3 1.9-3.4-1-1.5-2.6-1.7-3.2-1.7-1.4-.1-2.6.8-3.3.8-.7 0-1.7-.8-2.9-.8-1.5 0-2.9.9-3.6 2.2-1.6 2.7-.4 6.7 1.1 8.9.7 1.1 1.6 2.3 2.8 2.2 1.1 0 1.5-.7 2.9-.7 1.3 0 1.7.7 2.9.7 1.2 0 2-.1 2.8-2.2.6-.9.9-1.8.9-1.8s-1.8-.7-1.8-3.2ZM14.7 6.2c.6-.8 1.1-1.8.9-2.9-1 .1-2.1.7-2.8 1.4-.6.7-1.2 1.7-1 2.8 1.1.1 2.2-.5 2.9-1.3Z" />
    </svg>
  )
}

function StoreBadge({
  href,
  kind,
  comingSoon = false,
}: {
  href?: string
  kind: "play" | "apple"
  comingSoon?: boolean
}) {
  const isPlay = kind === "play"
  const labelTop = comingSoon ? "Coming soon" : isPlay ? "Get it on" : "Download on the"
  const labelBottom = isPlay ? "Google Play" : "App Store"
  const className = cn(
    "inline-flex min-w-[9.5rem] items-center gap-2.5 rounded-lg bg-black px-3 py-2 text-white",
    comingSoon ? "cursor-default opacity-90" : "transition hover:bg-gray-900"
  )

  const inner = (
    <>
      {isPlay ? <GooglePlayIcon /> : <AppleIcon />}
      <span className="flex flex-col leading-tight">
        <span className="text-[10px] uppercase tracking-wide text-gray-300">{labelTop}</span>
        <span className="text-sm font-semibold">{labelBottom}</span>
      </span>
    </>
  )

  if (comingSoon || !href) {
    return (
      <span className={className} aria-label={`${labelBottom} — coming soon`}>
        {inner}
      </span>
    )
  }

  return (
    <a href={href} target="_blank" rel="noopener noreferrer" className={className}>
      {inner}
    </a>
  )
}

function FooterLinkColumn({ title, children }: { title: string; children: ReactNode }) {
  return (
    <div className="min-w-0">
      <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{title}</p>
      <div className="mt-3 flex flex-col gap-2">{children}</div>
    </div>
  )
}

export function LandoFooter({
  copyright,
  navLinks = [],
  socialLinks = [],
  legalLinks = [],
  mobileApp,
}: LandoFooterProps) {
  const copy = copyright?.trim() || BRAND.copyright()
  const resolvedSocial = resolveSocialLinks(socialLinks)
  const showApp = Boolean(mobileApp?.enabled)
  const playUrl = mobileApp?.playStoreUrl?.trim() || ""
  const appUrl = mobileApp?.appStoreUrl?.trim() || ""
  const appTitle = mobileApp?.title?.trim() || "iOS & Android apps"
  const appDescription =
    mobileApp?.description?.trim() ||
    (playUrl || appUrl
      ? "Manage chats, orders, and growth on the go."
      : "Launching soon on iOS and Android.")

  return (
    <footer className="lando-footer border-t border-border bg-muted">
      <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8 lg:py-12">
        <div className="grid gap-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1.85fr)] lg:gap-12">
          <div className="min-w-0">
            <AppLogoAndName variant="navbar" className="font-bold text-foreground" />
            <div className="mt-3">
              <WhatsAppPartnerBadge variant="plain" />
            </div>
            <p className="mt-3 max-w-sm text-sm text-muted-foreground">{BRAND.productOf}</p>
          </div>

          <div className="grid grid-cols-2 gap-6 sm:grid-cols-3 sm:gap-8">
            {navLinks.length > 0 && (
              <FooterLinkColumn title="Platform">
                {navLinks.map((link) => (
                  <Link
                    key={link.href}
                    href={link.href}
                    className="text-sm font-medium text-foreground hover:text-primary"
                  >
                    {link.label}
                  </Link>
                ))}
              </FooterLinkColumn>
            )}
            {resolvedSocial.length > 0 && (
              <FooterLinkColumn title="Social">
                {resolvedSocial.map((link) => (
                  <a
                    key={link.label}
                    href={link.href}
                    target="_blank"
                    rel="me noopener noreferrer"
                    className="text-sm font-medium text-foreground hover:text-primary"
                    aria-label={`${BRAND.productName} on ${link.label}`}
                  >
                    {link.label}
                  </a>
                ))}
              </FooterLinkColumn>
            )}
            {legalLinks.length > 0 && (
              <FooterLinkColumn title="Legal">
                {legalLinks.map((link) => (
                  <Link
                    key={link.href}
                    href={link.href}
                    className="text-sm font-medium text-foreground hover:text-primary"
                  >
                    {link.label}
                  </Link>
                ))}
              </FooterLinkColumn>
            )}
          </div>
        </div>

        {(showApp || copy) && (
          <div className="mt-8 flex flex-col gap-5 border-t border-border pt-6 sm:flex-row sm:items-end sm:justify-between">
            {showApp ? (
              <div className="min-w-0">
                <p className="text-sm font-semibold text-foreground">{appTitle}</p>
                <p className="mt-1 max-w-md text-xs text-muted-foreground">{appDescription}</p>
                <div className="mt-3 flex flex-wrap gap-2">
                  <StoreBadge kind="apple" href={appUrl || undefined} comingSoon={!appUrl} />
                  <StoreBadge kind="play" href={playUrl || undefined} comingSoon={!playUrl} />
                </div>
              </div>
            ) : (
              <div />
            )}

            <div className="shrink-0 text-sm text-muted-foreground sm:text-right">
              <p>{copy}</p>
              <a
                href={BRAND.companyWebsite}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-1 inline-block font-medium text-primary hover:underline"
              >
                relayiq.app
              </a>
            </div>
          </div>
        )}
      </div>
    </footer>
  )
}
