"use client"

import { useState } from "react"
import { MessageCircle, Sparkles } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Textarea } from "@/components/ui/textarea"
import { respondOnboardingInterview, startOnboardingInterview } from "@/lib/api-actions"

type ChatMessage = { role: "assistant" | "user"; content: string }

export function OnboardingInterviewPanel({
  onComplete,
}: {
  onComplete?: () => void
}) {
  const [sessionId, setSessionId] = useState<string | null>(null)
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [input, setInput] = useState("")
  const [complete, setComplete] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const start = async () => {
    setBusy(true)
    setError(null)
    const result = await startOnboardingInterview()
    setBusy(false)
    if (!result.success || !result.sessionId) {
      setError(result.message ?? "Could not start interview")
      return
    }
    setSessionId(result.sessionId)
    setMessages([{ role: "assistant", content: result.message ?? "Tell me about your business." }])
    setComplete(false)
  }

  const send = async () => {
    if (!sessionId || !input.trim() || complete) return
    const userText = input.trim()
    setInput("")
    setMessages((prev) => [...prev, { role: "user", content: userText }])
    setBusy(true)
    setError(null)
    const result = await respondOnboardingInterview({ sessionId, message: userText })
    setBusy(false)
    if (!result.success) {
      setError(result.message ?? "Interview failed")
      return
    }
    setMessages((prev) => [...prev, { role: "assistant", content: result.message ?? "" }])
    if (result.complete) {
      setComplete(true)
      onComplete?.()
    }
  }

  return (
    <Card className="border-primary/20 bg-primary/5">
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Sparkles className="h-5 w-5" />
          AI business interview
        </CardTitle>
        <CardDescription>
          Answer a few questions — we&apos;ll auto-fill your Business DNA and Digital Twin.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {messages.length === 0 ? (
          <Button onClick={start} disabled={busy}>
            <MessageCircle className="h-4 w-4 mr-2" />
            Start interview
          </Button>
        ) : (
          <>
            <div className="space-y-2 max-h-64 overflow-y-auto rounded-md border bg-background p-3">
              {messages.map((m, i) => (
                <div
                  key={i}
                  className={`text-sm rounded-lg px-3 py-2 ${
                    m.role === "assistant"
                      ? "bg-muted text-foreground"
                      : "bg-primary/10 text-foreground ml-6"
                  }`}
                >
                  {m.content}
                </div>
              ))}
            </div>
            {complete ? (
              <Badge variant="default">Profile updated — review DNA &amp; Twin below, then Save.</Badge>
            ) : (
              <div className="flex gap-2">
                <Textarea
                  value={input}
                  onChange={(e) => setInput(e.target.value)}
                  placeholder="Describe your business…"
                  rows={2}
                  disabled={busy}
                  onKeyDown={(e) => {
                    if (e.key === "Enter" && !e.shiftKey) {
                      e.preventDefault()
                      send()
                    }
                  }}
                />
                <Button onClick={send} disabled={busy || !input.trim()} className="shrink-0">
                  Send
                </Button>
              </div>
            )}
          </>
        )}
        {error && <p className="text-sm text-destructive">{error}</p>}
      </CardContent>
    </Card>
  )
}
