'use client'

import { useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { AlertCircle } from 'lucide-react'

export default function DashboardError({
  error,
  reset,
}: {
  error: Error & { digest?: string }
  reset: () => void
}) {
  useEffect(() => {
    console.error('Dashboard error:', error)
  }, [error])

  return (
    <div className="flex min-h-[60vh] items-center justify-center p-6">
      <Card className="w-full max-w-md border-destructive/50 bg-card">
        <CardContent className="pt-6">
          <div className="flex flex-col items-center gap-4 text-center">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10">
              <AlertCircle className="h-6 w-6 text-destructive" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-foreground">Something went wrong</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                The dashboard could not load. This may be due to a connection issue or missing data.
              </p>
            </div>
            <div className="flex gap-2">
              <Button onClick={reset}>Try again</Button>
              <Button variant="outline" asChild>
                <a href="/dashboard">Back to dashboard</a>
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
