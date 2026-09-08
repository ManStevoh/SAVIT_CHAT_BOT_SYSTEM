'use client'

import { Link } from '@inertiajs/react'
import { ArrowLeft } from 'lucide-react'
import { resolveStorefrontStyle, type BrandTheme } from '@/lib/theme-utils'

type Props = {
  slug: string
  company: { name: string; theme?: BrandTheme }
  title: string
  body: string
  isDefault?: boolean
}

export default function StoreTermsPage({ slug, company, title, body, isDefault = false }: Props) {
  const style = resolveStorefrontStyle(company.theme)

  return (
    <div className="min-h-screen bg-slate-50/80 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100" style={style}>
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
        <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{title}</h1>
        {isDefault ? (
          <p className="mt-2 text-xs text-slate-500">
            This store has not published custom terms yet. The text below is a default purchase agreement.
          </p>
        ) : null}
        <div className="mt-6 whitespace-pre-wrap rounded-3xl border border-slate-200/80 bg-white p-6 text-sm leading-relaxed text-slate-700 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
          {body}
        </div>
      </main>
    </div>
  )
}
