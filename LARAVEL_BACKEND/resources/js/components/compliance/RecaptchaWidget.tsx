"use client"

import { useEffect, useId, useRef } from "react"
import { useAppBranding } from "@/components/providers/AppBrandingProvider"

declare global {
  interface Window {
    grecaptcha?: {
      render: (
        container: HTMLElement,
        parameters: { sitekey: string; callback?: (token: string) => void; "expired-callback"?: () => void }
      ) => number
      reset: (widgetId?: number) => void
      getResponse: (widgetId?: number) => string
    }
    ___grecaptcha_cfg?: unknown
  }
}

let scriptPromise: Promise<void> | null = null

function loadRecaptchaScript(): Promise<void> {
  if (typeof window === "undefined") return Promise.resolve()
  if (window.grecaptcha?.render) return Promise.resolve()
  if (scriptPromise) return scriptPromise

  scriptPromise = new Promise((resolve, reject) => {
    const existing = document.querySelector<HTMLScriptElement>('script[data-essem-recaptcha="1"]')
    if (existing) {
      existing.addEventListener("load", () => resolve())
      existing.addEventListener("error", () => reject(new Error("Failed to load reCAPTCHA")))
      return
    }
    const script = document.createElement("script")
    script.src = "https://www.google.com/recaptcha/api.js?render=explicit"
    script.async = true
    script.defer = true
    script.dataset.essemRecaptcha = "1"
    script.onload = () => resolve()
    script.onerror = () => reject(new Error("Failed to load reCAPTCHA"))
    document.head.appendChild(script)
  })

  return scriptPromise
}

type RecaptchaWidgetProps = {
  onChange: (token: string | null) => void
  className?: string
}

export function RecaptchaWidget({ onChange, className }: RecaptchaWidgetProps) {
  const branding = useAppBranding()
  const containerRef = useRef<HTMLDivElement | null>(null)
  const widgetIdRef = useRef<number | null>(null)
  const reactId = useId()

  const enabled = Boolean(branding.recaptchaEnabled && branding.recaptchaSiteKey)

  useEffect(() => {
    if (!enabled || !branding.recaptchaSiteKey || !containerRef.current) {
      onChange(null)
      return
    }

    let cancelled = false

    loadRecaptchaScript()
      .then(() => {
        if (cancelled || !containerRef.current || !window.grecaptcha) return
        if (widgetIdRef.current !== null) {
          window.grecaptcha.reset(widgetIdRef.current)
          return
        }
        containerRef.current.innerHTML = ""
        widgetIdRef.current = window.grecaptcha.render(containerRef.current, {
          sitekey: branding.recaptchaSiteKey!,
          callback: (token: string) => onChange(token),
          "expired-callback": () => onChange(null),
        })
      })
      .catch(() => {
        onChange(null)
      })

    return () => {
      cancelled = true
    }
  }, [enabled, branding.recaptchaSiteKey, onChange, reactId])

  if (!enabled) return null

  return <div ref={containerRef} className={className} data-recaptcha-widget={reactId} />
}

export function resetRecaptchaWidget(): void {
  try {
    window.grecaptcha?.reset()
  } catch {
    /* ignore */
  }
}
