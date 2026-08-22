'use client'

import { MessageCircle, Store } from 'lucide-react'
import { resolveStorefrontStyle, type BrandTheme } from '@/lib/theme-utils'
import { SeoHead, type SeoPayload } from '@/components/seo/SeoHead'

type BioLink = { label: string; url: string }
type Props = {
  slug: string
  company: {
    name: string
    headline?: string | null
    bio?: string | null
    logo?: string | null
    links: BioLink[]
    whatsappNumber?: string | null
    storefrontEnabled: boolean
    storeSlug?: string | null
    theme?: BrandTheme
  }
  seo?: SeoPayload | null
}

export default function LinkInBioPage({ company, seo }: Props) {
  const waHref = company.whatsappNumber
    ? `https://wa.me/${company.whatsappNumber.replace(/\D/g, '')}`
    : null

  const style = resolveStorefrontStyle(company.theme)
  const primaryColor = (company.theme?.primary_color) || '#0f172a'
  const borderRadius = ((style as Record<string, string | undefined>)['--sf-radius']) || '9999px'

  return (
    <>
      <SeoHead seo={seo} fallbackTitle={`${company.name} — Links & Bio`} />
      <div
        className="flex min-h-screen justify-center bg-gradient-to-b from-slate-50 to-white px-4 py-12"
        style={style}
      >
      <div className="w-full max-w-md space-y-6 text-center">
        {company.logo ? (
          <img
            src={company.logo}
            alt={company.name}
            className="mx-auto h-20 w-20 object-cover shadow-sm"
            style={{ borderRadius }}
          />
        ) : (
          <div
            className="mx-auto flex h-20 w-20 items-center justify-center text-2xl font-semibold text-white shadow-sm"
            style={{ background: primaryColor, borderRadius }}
          >
            {company.name.charAt(0).toUpperCase()}
          </div>
        )}

        <div>
          <h1 className="text-xl font-semibold tracking-tight">{company.headline || company.name}</h1>
          {company.bio && <p className="mt-2 text-sm text-slate-600">{company.bio}</p>}
        </div>

        <div className="space-y-3">
          {company.storefrontEnabled && company.storeSlug && (
            <a
              href={`/s/${company.storeSlug}`}
              className="flex items-center justify-center gap-2 border px-4 py-3 text-sm font-medium text-white transition hover:opacity-90 shadow-xs"
              style={{
                background: primaryColor,
                borderColor: primaryColor,
                borderRadius,
              }}
            >
              <Store className="h-4 w-4" /> Shop {company.name}
            </a>
          )}

          {waHref && (
            <a
              href={waHref}
              target="_blank"
              rel="noreferrer"
              className="flex items-center justify-center gap-2 border border-emerald-500 px-4 py-3 text-sm font-medium text-emerald-600 transition hover:bg-emerald-50 shadow-xs"
              style={{ borderRadius }}
            >
              <MessageCircle className="h-4 w-4" /> Chat on WhatsApp
            </a>
          )}

          {company.links.map((link, idx) => (
            <a
              key={idx}
              href={link.url}
              target="_blank"
              rel="noreferrer"
              className="flex items-center justify-center border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-400 shadow-xs"
              style={{ borderRadius }}
            >
              {link.label}
            </a>
          ))}
        </div>

        <p className="pt-6 text-xs text-slate-400">
          {company.theme?.footer_text || `Powered by ${company.name}`}
        </p>
      </div>
    </div>
    </>
  )
}

