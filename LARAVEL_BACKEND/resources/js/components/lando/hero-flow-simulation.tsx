"use client"

import { useEffect, useRef, useState } from "react"
import { Check, CreditCard, ShoppingBag, Smartphone } from "lucide-react"
import { cn } from "@/lib/utils"

type FlowPhase = "chat" | "browse" | "order" | "pay" | "done"

type TimelineItem =
  | { id: string; kind: "customer"; text: string; delay: number }
  | { id: string; kind: "typing"; delay: number }
  | { id: string; kind: "ai"; text: string; delay: number }
  | {
      id: string
      kind: "products"
      delay: number
      items: Array<{ name: string; price: string; emoji: string }>
    }
  | {
      id: string
      kind: "order"
      delay: number
      orderNo: string
      lines: Array<{ name: string; qty: number; price: string }>
      total: string
    }
  | { id: string; kind: "payment"; delay: number; amount: string; method: string }
  | { id: string; kind: "success"; delay: number; text: string }

const TIMELINE: TimelineItem[] = [
  { id: "c1", kind: "customer", text: "Hi! Do you have the Air Runner sneakers?", delay: 600 },
  { id: "t1", kind: "typing", delay: 900 },
  { id: "a1", kind: "ai", text: "Yes! Here are our bestsellers — tap to order:", delay: 1200 },
  {
    id: "p1",
    kind: "products",
    delay: 1400,
    items: [
      { name: "Air Runner", price: "KES 4,500", emoji: "👟" },
      { name: "Classic Tee", price: "KES 1,200", emoji: "👕" },
    ],
  },
  { id: "c2", kind: "customer", text: "I'll take Air Runner, size 42", delay: 2200 },
  { id: "t2", kind: "typing", delay: 900 },
  { id: "a2", kind: "ai", text: "Perfect! Here's your order summary:", delay: 1100 },
  {
    id: "o1",
    kind: "order",
    delay: 1300,
    orderNo: "4821",
    lines: [{ name: "Air Runner (42)", qty: 1, price: "KES 4,500" }],
    total: "KES 4,500",
  },
  { id: "t3", kind: "typing", delay: 900 },
  { id: "a3", kind: "ai", text: "Pay with M-Pesa? I'll send an STK push to your phone.", delay: 1100 },
  { id: "pay1", kind: "payment", delay: 1500, amount: "KES 4,500", method: "M-Pesa STK Push" },
  { id: "t4", kind: "typing", delay: 1200 },
  {
    id: "s1",
    kind: "success",
    delay: 1000,
    text: "Payment received! Order #4821 confirmed. Delivery in 2 days.",
  },
]

const PHASES: Array<{ key: FlowPhase; label: string; afterId: string }> = [
  { key: "chat", label: "Chat", afterId: "" },
  { key: "browse", label: "Browse", afterId: "p1" },
  { key: "order", label: "Order", afterId: "o1" },
  { key: "pay", label: "Pay", afterId: "pay1" },
  { key: "done", label: "Done", afterId: "s1" },
]

function phaseForVisible(ids: Set<string>): FlowPhase {
  let phase: FlowPhase = "chat"
  for (const step of PHASES) {
    if (step.afterId && ids.has(step.afterId)) phase = step.key
  }
  return phase
}

function shouldShowTyping(item: TimelineItem, visible: Set<string>): boolean {
  if (item.kind !== "typing") return false
  const idx = TIMELINE.findIndex((t) => t.id === item.id)
  const next = TIMELINE[idx + 1]
  return visible.has(item.id) && (!next || !visible.has(next.id))
}

function TypingBubble() {
  return (
    <div className="flex justify-start animate-in fade-in slide-in-from-bottom-2 duration-300">
      <div
        className="flex gap-1 rounded-lg rounded-bl-sm px-4 py-3"
        style={{ backgroundColor: "var(--wa-bubble-in)" }}
      >
        {[0, 1, 2].map((i) => (
          <span
            key={i}
            className="h-2 w-2 rounded-full bg-gray-400 animate-bounce"
            style={{ animationDelay: `${i * 150}ms` }}
          />
        ))}
      </div>
    </div>
  )
}

