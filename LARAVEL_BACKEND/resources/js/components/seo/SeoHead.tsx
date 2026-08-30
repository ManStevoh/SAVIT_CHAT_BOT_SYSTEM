import { Head } from "@inertiajs/react"

export type SeoPayload = {
  title?: string | null
  description?: string | null
  canonical?: string | null
  robots?: string | null
  ogTitle?: string | null
  ogDescription?: string | null
  ogImage?: string | null
  ogImageWidth?: number | null
  ogImageHeight?: number | null
  ogLocale?: string | null
  ogType?: string | null
  ogUrl?: string | null
  siteName?: string | null
  twitterCard?: string | null
  twitterSite?: string | null
  sameAs?: string[] | null
  articlePublishedTime?: string | null
  articleModifiedTime?: string | null
  jsonLd?: Record<string, unknown> | null
  skipAppTitleSuffix?: boolean | null
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
      {(seo?.sameAs ?? []).filter(Boolean).map((url) => (
        <link key={url} head-key={`me-${url}`} rel="me" href={url} />
      ))}
      <meta head-key="og:type" property="og:type" content={seo?.ogType || "website"} />
      {seo?.siteName ? <meta head-key="og:site_name" property="og:site_name" content={seo.siteName} /> : null}
      {seo?.ogLocale ? <meta head-key="og:locale" property="og:locale" content={seo.ogLocale} /> : null}
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
      {seo?.ogImage && seo?.ogImageWidth ? (
        <meta head-key="og:image:width" property="og:image:width" content={String(seo.ogImageWidth)} />
      ) : null}
      {seo?.ogImage && seo?.ogImageHeight ? (
        <meta head-key="og:image:height" property="og:image:height" content={String(seo.ogImageHeight)} />
      ) : null}
      <meta head-key="twitter:card" name="twitter:card" content={seo?.twitterCard || "summary_large_image"} />
      {seo?.twitterSite ? <meta head-key="twitter:site" name="twitter:site" content={seo.twitterSite} /> : null}
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
      {seo?.articlePublishedTime ? (
        <meta head-key="article:published_time" property="article:published_time" content={seo.articlePublishedTime} />
      ) : null}
      {seo?.articleModifiedTime ? (
        <meta head-key="article:modified_time" property="article:modified_time" content={seo.articleModifiedTime} />
      ) : null}
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
    ogImageWidth: initialSeo?.ogImageWidth,
    ogImageHeight: initialSeo?.ogImageHeight,
    ogLocale: initialSeo?.ogLocale,
    ogType: initialSeo?.ogType || "website",
    ogUrl: page?.canonicalUrl || initialSeo?.ogUrl || undefined,
    siteName: initialSeo?.siteName || "RelayIQ",
    twitterCard: initialSeo?.twitterCard || "summary_large_image",
    twitterSite: initialSeo?.twitterSite,
    sameAs: initialSeo?.sameAs,
    articlePublishedTime: initialSeo?.articlePublishedTime,
    articleModifiedTime: initialSeo?.articleModifiedTime,
    jsonLd: initialSeo?.jsonLd || null,
    skipAppTitleSuffix: initialSeo?.skipAppTitleSuffix,
  }
}
