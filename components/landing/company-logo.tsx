"use client"

import { cn } from "@/lib/utils"

const BRAND_COLORS = [
  "bg-slate-800 text-white",
  "bg-indigo-700 text-white",
  "bg-teal-700 text-white",
  "bg-violet-700 text-white",
  "bg-rose-700 text-white",
  "bg-amber-800 text-white",
  "bg-sky-800 text-white",
  "bg-emerald-800 text-white",
]

function hashName(name: string): number {
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }
  return Math.abs(hash)
}

function getMonogram(name: string): string {
  const words = name.trim().split(/\s+/)
  if (words.length >= 2) {
    return (words[0][0] + words[1][0]).toUpperCase()
  }
  return name.slice(0, 2).toUpperCase()
}

export type CompanyBrand = string | { name: string; logoUrl?: string }

export function parseCompanyBrand(entry: CompanyBrand): { name: string; logoUrl?: string } {
  if (typeof entry === "string") return { name: entry }
  return entry
}

export function CompanyLogo({
  name,
  logoUrl,
  className,
}: {
  name: string
  logoUrl?: string
  className?: string
}) {
  const colorClass = BRAND_COLORS[hashName(name) % BRAND_COLORS.length]

  if (logoUrl) {
    return (
      <div
        className={cn(
          "flex h-8 items-center justify-center overflow-hidden rounded-md bg-white px-3 ring-1 ring-border/60",
          className
        )}
      >
        <img src={logoUrl} alt={name} className="h-5 max-w-[100px] object-contain" />
      </div>
    )
  }

  return (
    <div
      className={cn(
        "flex h-8 items-center gap-2 rounded-md px-2.5 ring-1 ring-border/40",
        className
      )}
      title={name}
    >
      <span
        className={cn(
          "flex h-5 w-5 shrink-0 items-center justify-center rounded text-[9px] font-bold tracking-tight",
          colorClass
        )}
      >
        {getMonogram(name)}
      </span>
      <span className="whitespace-nowrap text-sm font-medium tracking-tight text-muted-foreground/70">
        {name}
      </span>
    </div>
  )
}
