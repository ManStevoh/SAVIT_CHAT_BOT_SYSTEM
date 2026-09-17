"use client"

import { useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  dismissMerchantMarketingPopup,
  getMerchantMarketingPopups,
  type MerchantMarketingPopup,
} from "@/lib/api-actions"

export function MerchantMarketingPopup() {
  const [queue, setQueue] = useState<MerchantMarketingPopup[]>([])
  const current = queue[0] ?? null

  useEffect(() => {
    let cancelled = false
    getMerchantMarketingPopups()
      .then((res) => {
        if (!cancelled && res?.popups?.length) {
          setQueue(res.popups)
        }
      })
      .catch(() => {
        // Not logged in as a merchant, or no popups.
      })
    return () => {
      cancelled = true
    }
  }, [])

  const dismiss = async () => {
    if (!current) return
    const id = current.id
    setQueue((prev) => prev.filter((p) => p.id !== id))
    await dismissMerchantMarketingPopup(id)
  }

  if (!current) {
    return null
  }

  return (
    <Dialog open onOpenChange={(open) => { if (!open) void dismiss() }}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{current.title}</DialogTitle>
          <DialogDescription className="whitespace-pre-wrap text-foreground">
            {current.body}
          </DialogDescription>
        </DialogHeader>
        <DialogFooter className="gap-2">
          <Button variant="outline" onClick={() => void dismiss()}>Dismiss</Button>
          {current.ctaUrl ? (
            <Button asChild onClick={() => void dismiss()}>
              <a href={current.ctaUrl}>{current.ctaLabel || "Open"}</a>
            </Button>
          ) : null}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
