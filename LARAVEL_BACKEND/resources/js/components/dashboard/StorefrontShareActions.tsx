"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Check, Copy, Share2 } from "lucide-react"
import { markStorefrontLinkShared } from "@/lib/api-actions"

interface Props {
  url: string
  companyName?: string
  onShared?: () => void
}

export function StorefrontShareActions({ url, companyName, onShared }: Props) {
  const [copied, setCopied] = useState(false)

  const rememberShare = () => {
    void markStorefrontLinkShared()
    onShared?.()
  }

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(url)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
      rememberShare()
    } catch {
      /* ignore */
    }
  }

  const share = async () => {
    const title = companyName ? `${companyName} shop` : "My shop"
    const text = companyName ? `Shop with ${companyName}` : "Shop with us"
    if (typeof navigator !== "undefined" && navigator.share) {
      try {
        await navigator.share({ title, text, url })
        rememberShare()
        return
      } catch (error) {
        if (error instanceof Error && error.name === "AbortError") {
          return
        }
      }
    }
    await copy()
  }

  return (
    <div className="flex shrink-0 items-center gap-2">
      <Button type="button" variant="outline" size="sm" className="h-8 gap-1" onClick={() => void copy()}>
        {copied ? <Check className="h-3.5 w-3.5 text-emerald-600" /> : <Copy className="h-3.5 w-3.5" />}
        {copied ? "Copied" : "Copy link"}
      </Button>
      <Button type="button" variant="outline" size="sm" className="h-8 gap-1" onClick={() => void share()}>
        <Share2 className="h-3.5 w-3.5" />
        Share
      </Button>
    </div>
  )
}
