"use client"

import Link from "next/link"
import { Twitter, Linkedin, Github } from "lucide-react"
import { AppLogoAndName } from "@/components/branding/AppLogoAndName"
import { useAppBranding } from "@/components/providers/AppBrandingProvider"

const DOCS_URL = "https://manstevoh.github.io/SAVIT_CHAT_BOT_SYSTEM/"
const CONTACT_EMAIL = "support@essemdigital.com"

const footerLinks = {
  Product: [
    { name: "Features", href: "#features" },
    { name: "Use cases", href: "#use-cases" },
    { name: "Growth Engine", href: "#growth" },
    { name: "Pricing", href: "#pricing" },
    { name: "Integrations", href: "#integrations" },
  ],
  Resources: [
    { name: "Documentation", href: DOCS_URL, external: true },
    { name: "FAQ", href: "#faq" },
    { name: "Product tour", href: "#demo" },
  ],
  Company: [
    { name: "About Essem Digital", href: "https://essemdigital.com", external: true },
    { name: "Contact", href: `mailto:${CONTACT_EMAIL}` },
    { name: "Privacy", href: "/privacy" },
    { name: "Terms", href: "/terms" },
  ],
}

export function Footer() {
  const branding = useAppBranding()
  const appName = branding.applicationName || "RelayIQ"

  return (
    <footer className="border-t border-border/70 bg-card">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="py-14 lg:py-16">
          <div className="grid gap-10 lg:grid-cols-5">
            <div className="lg:col-span-2">
              <AppLogoAndName variant="footer" />
              <p className="mt-4 max-w-xs text-sm leading-relaxed text-muted-foreground">
                Automate your WhatsApp business with AI-powered chatbots, order management, and in-chat payments.
                RelayIQ is a product of Essem Digital Innovation Limited.
              </p>
              <div className="mt-6 flex gap-4">
                {[
                  { Icon: Twitter, label: "Twitter" },
                  { Icon: Linkedin, label: "LinkedIn" },
                  { Icon: Github, label: "GitHub" },
                ].map(({ Icon, label }) => (
                  <Link
                    key={label}
                    href="#"
                    className="text-muted-foreground transition-colors hover:text-foreground"
                  >
                    <Icon className="h-4 w-4" />
                    <span className="sr-only">{label}</span>
                  </Link>
                ))}
              </div>
            </div>

            {Object.entries(footerLinks).map(([category, links]) => (
              <div key={category}>
                <h3 className="mb-3 text-sm font-semibold text-foreground">
                  {category}
                </h3>
                <ul className="space-y-2.5">
                  {links.map((link) => (
                    <li key={link.name}>
                      {"external" in link && link.external ? (
                        <a
                          href={link.href}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                          {link.name}
                        </a>
                      ) : link.href.startsWith("mailto:") ? (
                        <a
                          href={link.href}
                          className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                          {link.name}
                        </a>
                      ) : (
                        <Link
                          href={link.href}
                          className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                          {link.name}
                        </Link>
                      )}
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>

        <div className="flex flex-col items-center justify-between gap-3 border-t border-border/60 py-6 sm:flex-row">
          <div className="space-y-1 text-center sm:text-left">
            <p className="text-xs text-muted-foreground">
              © {new Date().getFullYear()} Essem Digital Innovation Limited. {appName} is a product of Essem Digital Innovation Limited.
            </p>
            <p className="text-xs text-muted-foreground">
              Powered by Essem Digital Innovation Limited ·{" "}
              <a href="https://essemdigital.com" className="hover:text-foreground underline-offset-2 hover:underline" target="_blank" rel="noopener noreferrer">
                essemdigital.com
              </a>
            </p>
          </div>
          <div className="flex gap-5">
            <Link href="/privacy" className="text-xs text-muted-foreground transition-colors hover:text-foreground">
              Privacy Policy
            </Link>
            <Link href="/terms" className="text-xs text-muted-foreground transition-colors hover:text-foreground">
              Terms of Service
            </Link>
          </div>
        </div>
      </div>
    </footer>
  )
}
