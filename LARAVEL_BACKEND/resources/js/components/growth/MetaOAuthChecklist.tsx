"use client"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { ExternalLink } from "lucide-react"

const STEPS = [
  "Create a Meta Developer app and add Facebook Login + Instagram products",
  "Add OAuth redirect URL from your Growth → Platforms tab",
  "Connect Facebook, then select your Page (and Instagram if applicable)",
  "Submit for App Review with screen recordings of publish + attribution flow",
  "Add test users in Meta console until review is approved",
]

export function MetaOAuthChecklist() {
  return (
    <Card className="border-dashed">
      <CardHeader className="pb-2">
        <CardTitle className="text-base">Meta production checklist</CardTitle>
        <CardDescription>Required before live Facebook/Instagram publish for all customers</CardDescription>
      </CardHeader>
      <CardContent className="space-y-2 text-sm text-muted-foreground">
        <ol className="list-decimal list-inside space-y-1">
          {STEPS.map((step) => (
            <li key={step}>{step}</li>
          ))}
        </ol>
        <a
          href="https://developers.facebook.com/docs/development/create-an-app"
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-1 text-primary text-xs mt-2 hover:underline"
        >
          Meta Developer docs <ExternalLink className="h-3 w-3" />
        </a>
      </CardContent>
    </Card>
  )
}