function ChatBubble({ text, outgoing }: { text: string; outgoing?: boolean }) {
  return (
    <div
      className={cn(
        "flex animate-in fade-in slide-in-from-bottom-2 duration-300",
        outgoing ? "justify-end" : "justify-start"
      )}
    >
      <div
        className={cn(
          "max-w-[88%] rounded-lg px-3 py-2 text-[13px] leading-snug text-gray-900 shadow-sm",
          outgoing ? "rounded-br-sm" : "rounded-bl-sm"
        )}
        style={{
          backgroundColor: outgoing ? "var(--wa-bubble-out)" : "var(--wa-bubble-in)",
        }}
      >
        {text}
      </div>
    </div>
  )
}

function TimelineBlock({ item }: { item: TimelineItem }) {
  switch (item.kind) {
    case "customer":
      return <ChatBubble text={item.text} outgoing />
    case "typing":
      return <TypingBubble />
    case "ai":
      return <ChatBubble text={item.text} />
    case "products":
      return (
        <div className="flex justify-start animate-in fade-in slide-in-from-bottom-2 duration-300">
          <div className="w-full max-w-[92%] space-y-2">
            {item.items.map((product) => (
              <div
                key={product.name}
                className="flex items-center gap-3 rounded-xl border border-gray-200/80 bg-white p-2.5 shadow-sm"
              >
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-2xl">
                  {product.emoji}
                </div>
                <div className="min-w-0 flex-1">
                  <div className="truncate text-sm font-semibold text-gray-900">{product.name}</div>
                  <div className="text-xs font-medium text-[#128c7e]">{product.price}</div>
                </div>
                <span className="shrink-0 rounded-lg bg-[#128c7e] px-2.5 py-1 text-[10px] font-semibold text-white">
                  Order
                </span>
              </div>
            ))}
          </div>
        </div>
      )
    case "order":
      return (
        <div className="flex justify-start animate-in fade-in slide-in-from-bottom-2 duration-300">
          <div className="w-full max-w-[92%] overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-sm">
            <div className="flex items-center gap-2 border-b border-gray-100 px-3 py-2 text-xs font-semibold text-gray-800">
              <ShoppingBag className="h-3.5 w-3.5 text-[#128c7e]" />
              Order #{item.orderNo}
            </div>
            <div className="space-y-1 px-3 py-2.5 text-xs text-gray-600">
              {item.lines.map((line) => (
                <div key={line.name} className="flex justify-between gap-2">
                  <span>
                    {line.qty}× {line.name}
                  </span>
                  <span className="tabular-nums font-medium">{line.price}</span>
                </div>
              ))}
              <div className="mt-2 flex justify-between border-t border-gray-100 pt-2 font-semibold text-gray-900">
                <span>Total</span>
                <span className="tabular-nums">{item.total}</span>
              </div>
            </div>
          </div>
        </div>
      )
    case "payment":
      return (
        <div className="flex justify-start animate-in fade-in slide-in-from-bottom-2 duration-300">
          <div className="w-full max-w-[92%] rounded-xl border-2 border-[#128c7e]/30 bg-white p-3 shadow-sm">
            <div className="flex items-center gap-2 text-xs font-semibold text-gray-800">
              <CreditCard className="h-4 w-4 text-[#128c7e]" />
              {item.method}
            </div>
            <div className="mt-2 text-lg font-bold tabular-nums text-gray-900">{item.amount}</div>
            <div className="mt-2 h-2 overflow-hidden rounded-full bg-gray-100">
              <div className="h-full w-2/3 animate-pulse rounded-full bg-[#128c7e]" />
            </div>
            <p className="mt-2 text-[10px] text-gray-500">Confirm on your phone…</p>
          </div>
        </div>
      )
    case "success":
      return (
        <div className="flex justify-start animate-in fade-in slide-in-from-bottom-2 duration-300">
          <div
            className="max-w-[92%] rounded-lg rounded-bl-sm px-3 py-2.5 text-[13px] leading-snug text-gray-900 shadow-sm"
            style={{ backgroundColor: "var(--wa-bubble-in)" }}
          >
            <div className="mb-1 flex items-center gap-1.5 font-semibold text-[#128c7e]">
              <Check className="h-4 w-4" />
              Paid
            </div>
            {item.text}
          </div>
        </div>
      )
    default:
      return null
  }
}

