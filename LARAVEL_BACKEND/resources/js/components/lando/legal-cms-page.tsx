"use client"

import { Head } from "@inertiajs/react"
import { LegalLayout } from "./legal-layout"
import { useCmsPage } from "@/lib/api-hooks"

interface LegalCmsPageProps {
  slug: string
  fallbackTitle: string
  fallbackBody: React.ReactNode
}

