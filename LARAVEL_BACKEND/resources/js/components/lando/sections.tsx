import Link from "next/link"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

interface LandoHeroProps {
  kicker?: string
  title: string
  description?: string
  primaryCtaText?: string
  primaryCtaHref?: string
  secondaryCtaText?: string
  secondaryCtaHref?: string
