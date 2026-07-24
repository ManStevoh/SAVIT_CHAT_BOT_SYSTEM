export interface CmsLink {
  label: string
  href: string
}

export interface CmsSection {
  key: string
  label: string
  isEnabled: boolean
  sortOrder: number
  content: Record<string, unknown>
}

export interface CmsPageData {
  page: {
    slug: string
    title: string
    metaTitle?: string | null
    metaDescription?: string | null
    ogImage?: string | null
    ogTitle?: string | null
    ogDescription?: string | null
    canonicalUrl?: string | null
    robots?: string | null
  }
  sections: CmsSection[]
  testimonials?: Array<{
    id: string
    name: string
    role: string
    content: string
    rating: number
  }>
  faqs?: Array<{ id: string; question: string; answer: string }>
  trustedCompanies?: string[] | Array<{ name: string; logoUrl?: string }>
}

export interface CmsGlobalData {
  page: CmsPageData['page']
  sections: CmsSection[]
}

export interface AdminCmsSection extends CmsSection {
  id: string
}

export interface AdminCmsPage {
  page: {
    id: string
    slug: string
    title: string
    metaTitle?: string | null
    metaDescription?: string | null
    ogImage?: string | null
    ogTitle?: string | null
    ogDescription?: string | null
    canonicalUrl?: string | null
    robots?: string | null
    isPublished: boolean
  }
  sections: AdminCmsSection[]
}
