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
  }
  sections: CmsSection[]
  testimonials?: Array<{
    id: string
    name: string
