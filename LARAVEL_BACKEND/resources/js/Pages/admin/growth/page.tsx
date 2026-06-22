"use client"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { useAdminGrowthPortfolio } from "@/lib/api-hooks"
import { generatePortfolioRecommendations } from "@/lib/api-actions"
import { useState } from "react"
import { Loader2 } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { toast } from "sonner"
import { mutate } from "swr"

export default function AdminGrowthPage() {
  const [period, setPeriod] = useState("30d")
  const { data, isLoading } = useAdminGrowthPortfolio(period)

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Growth Portfolio</h1>
          <p className="text-muted-foreground">Cross-brand attributed leads and revenue (Sapphital Group view)</p>
        </div>
        <Select value={period} onValueChange={setPeriod}>
          <SelectTrigger className="w-[140px]">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="7d">Last 7 days</SelectItem>
            <SelectItem value="30d">Last 30 days</SelectItem>
            <SelectItem value="90d">Last 90 days</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-20">
          <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        </div>
      ) : (
        <>
          <div className="grid gap-4 md:grid-cols-3">
            <Card>
              <CardHeader className="pb-2">
                <CardDescription>Total leads</CardDescription>
                <CardTitle className="text-3xl">{data?.totals.leads ?? 0}</CardTitle>
              </CardHeader>
            </Card>
            <Card>
              <CardHeader className="pb-2">
                <CardDescription>Total attributed revenue</CardDescription>
                <CardTitle className="text-3xl">{(data?.totals.revenue ?? 0).toLocaleString()}</CardTitle>
              </CardHeader>
            </Card>
            <Card>
              <CardHeader className="pb-2">
                <CardDescription>Posts published</CardDescription>
                <CardTitle className="text-3xl">{data?.totals.posts ?? 0}</CardTitle>
              </CardHeader>
            </Card>
          </div>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <div>
                <CardTitle>Cross-brand insight</CardTitle>
                <CardDescription>{data?.crossBrandInsight}</CardDescription>
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={async () => {
                  const r = await generatePortfolioRecommendations()
                  if (r.success) {
                    toast.success("Portfolio AI recommendations generated")
                    mutate(["admin-growth-portfolio", period])
                  } else toast.error(r.message ?? "Failed")
                }}
              >
                Run portfolio AI
              </Button>
            </CardHeader>
          </Card>

          {(data?.recommendations?.length ?? 0) > 0 && (
            <div className="grid gap-4 md:grid-cols-2">
              {data?.recommendations?.map((rec) => (
                <Card key={rec.id}>
                  <CardHeader className="pb-2">
                    <div className="flex items-center justify-between gap-2">
                      <Badge variant="outline">{rec.recommendationType}</Badge>
                      <span className="text-xs text-muted-foreground">{rec.confidenceScore}%</span>
                    </div>
                    <CardTitle className="text-base">{rec.title}</CardTitle>
                    {rec.companyName && (
                      <p className="text-xs text-muted-foreground">For: {rec.companyName}</p>
                    )}
                  </CardHeader>
                  <CardContent>
                    <p className="text-sm text-muted-foreground">{rec.body}</p>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}

          <Card>
            <CardHeader>
              <CardTitle>Companies</CardTitle>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Company</TableHead>
                    <TableHead className="text-right">Leads</TableHead>
                    <TableHead className="text-right">Posts</TableHead>
                    <TableHead className="text-right">Revenue</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(data?.companies ?? []).map((c) => (
                    <TableRow key={c.companyId}>
                      <TableCell className="font-medium">{c.companyName}</TableCell>
                      <TableCell className="text-right">{c.leads}</TableCell>
                      <TableCell className="text-right">{c.posts}</TableCell>
                      <TableCell className="text-right">{c.revenue.toLocaleString()}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </>
      )}
    </div>
  )
}
