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
