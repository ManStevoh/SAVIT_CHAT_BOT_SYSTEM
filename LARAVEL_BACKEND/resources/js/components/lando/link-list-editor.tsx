import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Plus, Trash2 } from "lucide-react"
import type { CmsLink } from "./types"

export function LinkListEditor({
  label,
  links,
  onChange,
}: {
  label: string
  links: CmsLink[]
  onChange: (links: CmsLink[]) => void
}) {
  const update = (index: number, field: keyof CmsLink, value: string) => {
    const next = [...links]
    next[index] = { ...next[index], [field]: value }
    onChange(next)
  }

  return (
    <div className="space-y-2">
      <Label className="text-xs">{label}</Label>
