import Link from "next/link"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"
import { BRAND } from "@/lib/branding"
import type { CmsLink } from "./types"

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
}: {
  href: string
  kind: "play" | "apple"
}) {
  const isPlay = kind === "play"
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className="inline-flex min-w-[9.75rem] items-center gap-2.5 rounded-lg bg-black px-3 py-2 text-white transition hover:bg-gray-900"
    >
      {isPlay ? <GooglePlayIcon /> : <AppleIcon />}
      <span className="flex flex-col leading-tight">
        <span className="text-[10px] uppercase tracking-wide text-gray-300">
          {isPlay ? "Get it on" : "Download on the"}
        </span>
        <span className="text-sm font-semibold">{isPlay ? "Google Play" : "App Store"}</span>
      </span>
    </a>
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
  const showApp = Boolean(mobileApp?.enabled)
  const playUrl = mobileApp?.playStoreUrl?.trim() || ""
  const appUrl = mobileApp?.appStoreUrl?.trim() || ""
  const hasStoreLinks = playUrl !== "" || appUrl !== ""
  const appTitle = mobileApp?.title?.trim() || "Get the mobile app"
  const appDescription =
    mobileApp?.description?.trim() ||
    (hasStoreLinks
      ? "Manage chats, orders, and growth on the go."
      : "Coming soon on Google Play and the App Store.")

  return (
    <footer className="lando-footer border-t border-gray-200 bg-[#f3f4f6]">
      <div className="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:grid-cols-2 lg:grid-cols-4 sm:px-6 lg:px-8">
        <div>
          <AppLogoAndName variant="navbar" className="font-bold text-black" />
          <p className="mt-3 text-sm text-gray-600">{BRAND.productOf}</p>
          <p className="mt-1 text-sm text-gray-500">{BRAND.poweredBy}</p>
          <p className="mt-4 text-sm text-gray-500">{copy}</p>
          <a
            href={BRAND.companyWebsite}
            target="_blank"
            rel="noopener noreferrer"
            className="mt-2 inline-block text-sm font-medium text-[#2563eb] hover:underline"
          >
            essemdigital.com
          </a>
        </div>

        <div className="flex flex-col gap-2">
          {navLinks.map((link) => (
            <Link key={link.href} href={link.href} className="text-sm font-medium text-black hover:text-[#2563eb]">
              {link.label}
            </Link>
          ))}
        </div>

        <div className="flex flex-col gap-2">
          {socialLinks.map((link) => (
            <a
              key={link.label}
              href={link.href}
              target="_blank"
              rel="noopener noreferrer"
              className="text-sm font-medium text-black hover:text-[#2563eb]"
            >
              {link.label}
            </a>
          ))}
        </div>

        <div className="flex flex-col gap-2">
          {legalLinks.map((link) => (
            <Link key={link.href} href={link.href} className="text-sm font-medium text-black hover:text-[#2563eb]">
              {link.label}
            </Link>
          ))}

          {showApp && (
            <div className="mt-4 space-y-3 border-t border-gray-200 pt-4">
              <div>
                <p className="text-sm font-semibold text-black">{appTitle}</p>
                <p className="mt-1 text-xs text-gray-500">{appDescription}</p>
              </div>
              {hasStoreLinks ? (
                <div className="flex flex-wrap gap-2">
                  {playUrl !== "" && <StoreBadge href={playUrl} kind="play" />}
                  {appUrl !== "" && <StoreBadge href={appUrl} kind="apple" />}
                </div>
              ) : (
                <p className="text-xs font-medium text-gray-600">Coming soon</p>
              )}
            </div>
          )}
        </div>
      </div>
    </footer>
  )
}
