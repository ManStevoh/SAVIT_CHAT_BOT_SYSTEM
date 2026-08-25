"use client"

import { useEffect, useRef, useState, type ReactNode, type CSSProperties } from "react"
import { cn } from "@/lib/utils"

type RevealProps = {
  children: ReactNode
  className?: string
  /** Stagger delay in ms once visible */
  delayMs?: number
  /** Skip animation (e.g. for SSR-critical hero text if preferred) */
  disabled?: boolean
}

/**
 * Fade/slide-up when the element enters the viewport.
 * Respects prefers-reduced-motion.
 */
export function Reveal({ children, className, delayMs = 0, disabled = false }: RevealProps) {
  const ref = useRef<HTMLDivElement>(null)
  const [visible, setVisible] = useState(disabled)
  const [reduceMotion, setReduceMotion] = useState(false)

  useEffect(() => {
    if (disabled) return
    const mq = window.matchMedia("(prefers-reduced-motion: reduce)")
    const sync = () => setReduceMotion(mq.matches)
    sync()
    mq.addEventListener?.("change", sync)
    return () => mq.removeEventListener?.("change", sync)
  }, [disabled])

  useEffect(() => {
    if (disabled || reduceMotion) {
      setVisible(true)
      return
    }
    const el = ref.current
    if (!el) return

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry?.isIntersecting) {
          setVisible(true)
          observer.disconnect()
        }
      },
      { threshold: 0.12, rootMargin: "0px 0px -8% 0px" }
    )
    observer.observe(el)
    return () => observer.disconnect()
  }, [disabled, reduceMotion])

  const style: CSSProperties | undefined =
    !reduceMotion && !disabled
      ? {
          transitionDelay: visible ? `${delayMs}ms` : "0ms",
        }
      : undefined

  return (
    <div
      ref={ref}
      style={style}
      className={cn(
        !disabled &&
          !reduceMotion &&
          "translate-y-5 opacity-0 transition-[opacity,transform] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] will-change-transform",
        visible && "translate-y-0 opacity-100",
        className
      )}
    >
      {children}
    </div>
  )
}
