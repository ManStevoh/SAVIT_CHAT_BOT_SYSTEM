"use client"

import { Head } from "@inertiajs/react"
import { LegalLayout } from "./legal-layout"
import { useCmsPage } from "@/lib/api-hooks"

interface LegalCmsPageProps {
  slug: string
  fallbackTitle: string
  fallbackBody: React.ReactNode
}

export function LegalCmsPage({ slug, fallbackTitle, fallbackBody }: LegalCmsPageProps) {
  const { data, isLoading } = useCmsPage(slug)

  const section = data?.sections?.find((s) => s.key === "legal_content" && s.isEnabled)
  const content = (section?.content ?? {}) as {
    title?: string
    lastUpdated?: string
    body?: string
  }

  const title = content.title || fallbackTitle
  const metaTitle = data?.page.metaTitle || `${title} — Essem Chat`
  const hasCmsBody = typeof content.body === "string" && content.body.trim().length > 0

  return (
    <>
      <Head title={metaTitle} />
      <LegalLayout title={title}>
        {content.lastUpdated && (
          <p className="text-sm leading-relaxed">
            <strong>Last updated:</strong> {content.lastUpdated}
          </p>
        )}

        {isLoading && !hasCmsBody ? (
          <p className="text-sm text-gray-500">Loading…</p>
        ) : hasCmsBody ? (
          <div dangerouslySetInnerHTML={{ __html: content.body as string }} />
        ) : (
          fallbackBody
