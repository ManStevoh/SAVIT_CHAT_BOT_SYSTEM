'use client'

import { Link } from '@inertiajs/react'
import { ArrowLeft, MessageCircle } from 'lucide-react'
import { SeoHead, type SeoPayload } from '@/components/seo/SeoHead'
import { StorefrontFooter } from '@/components/store/StorefrontFooter'
import { resolveStorefrontStyle, type BrandTheme } from '@/lib/theme-utils'

type Props = {
  slug: string
  company: {
    name: string
    logo?: string | null
    whatsappUrl?: string | null
    instagramUrl?: string | null
    facebookUrl?: string | null
    tiktokUrl?: string | null
    aboutUrl?: string
    termsUrl?: string
    theme?: BrandTheme
  }
  title: string
  body: string
  isDefault?: boolean
  seo?: SeoPayload | null
}

export default function StoreAboutPage({ slug, company, title, body, isDefault = false, seo }: Props) {
  const style = resolveStorefrontStyle(company.theme)

  return (
    <div className="min-h-screen bg-slate-50/80 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100" style={style}>
      <SeoHead seo={seo} fallbackTitle={`${title} — ${company.name}`} />
      <header className="border-b border-slate-200/80 bg-white/90 dark:border-slate-800 dark:bg-slate-900/90">
        <div className="mx-auto flex max-w-3xl items-center justify-between px-4 py-3.5">
          <Link
            href={`/s/${slug}`}
            className="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
          >
            <ArrowLeft className="h-4 w-4" /> Back to store
          </Link>
          <span className="text-xs font-bold uppercase tracking-wider text-slate-400">{company.name}</span>
        </div>
      </header>
      <main className="mx-auto max-w-3xl px-4 py-10">
        <div className="flex items-center gap-3">
          {company.logo ? (
            <div className="flex h-12 shrink-0 items-center justify-center overflow-hidden rounded-xl">
              <img
                src={company.logo}
                alt={company.name}
                className="h-12 w-auto max-w-[180px] object-contain"
              />
            </div>
          ) : null}
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{title}</h1>
        </div>
        {isDefault ? (
          <p className="mt-2 text-xs text-slate-500">
            This store has not published a custom about page yet. The merchant can add their story in Storefront settings.
          </p>
        ) : null}
        <div className="mt-6 whitespace-pre-wrap rounded-3xl border border-slate-200/80 bg-white p-6 text-sm leading-relaxed text-slate-700 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
          {body}
        </div>
        {company.whatsappUrl ? (
          <a
            href={company.whatsappUrl}
            target="_blank"
            rel="noreferrer"
            className="mt-6 inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 text-xs font-bold text-white"
            style={{ background: '#128C7E' }}
          >
            <MessageCircle className="h-4 w-4" /> Chat on WhatsApp
          </a>
        ) : null}
      </main>
      <StorefrontFooter slug={slug} company={company} />
    </div>
  )
}
