import { Link } from '@inertiajs/react'
import { Instagram } from 'lucide-react'
import type { BrandTheme } from '@/lib/theme-utils'

export type StorefrontFooterCompany = {
  name: string
  whatsappUrl?: string | null
  instagramUrl?: string | null
  facebookUrl?: string | null
  tiktokUrl?: string | null
  aboutUrl?: string
  termsUrl?: string
  theme?: BrandTheme | null
}

export function StorefrontFooter({ slug, company }: { slug: string; company: StorefrontFooterCompany }) {
  const theme = company.theme ?? {}
  const links = [
    { href: `/s/${slug}/about`, label: 'About' },
    { href: `/s/${slug}/track`, label: 'Track order' },
    { href: company.termsUrl || `/s/${slug}/terms`, label: 'Terms' },
  ]

  return (
    <footer className="border-t border-slate-200/80 bg-white py-10 dark:border-slate-800 dark:bg-slate-950">
      <div className="mx-auto flex max-w-5xl flex-col gap-6 px-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="max-w-sm space-y-2">
          <p className="text-sm font-extrabold tracking-tight text-slate-900 dark:text-white">{company.name}</p>
          <p className="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
            {theme.footer_text || `Shop ${company.name} online. Browse the catalog and check out when you are ready.`}
          </p>
        </div>
        <nav className="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
          {links.map((link) => (
            <Link key={link.href} href={link.href} className="hover:text-slate-900 dark:hover:text-white">
              {link.label}
            </Link>
          ))}
          {company.whatsappUrl && (
            <a href={company.whatsappUrl} target="_blank" rel="noreferrer" className="hover:text-emerald-700">
              WhatsApp
            </a>
          )}
          {company.instagramUrl && (
            <a href={company.instagramUrl} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 hover:text-slate-900 dark:hover:text-white">
              <Instagram className="h-3.5 w-3.5" /> Instagram
            </a>
          )}
          {company.facebookUrl && (
            <a href={company.facebookUrl} target="_blank" rel="noreferrer" className="hover:text-slate-900 dark:hover:text-white">
              Facebook
            </a>
          )}
          {company.tiktokUrl && (
            <a href={company.tiktokUrl} target="_blank" rel="noreferrer" className="hover:text-slate-900 dark:hover:text-white">
              TikTok
            </a>
          )}
        </nav>
      </div>
      <p className="mt-6 text-center text-[11px] text-slate-400">Powered by RelayIQ</p>
    </footer>
  )
}
