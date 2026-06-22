"use client"

import { Card, CardContent } from "@/components/ui/card"
import { PartyPopper } from "lucide-react"

type Props = {
  message: string
  show: boolean
}

export function GrowthCelebrationBanner({ message, show }: Props) {
  if (!show) return null

  return (
    <Card className="border-amber-500/40 bg-gradient-to-r from-amber-500/10 to-primary/10">
      <CardContent className="flex items-center gap-3 py-4">
        <PartyPopper className="h-8 w-8 text-amber-600 shrink-0" />
        <div>
          <p className="font-semibold text-foreground">First attributed sale!</p>
          <p className="text-sm text-muted-foreground">{message}</p>
        </div>
      </CardContent>
    </Card>
  )
}
