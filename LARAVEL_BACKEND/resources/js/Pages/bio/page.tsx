'use client'

import { MessageCircle, Store } from 'lucide-react'

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
  }
}

export default function LinkInBioPage({ company }: Props) {
  const waHref = company.whatsappNumber
    ? `https://wa.me/${company.whatsappNumber.replace(/\D/g, '')}`
    : null

  return (
    <div className="flex min-h-screen justify-center bg-gradient-to-b from-slate-50 to-white px-4 py-12">
      <div className="w-full max-w-md space-y-6 text-center">
        {company.logo ? (
          <img src={company.logo} alt={company.name} className="mx-auto h-20 w-20 rounded-full object-cover shadow-sm" />
        ) : (
          <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-slate-900 text-2xl font-semibold text-white">
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
              className="flex items-center justify-center gap-2 rounded-full border border-slate-900 bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800"
            >
              <Store className="h-4 w-4" /> Shop {company.name}
            </a>
          )}

          {waHref && (
            <a
              href={waHref}
              target="_blank"
              rel="noreferrer"
              className="flex items-center justify-center gap-2 rounded-full border border-emerald-500 px-4 py-3 text-sm font-medium text-emerald-600 transition hover:bg-emerald-50"
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
              className="flex items-center justify-center rounded-full border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-400"
            >
              {link.label}
            </a>
          ))}
        </div>

        <p className="pt-6 text-xs text-slate-400">Powered by SAVIT</p>
      </div>
    </div>
  )
}
