"use client"

import { useEffect, useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { MessageSquare, RefreshCw } from "lucide-react"
import { getAdminWhatsAppConnections, type AdminWhatsAppConnection } from "@/lib/api-actions"

export default function AdminWhatsAppPage() {
  const [connections, setConnections] = useState<AdminWhatsAppConnection[]>([])
  const [platform, setPlatform] = useState<{ embeddedSignupEnabled: boolean; webhookUrl: string; graphVersion: string } | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getAdminWhatsAppConnections()
      setConnections(res.connections)
      setPlatform(res.platform)
    } catch {
      setError("Failed to load WhatsApp connections.")
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-foreground">WhatsApp Connections</h1>
          <p className="text-muted-foreground">Monitor company WhatsApp onboarding across the platform</p>
        </div>
        <Button variant="outline" onClick={load} disabled={loading}>
          <RefreshCw className={`h-4 w-4 mr-2 ${loading ? "animate-spin" : ""}`} />
          Refresh
        </Button>
      </div>

      {platform && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Platform Meta configuration</CardTitle>
          </CardHeader>
          <CardContent className="text-sm space-y-2 text-muted-foreground">
            <p>Embedded Signup: <strong className="text-foreground">{platform.embeddedSignupEnabled ? "Ready" : "Not configured"}</strong></p>
            <p>Graph API: {platform.graphVersion}</p>
            <p className="break-all">Webhook: <code className="text-xs bg-muted px-1 rounded">{platform.webhookUrl}</code></p>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <MessageSquare className="h-5 w-5" />
            Company connections
          </CardTitle>
          <CardDescription>{connections.length} record(s)</CardDescription>
        </CardHeader>
        <CardContent>
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : error ? (
            <p className="text-sm text-destructive">{error}</p>
          ) : connections.length === 0 ? (
            <p className="text-sm text-muted-foreground">No companies have connected WhatsApp yet.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Company</TableHead>
                  <TableHead>Phone</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Onboarding</TableHead>
                  <TableHead>Connected</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {connections.map((c) => (
                  <TableRow key={c.id}>
                    <TableCell>
                      <div className="font-medium">{c.companyName}</div>
                      <div className="text-xs text-muted-foreground">{c.companyEmail}</div>
                    </TableCell>
                    <TableCell>{c.displayPhoneNumber ?? "—"}</TableCell>
                    <TableCell>
                      <Badge variant={c.status === "active" ? "default" : "secondary"}>{c.status}</Badge>
                    </TableCell>
                    <TableCell>
                      <div>{c.onboardingStatus ?? "—"}</div>
                      {c.onboardingError && (
                        <div className="text-xs text-destructive truncate max-w-xs" title={c.onboardingError}>{c.onboardingError}</div>
                      )}
                    </TableCell>
                    <TableCell className="text-xs text-muted-foreground">
                      {c.connectedAt ? new Date(c.connectedAt).toLocaleString() : "—"}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
