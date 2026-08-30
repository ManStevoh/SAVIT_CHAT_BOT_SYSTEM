"use client"

import { useMemo, useState } from "react"
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
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Mail, Trash2 } from "lucide-react"
import { useAdminContactSubmissions, type AdminContactSubmission } from "@/lib/api-hooks"
import { deleteContactSubmission, markContactSubmissionRead, markContactSubmissionUnread } from "@/lib/api-actions"
import { useToast } from "@/hooks/use-toast"

export default function AdminContactInboxPage() {
  const [unreadOnly, setUnreadOnly] = useState(false)
  const { data, error, isLoading, mutate } = useAdminContactSubmissions(unreadOnly)
  const { toast } = useToast()
  const [selected, setSelected] = useState<AdminContactSubmission | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<AdminContactSubmission | null>(null)
  const [deleting, setDeleting] = useState(false)

  const submissions = data?.submissions ?? []
  const unreadCount = data?.unreadCount ?? 0

  const openMessage = async (row: AdminContactSubmission) => {
    setSelected(row)
    if (!row.isRead) {
      await markContactSubmissionRead(row.id)
      mutate()
    }
  }

  const handleUnread = async () => {
    if (!selected) return
    const res = await markContactSubmissionUnread(selected.id)
    if (res.success) {
      toast({ title: "Marked unread" })
      setSelected(null)
      mutate()
    } else {
      toast({ title: res.message ?? "Update failed", variant: "destructive" })
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      const res = await deleteContactSubmission(deleteTarget.id)
      if (res.success) {
        toast({ title: "Message deleted" })
        if (selected?.id === deleteTarget.id) setSelected(null)
        setDeleteTarget(null)
        mutate()
      } else {
        toast({ title: res.message ?? "Delete failed", variant: "destructive" })
      }
    } finally {
      setDeleting(false)
    }
  }

  const selectedPreview = useMemo(() => selected, [selected])

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Contact inbox</h1>
          <p className="text-muted-foreground">
            Messages from relayiq.app/contact. Emails still go to Support Email when SMTP works.
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant={unreadOnly ? "outline" : "default"} onClick={() => setUnreadOnly(false)}>
            All
          </Button>
          <Button variant={unreadOnly ? "default" : "outline"} onClick={() => setUnreadOnly(true)}>
            Unread{unreadCount > 0 ? ` (${unreadCount})` : ""}
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Mail className="h-5 w-5" />
            Submitted messages
          </CardTitle>
          <CardDescription>
            {unreadCount === 0 ? "No unread messages." : `${unreadCount} unread.`} Open a row to read the full message.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center py-8">
              <span className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
            </div>
          ) : error ? (
            <p className="py-4 text-destructive">Failed to load contact messages.</p>
          ) : submissions.length === 0 ? (
            <p className="py-8 text-center text-muted-foreground">
              No contact submissions yet. New /contact form messages will appear here.
            </p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>From</TableHead>
                  <TableHead>Email</TableHead>
                  <TableHead>Preview</TableHead>
                  <TableHead>Received</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="w-[80px]" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {submissions.map((row) => (
                  <TableRow
                    key={row.id}
                    className="cursor-pointer"
                    onClick={() => void openMessage(row)}
                  >
                    <TableCell className="font-medium">{row.name}</TableCell>
                    <TableCell>{row.email}</TableCell>
                    <TableCell className="max-w-xs truncate text-muted-foreground">{row.message}</TableCell>
                    <TableCell className="text-muted-foreground">
                      {row.createdAt ? new Date(row.createdAt).toLocaleString() : "—"}
                    </TableCell>
                    <TableCell>
                      <Badge variant={row.isRead ? "secondary" : "default"}>{row.isRead ? "Read" : "Unread"}</Badge>
                    </TableCell>
                    <TableCell>
                      <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Delete message"
                        onClick={(event) => {
                          event.stopPropagation()
                          setDeleteTarget(row)
                        }}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Dialog open={selectedPreview !== null} onOpenChange={(open) => !open && setSelected(null)}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>{selectedPreview?.name}</DialogTitle>
          </DialogHeader>
          {selectedPreview && (
            <div className="space-y-3 text-sm">
              <p>
                <span className="text-muted-foreground">Email: </span>
                <a className="text-primary hover:underline" href={`mailto:${selectedPreview.email}`}>
                  {selectedPreview.email}
                </a>
              </p>
              <p className="text-muted-foreground">
                {selectedPreview.createdAt ? new Date(selectedPreview.createdAt).toLocaleString() : ""}
                {selectedPreview.ipAddress ? ` · ${selectedPreview.ipAddress}` : ""}
              </p>
              <p className="whitespace-pre-wrap rounded-md border border-border bg-muted/40 p-3">{selectedPreview.message}</p>
            </div>
          )}
          <DialogFooter className="gap-2">
            {selectedPreview && (
              <Button asChild variant="outline">
                <a href={`mailto:${selectedPreview.email}?subject=${encodeURIComponent("Re: your RelayIQ message")}`}>
                  Reply
                </a>
              </Button>
            )}
            <Button variant="outline" onClick={() => void handleUnread()}>
              Mark unread
            </Button>
            <Button onClick={() => setSelected(null)}>Close</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete this message?</AlertDialogTitle>
            <AlertDialogDescription>This cannot be undone.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={() => void handleDelete()} disabled={deleting}>
              {deleting ? "Deleting…" : "Delete"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
