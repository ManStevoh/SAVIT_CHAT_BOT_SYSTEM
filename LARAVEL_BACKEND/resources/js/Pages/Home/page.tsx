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
            <div className="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 text-3xl">
              🕵️‍♂️
            </div>
            <DialogTitle className="text-xl font-bold">Caught in 4K: Muscle Memory Too Fast! 💨</DialogTitle>
            <DialogDescription className="mt-2 text-sm text-muted-foreground">
              You unconsciously ran <code className="rounded bg-muted px-1.5 py-0.5 text-foreground font-mono">~/deploy</code> in the terminal because old habits die hard! 😂
            </DialogDescription>
          </DialogHeader>

          <div className="my-2 rounded-lg border border-amber-500/20 bg-amber-500/5 p-3 text-xs text-amber-700 dark:text-amber-300">
            <div className="flex items-center gap-2 font-semibold">
              <span>🩺 Diagnosis: Terminal Addiction Detected.</span>
            </div>
            <p className="mt-1 text-[11px] opacity-90">
              To cure this: You are strictly forbidden from opening cPanel SSH! Deploy this change from <strong>relayiq.app/deploy</strong> using only your mouse!
            </p>
          </div>

          <DialogFooter className="sm:justify-center">
            <Button
              type="button"
              className="w-full sm:w-auto bg-amber-600 hover:bg-amber-700 text-white"
              onClick={() => setShowModal(false)}
            >
              I Promise To Use The Web Button 🤝
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
