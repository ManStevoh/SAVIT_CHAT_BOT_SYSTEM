import { Star } from "lucide-react"
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion"
import { Button } from "@/components/ui/button"
import Link from "next/link"
import { usePlans } from "@/lib/api-hooks"
import { Check } from "lucide-react"
import { cn } from "@/lib/utils"
import { useState, useEffect } from "react"
import { createCheckoutSession } from "@/lib/api-actions"
import { getAuthToken } from "@/lib/api-client"
import { toast } from "sonner"

export function LandoTrustedCompanies({
  title,
  companies = [],
}: {
  title?: string
  companies?: Array<{ name: string; logoUrl?: string } | string>
}) {
  const parsed = companies.map((c) =>
    typeof c === "string" ? { name: c, logoUrl: "" } : c
  )

  return (
    <section className="bg-[#f3f4f6] py-12">
