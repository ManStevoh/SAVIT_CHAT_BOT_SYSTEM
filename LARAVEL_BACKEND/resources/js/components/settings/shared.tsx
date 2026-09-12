"use client"

import type { ReactNode } from "react"
import Link from "next/link"
import { ArrowRight, Check, Loader2 } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { cn } from "@/lib/utils"

/** Masked secrets come back from GET /api/company/settings as ••••… */
export function isMasked(val: unknown): boolean {
  return typeof val === "string" && val.startsWith("••••")
}

/** Page-level section wrapper: number-free, calm hierarchy. */
export function SettingSection({
  title,
  description,
  badge,
  actions,
  children,
  className,
}: {
  title: string
  description?: string
  badge?: ReactNode
  actions?: ReactNode
  children: ReactNode
  className?: string
}) {
  return (
    <Card className={className}>
      <CardHeader className="pb-4">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <CardTitle className="text-base">{title}</CardTitle>
              {badge}
            </div>
            {description && <CardDescription>{description}</CardDescription>}
          </div>
          {actions}
        </div>
      </CardHeader>
      <CardContent className="space-y-5">{children}</CardContent>
    </Card>
  )
}

/** Label + hint on the left, control on the right. Stacks on mobile. */
export function SettingRow({
  label,
  hint,
  control,
  className,
}: {
  label: string
  hint?: string
  control: ReactNode
  className?: string
}) {
  return (
    <div className={cn("flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-6", className)}>
      <div className="min-w-0">
        <p className="text-sm font-medium text-foreground">{label}</p>
        {hint && <p className="mt-0.5 text-[13px] text-muted-foreground">{hint}</p>}
      </div>
      <div className="shrink-0 sm:min-w-[220px] sm:max-w-[320px] sm:text-right">{control}</div>
    </div>
  )
}

/** Sticky-ish save bar used at the bottom of each persisted section. */
export function SaveBar({
  saving,
  saved,
  error,
  onSave,
  label = "Save changes",
  disabled,
}: {
  saving: boolean
  saved: boolean
  error: string | null
  onSave: () => void
  label?: string
  disabled?: boolean
}) {
  return (
    <div className="space-y-2 border-t border-border pt-4">
      {error && (
        <p className="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-[13px] text-destructive">
          {error}
        </p>
      )}
      <div className="flex items-center gap-3">
        <Button onClick={onSave} disabled={saving || disabled} size="sm">
          {saving && <Loader2 className="mr-2 h-3.5 w-3.5 animate-spin" />}
          {saving ? "Saving…" : label}
        </Button>
        {saved && (
          <span className="inline-flex items-center gap-1 text-[13px] font-medium text-emerald-600 dark:text-emerald-400">
            <Check className="h-3.5 w-3.5" /> Saved
          </span>
        )}
      </div>
    </div>
  )
}

/** Small "needs Growth" inline link used next to gated rows. */
export function GrowthLink({ label = "Growth" }: { label?: string }) {
  return (
    <Link
      href="/dashboard/subscription#plans"
      className="inline-flex items-center gap-1 text-[13px] font-medium text-primary hover:underline"
    >
      {label}
      <ArrowRight className="h-3 w-3" />
    </Link>
  )
}

export function GrowthBadge() {
  return (
    <Badge variant="outline" className="border-primary/30 text-[11px] text-primary">
      Growth
    </Badge>
  )
}
