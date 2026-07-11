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
