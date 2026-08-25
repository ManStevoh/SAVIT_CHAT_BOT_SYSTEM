import { useState, useEffect } from "react"
import { LandoCmsPage } from "@/components/lando/cms-page"
import type { CmsPageData } from "@/components/lando/types"
import type { SeoPayload } from "@/components/seo/SeoHead"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { CheckCircle2, Rocket } from "lucide-react"

export default function HomePage({
  seo,
  cms,
  cmsGlobal,
}: {
  seo?: SeoPayload | null
  cms?: CmsPageData | null
  cmsGlobal?: CmsPageData | null
}) {
  const [showModal, setShowModal] = useState(false)

  useEffect(() => {
    // Open modal on load
    setShowModal(true)
  }, [])

  return (
    <>
      <LandoCmsPage
        slug="home"
        fallbackTitle="RelayIQ"
        initialSeo={seo}
        initialCms={cms}
        initialCmsGlobal={cmsGlobal}
      />

      <Dialog open={showModal} onOpenChange={setShowModal}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader className="flex flex-col items-center text-center">
            <div className="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
              <Rocket className="h-7 w-7 animate-bounce" />
            </div>
            <DialogTitle className="text-xl font-bold">All Systems Is a Go! 🚀</DialogTitle>
            <DialogDescription className="mt-2 text-sm text-muted-foreground">
              Production deployment is active and fully operational. Your Git pipeline, pre-compiled assets, and caching engine are running smoothly.
            </DialogDescription>
          </DialogHeader>

          <div className="my-2 rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-3 text-xs text-emerald-700 dark:text-emerald-300">
            <div className="flex items-center gap-2 font-semibold">
              <CheckCircle2 className="h-4 w-4" />
              <span>Production Pipeline Status: Active</span>
            </div>
          </div>

          <DialogFooter className="sm:justify-center">
            <Button
              type="button"
              className="w-full sm:w-auto"
              onClick={() => setShowModal(false)}
            >
              Continue to RelayIQ
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
