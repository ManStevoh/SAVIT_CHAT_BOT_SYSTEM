import { Head } from "@inertiajs/react"

export type SeoPayload = {
  title?: string | null
  description?: string | null
  canonical?: string | null
  robots?: string | null
  ogTitle?: string | null
  ogDescription?: string | null
  ogImage?: string | null
  ogType?: string | null
  ogUrl?: string | null
  siteName?: string | null
  twitterCard?: string | null
  jsonLd?: Record<string, unknown> | null
}

type SeoHeadProps = {
  seo?: SeoPayload | null
  fallbackTitle?: string
}

export function SeoHead({ seo, fallbackTitle }: SeoHeadProps) {
  const title = seo?.title || fallbackTitle
  if (!title && !seo) return null

  return (
    <Head>
      {title ? <title>{title}</title> : null}
      {seo?.description ? <meta head-key="description" name="description" content={seo.description} /> : null}
      {seo?.robots ? <meta head-key="robots" name="robots" content={seo.robots} /> : null}
      {seo?.canonical ? <link head-key="canonical" rel="canonical" href={seo.canonical} /> : null}
      <meta head-key="og:type" property="og:type" content={seo?.ogType || "website"} />
      {seo?.siteName ? <meta head-key="og:site_name" property="og:site_name" content={seo.siteName} /> : null}
      {(seo?.ogTitle || title) ? (
        <meta head-key="og:title" property="og:title" content={seo?.ogTitle || title || ""} />
      ) : null}
      {seo?.ogDescription || seo?.description ? (
        <meta
          head-key="og:description"
          property="og:description"
          content={seo?.ogDescription || seo?.description || ""}
        />
      ) : null}
      {seo?.ogUrl ? <meta head-key="og:url" property="og:url" content={seo.ogUrl} /> : null}
      {seo?.ogImage ? <meta head-key="og:image" property="og:image" content={seo.ogImage} /> : null}
      <meta head-key="twitter:card" name="twitter:card" content={seo?.twitterCard || "summary_large_image"} />
      {(seo?.ogTitle || title) ? (
        <meta head-key="twitter:title" name="twitter:title" content={seo?.ogTitle || title || ""} />
      ) : null}
      {seo?.ogDescription || seo?.description ? (
        <meta
          head-key="twitter:description"
          name="twitter:description"
          content={seo?.ogDescription || seo?.description || ""}
        />
      ) : null}
      {seo?.ogImage ? <meta head-key="twitter:image" name="twitter:image" content={seo.ogImage} /> : null}
      {seo?.jsonLd ? (
        <script
          head-key="ld-json"
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(seo.jsonLd) }}
        />
      ) : null}
    </Head>
  )
}

export function buildSeoFromCmsPage(
  page?: {
    metaTitle?: string | null
    metaDescription?: string | null
    ogImage?: string | null
    ogTitle?: string | null
    ogDescription?: string | null
    canonicalUrl?: string | null
    robots?: string | null
    title?: string
    slug?: string
  } | null,
  initialSeo?: SeoPayload | null,
  fallbackTitle?: string
): SeoPayload {
  if (!page && initialSeo) return initialSeo

  const title = page?.metaTitle || page?.title || initialSeo?.title || fallbackTitle || ""
  const description = page?.metaDescription || initialSeo?.description || ""

  return {
    title,
    description,
    canonical: page?.canonicalUrl || initialSeo?.canonical || undefined,
    robots: page?.robots || initialSeo?.robots || "index, follow",
    ogTitle: page?.ogTitle || title,
    ogDescription: page?.ogDescription || description,
    ogImage: page?.ogImage || initialSeo?.ogImage || undefined,
    ogType: initialSeo?.ogType || "website",
    ogUrl: page?.canonicalUrl || initialSeo?.ogUrl || undefined,
    siteName: initialSeo?.siteName || "RelayIQ",
    twitterCard: initialSeo?.twitterCard || "summary_large_image",
    jsonLd: initialSeo?.jsonLd || null,
  }
}
