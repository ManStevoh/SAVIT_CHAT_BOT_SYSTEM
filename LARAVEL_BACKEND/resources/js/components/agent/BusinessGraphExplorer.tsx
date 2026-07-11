"use client"

import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { useBusinessGraph } from "@/lib/api-hooks"

const TYPE_COLORS: Record<string, string> = {
  product: "bg-blue-500/10 text-blue-700",
  customer: "bg-green-500/10 text-green-700",
  order: "bg-amber-500/10 text-amber-700",
  campaign: "bg-purple-500/10 text-purple-700",
  category: "bg-slate-500/10 text-slate-700",
  supplier: "bg-rose-500/10 text-rose-700",
  warehouse: "bg-cyan-500/10 text-cyan-700",
}

export function BusinessGraphExplorer() {
  const { data, isLoading } = useBusinessGraph()

  const nodesByType = (data?.nodes ?? []).reduce<Record<string, number>>((acc, n) => {
    acc[n.type] = (acc[n.type] ?? 0) + 1
    return acc
  }, {})

  return (
    <Card>
      <CardHeader>
        <CardTitle>Business graph explorer</CardTitle>
        <CardDescription>
          {isLoading
            ? "Loading graph…"
            : `${data?.stats.nodes ?? 0} nodes · ${data?.stats.edges ?? 0} edges`}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4 text-sm">
        {!isLoading && Object.keys(nodesByType).length > 0 && (
          <div className="flex flex-wrap gap-2">
            {Object.entries(nodesByType).map(([type, count]) => (
              <Badge key={type} variant="outline" className={TYPE_COLORS[type] ?? ""}>
                {type}: {count}
              </Badge>
            ))}
          </div>
        )}

        {(data?.nodes?.length ?? 0) === 0 ? (
          <p className="text-muted-foreground">No graph nodes yet — run Sync business graph.</p>
        ) : (
          <ul className="space-y-2 max-h-64 overflow-y-auto">
            {data?.nodes.slice(0, 30).map((node) => (
              <li key={node.id} className="flex items-center justify-between gap-2 border rounded-md px-2 py-1">
                <span className="truncate">{node.label}</span>
                <Badge variant="secondary" className="text-[10px] shrink-0">
                  {node.type}
                </Badge>
              </li>
            ))}
          </ul>
        )}

        {(data?.edges?.length ?? 0) > 0 && (
          <div>
            <p className="text-xs font-medium mb-1">Sample relationships</p>
            <ul className="text-xs text-muted-foreground space-y-1 max-h-32 overflow-y-auto">
              {data?.edges.slice(0, 12).map((edge, i) => (
                <li key={i}>
                  #{edge.from} → #{edge.to} ({edge.type})
                </li>
              ))}
            </ul>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
