"use client"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { CheckCircle2, Circle, Rocket } from "lucide-react"
import { Progress } from "@/components/ui/progress"

export type OnboardingStep = {
  key: string
  label: string
  description: string
  completed: boolean
  actionTab?: string
}

type Props = {
  steps: OnboardingStep[]
  percentComplete: number
  isComplete: boolean
  onGoToTab: (tab: string) => void
  demoMode?: boolean
}

export function GrowthPilotChecklist({ steps, percentComplete, isComplete, onGoToTab, demoMode }: Props) {
  if (isComplete) return null

  return (
    <Card className="border-primary/30 bg-primary/5">
      <CardHeader className="pb-3">
        <CardTitle className="text-lg flex items-center gap-2">
          <Rocket className="h-5 w-5 text-primary" />
          Pilot onboarding — prove attribution in week one
        </CardTitle>
        <CardDescription>
          Complete these steps to see clicks, leads, and revenue tied to your posts.
          {demoMode && " Sample data is shown until your first real event."}
        </CardDescription>
        <Progress value={percentComplete} className="h-2 mt-2" />
        <p className="text-xs text-muted-foreground">{percentComplete}% complete</p>
      </CardHeader>
      <CardContent className="space-y-3">
        {steps.map((step) => (
          <div
            key={step.key}
            className="flex items-start gap-3 rounded-lg border bg-background/80 p-3"
          >
            {step.completed ? (
              <CheckCircle2 className="h-5 w-5 text-primary shrink-0 mt-0.5" />
            ) : (
              <Circle className="h-5 w-5 text-muted-foreground shrink-0 mt-0.5" />
            )}
            <div className="flex-1 min-w-0">
              <p className="font-medium text-sm">{step.label}</p>
              <p className="text-xs text-muted-foreground">{step.description}</p>
            </div>
            {!step.completed && step.actionTab && (
              <Button size="sm" variant="outline" onClick={() => onGoToTab(step.actionTab!)}>
                Start
              </Button>
            )}
          </div>
        ))}
      </CardContent>
    </Card>
  )
}
