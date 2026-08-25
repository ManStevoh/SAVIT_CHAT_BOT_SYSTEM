"use client"

import { useEffect, useId, useRef, useState } from "react"
import { useAppBranding } from "@/components/providers/AppBrandingProvider"

declare global {
  interface Window {
    grecaptcha?: {
      ready?: (cb: () => void) => void
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
      if (window.grecaptcha?.render) {
        resolve()
        return
      }
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

function waitForGrecaptcha(): Promise<NonNullable<Window["grecaptcha"]>> {
  return new Promise((resolve, reject) => {
    const start = Date.now()
    const tick = () => {
      if (window.grecaptcha?.render) {
        const g = window.grecaptcha
        if (typeof g.ready === "function") {
          g.ready(() => resolve(g))
        } else {
          resolve(g)
        }
        return
      }
      if (Date.now() - start > 10000) {
        reject(new Error("reCAPTCHA timed out"))
        return
      }
      window.setTimeout(tick, 50)
    }
    tick()
  })
}

type RecaptchaWidgetProps = {
  onChange: (token: string | null) => void
  className?: string
}

export function RecaptchaWidget({ onChange, className }: RecaptchaWidgetProps) {
  const branding = useAppBranding()
  const containerRef = useRef<HTMLDivElement | null>(null)
  const widgetIdRef = useRef<number | null>(null)
  const onChangeRef = useRef(onChange)
  const reactId = useId()
  const [loadError, setLoadError] = useState<string | null>(null)

  onChangeRef.current = onChange

  const enabled = Boolean(branding.recaptchaEnabled && branding.recaptchaSiteKey)

  useEffect(() => {
    if (!enabled || !branding.recaptchaSiteKey || !containerRef.current) {
      onChangeRef.current(null)
      return
    }

    let cancelled = false
    setLoadError(null)

    loadRecaptchaScript()
      .then(() => waitForGrecaptcha())
      .then((grecaptcha) => {
        if (cancelled || !containerRef.current) return
        if (widgetIdRef.current !== null) {
          try {
            grecaptcha.reset(widgetIdRef.current)
          } catch {
            /* ignore */
          }
          return
        }
        containerRef.current.innerHTML = ""
        widgetIdRef.current = grecaptcha.render(containerRef.current, {
          sitekey: branding.recaptchaSiteKey!,
          callback: (token: string) => onChangeRef.current(token),
          "expired-callback": () => onChangeRef.current(null),
        })
      })
      .catch(() => {
        if (cancelled) return
        onChangeRef.current(null)
        setLoadError(
          "Captcha could not load. Check that this domain is allowed on your Google reCAPTCHA site key, disable blockers for google.com, then refresh."
        )
      })

    return () => {
      cancelled = true
    }
  }, [enabled, branding.recaptchaSiteKey, reactId])

  if (!enabled) return null

  return (
    <div className={className}>
      <div ref={containerRef} data-recaptcha-widget={reactId} />
      {loadError ? (
        <p className="mt-2 text-sm text-destructive" role="alert">
          {loadError}
        </p>
      ) : null}
    </div>
  )
}

export function resetRecaptchaWidget(): void {
  try {
    window.grecaptcha?.reset()
  } catch {
    /* ignore */
  }
}
