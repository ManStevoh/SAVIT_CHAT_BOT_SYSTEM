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
  const [modalOpen, setModalOpen] = useState(false)

  return (
    <>
      <LandoCmsPage
        slug="home"
        fallbackTitle="RelayIQ"
        initialSeo={seo}
        initialCms={cms}
        initialCmsGlobal={cmsGlobal}
      />

      {/* Test Deployment Floating Trigger Button */}
      <div className="fixed bottom-6 right-6 z-40">
        <button
          onClick={() => setModalOpen(true)}
          className="inline-flex items-center gap-2 px-4 py-2.5 rounded-full bg-red-600 hover:bg-red-700 text-white text-sm font-semibold shadow-lg hover:shadow-xl transition-all duration-200 cursor-pointer border border-red-400/30 backdrop-blur-sm"
        >
          <Sparkles className="w-4 h-4 text-red-200 animate-pulse" />
          <span>Test Deployment Modal (Red)</span>
        </button>
      </div>

      {/* Test Deployment Modal Dialog */}
      <Dialog open={modalOpen} onOpenChange={setModalOpen}>
        <DialogContent className="sm:max-w-md bg-white text-slate-900 border-2 border-red-500 shadow-2xl rounded-2xl p-6">
          <DialogHeader className="space-y-3">
            <div className="w-12 h-12 rounded-xl bg-red-50 border border-red-200 flex items-center justify-center text-red-600">
              <CheckCircle2 className="w-6 h-6" />
            </div>
            <DialogTitle className="text-xl font-bold text-red-600">
              Deployment Verification (Red Theme)
            </DialogTitle>
            <DialogDescription className="text-sm text-slate-600 leading-relaxed">
              This modal confirms that the <strong>RelayIQ automated deployment pipeline</strong> and <strong>Vite asset compilation</strong> were successfully executed and deployed to live production in red theme.
            </DialogDescription>
          </DialogHeader>

          <div className="my-4 p-3 rounded-lg bg-red-50/50 border border-red-100 text-xs font-mono text-slate-700 space-y-1">
            <div className="flex justify-between">
              <span className="text-slate-400">Target Gateway:</span>
              <span className="text-red-600 font-semibold">Production</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-400">Pipeline Status:</span>
              <span className="text-red-600 font-semibold">Active & Verified</span>
            </div>
          </div>

          <DialogFooter className="flex gap-2 sm:justify-end">
            <Button
              variant="default"
              onClick={() => setModalOpen(false)}
              className="bg-red-600 hover:bg-red-700 text-white font-medium"
            >
              Close Verification
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