export function LandoHeroFlowSimulation() {
  const [visible, setVisible] = useState<Set<string>>(new Set())
  const [cycle, setCycle] = useState(0)
  const scrollRef = useRef<HTMLDivElement>(null)
  const phase = phaseForVisible(visible)

  useEffect(() => {
    setVisible(new Set())
    const timers: ReturnType<typeof setTimeout>[] = []
    let elapsed = 400

    TIMELINE.forEach((item) => {
      elapsed += item.delay
      timers.push(
        setTimeout(() => {
          setVisible((prev) => new Set([...prev, item.id]))
        }, elapsed)
      )
    })

    timers.push(
      setTimeout(() => {
        setCycle((c) => c + 1)
      }, elapsed + 3500)
    )

    return () => timers.forEach(clearTimeout)
  }, [cycle])

  useEffect(() => {
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: "smooth" })
  }, [visible])

  return (
    <div className="mx-auto w-full max-w-[360px] lg:max-w-[400px]">
      <div className="relative rounded-[2.5rem] border-[10px] border-gray-900 bg-gray-900 p-1 shadow-2xl shadow-gray-900/20">
        <div className="absolute left-1/2 top-3 z-10 h-6 w-28 -translate-x-1/2 rounded-full bg-gray-900" />
        <div className="overflow-hidden rounded-[2rem] bg-white">
          <div
            className="flex items-center gap-3 px-4 pb-3 pt-10"
            style={{ backgroundColor: "var(--wa-header)" }}
          >
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-white/20 text-sm font-bold text-white">
              EC
            </div>
            <div className="min-w-0 flex-1">
              <div className="truncate text-sm font-semibold text-white">Essem Assistant</div>
              <div className="text-xs text-white/75">AI · online</div>
            </div>
            <Smartphone className="h-4 w-4 text-white/80" />
          </div>

          <div
            ref={scrollRef}
            className="h-[380px] space-y-2.5 overflow-y-auto p-3 scroll-smooth sm:h-[420px]"
            style={{ backgroundColor: "var(--wa-bg)" }}
          >
            {TIMELINE.map((item) => {
              if (!visible.has(item.id)) return null
              if (item.kind === "typing" && !shouldShowTyping(item, visible)) return null
              return <TimelineBlock key={item.id} item={item} />
            })}
          </div>
        </div>
      </div>

      <div className="mt-5 flex items-center justify-center gap-1.5 sm:gap-2">
        {PHASES.map((step, i) => {
          const active = PHASES.findIndex((p) => p.key === phase) >= i
          const current = step.key === phase
          return (
            <div key={step.key} className="flex items-center gap-1.5 sm:gap-2">
              <div
                className={cn(
                  "rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide transition-all duration-500 sm:px-3 sm:text-[11px]",
                  current
                    ? "bg-[#2563eb] text-white shadow-md"
                    : active
                      ? "bg-[#2563eb]/15 text-[#2563eb]"
                      : "bg-gray-200 text-gray-500"
                )}
              >
                {step.label}
              </div>
              {i < PHASES.length - 1 && (
                <div
                  className={cn(
                    "h-px w-4 transition-colors duration-500 sm:w-6",
                    active ? "bg-[#2563eb]/40" : "bg-gray-200"
                  )}
                />
              )}
            </div>
          )
        })}
      </div>
    </div>
  )
}
