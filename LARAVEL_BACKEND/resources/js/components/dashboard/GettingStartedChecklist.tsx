"use client"

import Link from "next/link"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Progress } from "@/components/ui/progress"
import { CheckCircle2, ListChecks, X } from "lucide-react"
import { useSetupStatus, SetupStatus } from "@/lib/api-hooks"
import { dismissSetupChecklist, restoreSetupChecklist } from "@/lib/api-actions"
import { useState } from "react"
import { useSWRConfig } from "swr"
import { toast } from "sonner"

interface Props {
  /** Pre-seeded data from the dashboard-summary combined fetch. When provided,
   *  the component skips its own network request. */
  initialData?: SetupStatus
}

export function GettingStartedChecklist({ initialData }: Props) {
  const { data, isLoading, mutate } = useSetupStatus(initialData)
  const { mutate: globalMutate } = useSWRConfig()
  const [dismissing, setDismissing] = useState(false)
  const [restoring, setRestoring] = useState(false)
  // Local override: the hook's mutate is bound to a null key when initialData
  // is seeded, so dismiss/restore must also flip local state to take effect.
  const [localDismissed, setLocalDismissed] = useState<boolean | null>(null)

  if (isLoading || !data) return null

  const dismissed = localDismissed ?? data.dismissed
  if (data.isComplete) return null

  // Tiny footprint when dismissed: one muted line that brings it back.
  if (dismissed) {
    return (
      <div className="flex items-center justify-center gap-1.5 text-xs text-muted-foreground">
        <span>Setup guide hidden.</span>
        <button
          type="button"
          className="font-medium text-primary underline-offset-2 hover:underline disabled:opacity-60"
          disabled={restoring}
          onClick={async () => {
            setRestoring(true)
            const result = await restoreSetupChecklist()
            setRestoring(false)
            if (result.success) {
              setLocalDismissed(false)
              await globalMutate("company-setup-status", data ? { ...data, dismissed: false } : undefined, false)
              mutate()
            } else {
              toast.error(result.message ?? "Could not bring back the guide.")
            }
          }}
        >
          {restoring ? "…" : "Show again"}
        </button>
      </div>
    )
  }

  const handleDismiss = async () => {
    setDismissing(true)
    const result = await dismissSetupChecklist()
    setDismissing(false)
    if (result.success) {
      setLocalDismissed(true)
      await globalMutate("company-setup-status", { ...data, dismissed: true }, false)
      mutate()
    } else {
      toast.error(result.message ?? "Could not dismiss checklist.")
    }
  }

  return (
    <Card className="border-primary/30 bg-primary/5">
      <CardHeader className="pb-3">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0 space-y-1">
            <CardTitle className="flex items-center gap-2 text-lg">
              <ListChecks className="h-5 w-5 text-primary" />
              Getting started
            </CardTitle>
            <CardDescription>
              Complete these steps to go live. You can finish them in any order.
            </CardDescription>
          </div>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="shrink-0 text-muted-foreground hover:text-foreground"
            disabled={dismissing}
            onClick={handleDismiss}
          >
            <X className="h-4 w-4" />
            <span className="sr-only">Dismiss</span>
          </Button>
        </div>
        <Progress value={data.percent} className="mt-3 h-2" />
        <p className="text-xs text-muted-foreground">
          {data.completedCount} of {data.totalCount} complete ({data.percent}%)
        </p>
      </CardHeader>
      <CardContent className="space-y-3">
        {data.steps.map((step, i) => (
          <div
            key={step.id}
            className="flex items-start gap-3 rounded-lg border bg-background/80 p-3"
          >
            {step.done ? (
              <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-primary" />
            ) : (
              <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-bold text-muted-foreground">
                {i + 1}
              </span>
            )}
            <div className="min-w-0 flex-1">
              <p className="text-sm font-medium">{step.title}</p>
              <p className="text-xs text-muted-foreground">{step.description}</p>
            </div>
            {!step.done && (
              <Button asChild size="sm" variant="outline" className="shrink-0">
                <Link href={step.href}>Do it</Link>
              </Button>
            )}
          </div>
        ))}
        <p className="pt-1 text-center text-xs text-muted-foreground">
          Prefer to explore on your own?{" "}
          <button
            type="button"
            className="font-medium text-primary underline-offset-2 hover:underline"
            disabled={dismissing}
            onClick={handleDismiss}
          >
            Dismiss checklist
          </button>
        </p>
      </CardContent>
    </Card>
  )
}
