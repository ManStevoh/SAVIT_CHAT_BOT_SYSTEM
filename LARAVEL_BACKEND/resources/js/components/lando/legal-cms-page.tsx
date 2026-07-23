"use client"

import { LegalLayout } from "./legal-layout"
import { useCmsPage } from "@/lib/api-hooks"
import { SeoHead, buildSeoFromCmsPage, type SeoPayload } from "@/components/seo/SeoHead"

interface LegalCmsPageProps {
  slug: string
  fallbackTitle: string
  fallbackBody: React.ReactNode
  initialSeo?: SeoPayload | null
}

export function LegalCmsPage({ slug, fallbackTitle, fallbackBody, initialSeo }: LegalCmsPageProps) {
  const { data, isLoading } = useCmsPage(slug)

  const section = data?.sections?.find((s) => s.key === "legal_content" && s.isEnabled)
  const content = (section?.content ?? {}) as {
    title?: string
    lastUpdated?: string
    body?: string
  }

  const title = content.title || fallbackTitle
  const seo = buildSeoFromCmsPage(
    data?.page,
    initialSeo,
    data?.page?.metaTitle || `${title} — RelayIQ`
  )
  const hasCmsBody = typeof content.body === "string" && content.body.trim().length > 0

  return (
    <>
      <SeoHead seo={seo} fallbackTitle={`${title} — RelayIQ`} />
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
        )}
      </LegalLayout>
    </>
  )
}
