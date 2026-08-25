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
import { Sparkles, CheckCircle2 } from "lucide-react"

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
            <div className="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400">
              <Sparkles className="h-7 w-7 animate-spin" />
            </div>
            <DialogTitle className="text-xl font-bold">🎉 Web Deploy Verified!</DialogTitle>
            <DialogDescription className="mt-2 text-sm text-muted-foreground">
              This modal was deployed live from <strong>https://relayiq.app/deploy</strong> with 1-click in the browser!
            </DialogDescription>
          </DialogHeader>

          <div className="my-2 rounded-lg border border-blue-500/20 bg-blue-500/5 p-3 text-xs text-blue-700 dark:text-blue-300">
            <div className="flex items-center gap-2 font-semibold">
              <CheckCircle2 className="h-4 w-4" />
              <span>Web Console Deployment: 100% Operational</span>
            </div>
          </div>

          <DialogFooter className="sm:justify-center">
            <Button
              type="button"
              className="w-full sm:w-auto"
              onClick={() => setShowModal(false)}
            >
              Confirm & Continue
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
