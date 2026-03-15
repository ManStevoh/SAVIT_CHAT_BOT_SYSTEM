"use client"

import { useState } from "react"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
  TooltipProvider,
} from "@/components/ui/tooltip"
import { Search, Filter, RefreshCw, Download, AlertCircle, Info, AlertTriangle, CheckCircle, Loader2 } from "lucide-react"
import { useAdminLogs } from "@/lib/api-hooks"
import { exportData } from "@/lib/api-actions"
import { downloadFile } from "@/lib/api-client"
import type { SystemLog } from "@/lib/mock-data"

const ADMIN_EXPORT_TYPES = [
  { value: "companies", label: "Companies" },
  { value: "users", label: "Users" },
  { value: "subscriptions", label: "Subscriptions" },
  { value: "revenue", label: "Revenue" },
  { value: "logs", label: "Logs" },
] as const
const EXPORT_FORMATS = [
  { value: "csv", label: "CSV" },
  { value: "json", label: "JSON" },
] as const

const getLevelIcon = (level: string) => {
  switch (level) {
    case "error": return <AlertCircle className="h-4 w-4" />
    case "warning": return <AlertTriangle className="h-4 w-4" />
    case "success": return <CheckCircle className="h-4 w-4" />
    default: return <Info className="h-4 w-4" />
  }
}

const getLevelVariant = (level: string): "default" | "secondary" | "destructive" | "outline" => {
  switch (level) {
    case "error": return "destructive"
    case "warning": return "secondary"
    case "success": return "default"
    default: return "outline"
  }
}

export default function AdminLogsPage() {
  const [searchQuery, setSearchQuery] = useState("")
  const [typeFilter, setTypeFilter] = useState("all")
  const [exportOpen, setExportOpen] = useState(false)
  const [exportDataType, setExportDataType] = useState<string>("logs")
  const [exportFormat, setExportFormat] = useState<string>("csv")
  const [exporting, setExporting] = useState(false)
  const [exportError, setExportError] = useState<string | null>(null)
  const { data: logs, error, isLoading, mutate } = useAdminLogs({
    type: typeFilter !== "all" ? typeFilter : undefined,
  })

  const filtered = (logs ?? []).filter((log) => {
    const q = searchQuery.toLowerCase()
    return (
      log.message.toLowerCase().includes(q) ||
      (log.details?.toLowerCase().includes(q)) ||
      log.source.toLowerCase().includes(q)
    )
  })

  const handleExport = async () => {
    setExportError(null)
    setExporting(true)
    try {
      const result = await exportData(
        exportDataType as "companies" | "users" | "subscriptions" | "revenue" | "logs",
        exportFormat as "csv" | "json"
      )
      if (result.success && result.downloadUrl && result.filename) {
        await downloadFile(result.downloadUrl, result.filename)
        setExportOpen(false)
      } else {
        setExportError(result.message || "Export failed")
      }
    } catch (e) {
      setExportError(e instanceof Error ? e.message : "Export failed")
    } finally {
      setExporting(false)
    }
  }

  if (isLoading && !logs) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">System Logs</h1>
          <p className="text-muted-foreground">Monitor system events and user actions</p>
        </div>
        <Card>
          <CardContent className="p-8">
            <div className="flex items-center justify-center gap-2 text-muted-foreground">
              <span className="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
              Loading logs...
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">System Logs</h1>
          <p className="text-muted-foreground">Monitor system events and user actions</p>
        </div>
        <Card className="border-destructive/50">
          <CardContent className="p-6">
            <p className="text-destructive">Failed to load logs. Please try again.</p>
            <Button variant="outline" className="mt-2" onClick={() => mutate()}>Retry</Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground">System Logs</h1>
          <p className="text-muted-foreground">Monitor system events and user actions</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" onClick={() => mutate()}>
            <RefreshCw className="h-4 w-4 mr-2" />
            Refresh
          </Button>
          <TooltipProvider>
            <Popover open={exportOpen} onOpenChange={setExportOpen}>
              <Tooltip>
                <TooltipTrigger asChild>
                  <PopoverTrigger asChild>
                    <Button variant="outline" size="sm">
                      <Download className="h-4 w-4 mr-2" />
                      Export
                    </Button>
                  </PopoverTrigger>
                </TooltipTrigger>
                <TooltipContent side="bottom" className="max-w-xs">
                  Export platform data as CSV (for Excel) or JSON. Choose data type and format, then click Export.
                </TooltipContent>
              </Tooltip>
              <PopoverContent className="w-72" align="end">
                <div className="space-y-3">
                  <p className="text-sm font-medium text-foreground">Export data</p>
                  <div className="space-y-2">
                    <label className="text-xs text-muted-foreground">Data type</label>
                    <Select value={exportDataType} onValueChange={setExportDataType}>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {ADMIN_EXPORT_TYPES.map((t) => (
                          <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <label className="text-xs text-muted-foreground">Format</label>
                    <Select value={exportFormat} onValueChange={setExportFormat}>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {EXPORT_FORMATS.map((f) => (
                          <SelectItem key={f.value} value={f.value}>{f.label}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground">CSV opens in Excel; JSON for developers.</p>
                  </div>
                  {exportError && (
                    <p className="text-xs text-destructive">{exportError}</p>
                  )}
                  <Button
                    size="sm"
                    className="w-full"
                    onClick={handleExport}
                    disabled={exporting}
                  >
                    {exporting ? (
                      <Loader2 className="h-4 w-4 animate-spin mr-2" />
                    ) : (
                      <Download className="h-4 w-4 mr-2" />
                    )}
                    {exporting ? "Generating…" : "Export"}
                  </Button>
                </div>
              </PopoverContent>
            </Popover>
          </TooltipProvider>
        </div>
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Event Logs</CardTitle>
          <div className="flex items-center gap-3">
            <div className="relative w-64">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Search logs..."
                className="pl-10"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
              />
            </div>
            <Select value={typeFilter} onValueChange={setTypeFilter}>
              <SelectTrigger className="w-32">
                <Filter className="h-4 w-4 mr-2" />
                <SelectValue placeholder="Level" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Levels</SelectItem>
                <SelectItem value="info">Info</SelectItem>
                <SelectItem value="warning">Warning</SelectItem>
                <SelectItem value="error">Error</SelectItem>
                <SelectItem value="success">Success</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardHeader>
        <CardContent>
          {filtered.length === 0 ? (
            <div className="py-12 text-center text-muted-foreground">No logs match your filters.</div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Timestamp</TableHead>
                  <TableHead>Level</TableHead>
                  <TableHead>Message</TableHead>
                  <TableHead>Source</TableHead>
                  <TableHead>Details</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filtered.map((log: SystemLog) => (
                  <TableRow key={log.id}>
                    <TableCell className="font-mono text-sm text-muted-foreground">
                      {new Date(log.timestamp).toLocaleString()}
                    </TableCell>
                    <TableCell>
                      <Badge variant={getLevelVariant(log.type)} className="gap-1">
                        {getLevelIcon(log.type)}
                        {log.type}
                      </Badge>
                    </TableCell>
                    <TableCell className="font-medium text-foreground">{log.message}</TableCell>
                    <TableCell className="text-muted-foreground">{log.source}</TableCell>
                    <TableCell className="text-muted-foreground max-w-xs truncate">{log.details ?? "—"}</TableCell>
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
