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
      {links.map((link, i) => (
        <div key={i} className="flex gap-2">
          <Input
            value={link.label}
            onChange={(e) => update(i, "label", e.target.value)}
            placeholder="Label"
            className="text-sm"
          />
          <Input
            value={link.href}
            onChange={(e) => update(i, "href", e.target.value)}
            placeholder="/path"
            className="text-sm"
          />
          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="shrink-0"
            onClick={() => onChange(links.filter((_, j) => j !== i))}
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
