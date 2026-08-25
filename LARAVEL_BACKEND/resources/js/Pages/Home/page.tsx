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
import { Sparkles, Laugh, RefreshCw, Smile } from "lucide-react"

const JOKES = [
  {
    setup: "Why do programmers prefer dark mode?",
    punchline: "Because light attracts bugs! 🐛✨",
  },
  {
    setup: "Why do Java developers wear glasses?",
    punchline: "Because they don't C#! 🤓",
  },
  {
    setup: "How do you comfort a JavaScript bug?",
    punchline: "You console it! 💻❤️",
  },
  {
    setup: "There are 10 types of people in the world...",
    punchline: "Those who understand binary, and those who don't! 🤖",
  },
  {
    setup: "Why did the database administrator leave the restaurant?",
    punchline: "Because there were too many tables! 🍽️📊",
  },
]

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
  const [jokeIndex, setJokeIndex] = useState(0)

  useEffect(() => {
    // Open joke modal automatically shortly after mounting
    const timer = setTimeout(() => {
      setModalOpen(true)
    }, 600)
    return () => clearTimeout(timer)
  }, [])

  const nextJoke = () => {
    setJokeIndex((prev) => (prev + 1) % JOKES.length)
  }

  const currentJoke = JOKES[jokeIndex]

  return (
    <>
      <LandoCmsPage
        slug="home"
        fallbackTitle="RelayIQ"
        initialSeo={seo}
        initialCms={cms}
        initialCmsGlobal={cmsGlobal}
      />

      {/* Floating Re-open Button */}
      <div className="fixed bottom-6 right-6 z-40">
        <button
          onClick={() => setModalOpen(true)}
          className="inline-flex items-center gap-2 px-4 py-2.5 rounded-full bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white text-sm font-semibold shadow-lg hover:shadow-xl transition-all duration-200 cursor-pointer border border-amber-400/30 backdrop-blur-sm group"
          title="Click for a joke!"
        >
          <Laugh className="w-4 h-4 text-amber-200 group-hover:rotate-12 transition-transform duration-200" />
          <span>Daily Joke 😄</span>
        </button>
      </div>

      {/* Joke Modal Popup */}
      <Dialog open={modalOpen} onOpenChange={setModalOpen}>
        <DialogContent className="sm:max-w-md bg-white text-slate-900 border border-amber-200 shadow-2xl rounded-2xl p-6 dark:bg-slate-900 dark:text-white dark:border-amber-500/20">
          <DialogHeader className="space-y-3 text-center sm:text-left">
            <div className="flex items-center gap-3">
              <div className="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-950/60 border border-amber-300 dark:border-amber-700/50 flex items-center justify-center text-amber-600 dark:text-amber-400 shadow-sm shrink-0">
                <Smile className="w-6 h-6 animate-bounce" />
              </div>
              <div>
                <div className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300 mb-1">
                  <Sparkles className="w-3 h-3 text-amber-500" />
                  RelayIQ Daily Joke
                </div>
                <DialogTitle className="text-xl font-bold text-slate-900 dark:text-white">
                  Here&apos;s a joke for you! 🎉
                </DialogTitle>
              </div>
            </div>
            <DialogDescription className="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
              A little moment of fun while exploring RelayIQ.
            </DialogDescription>
          </DialogHeader>

          {/* Joke Card */}
          <div className="my-3 p-4 rounded-xl bg-amber-50/80 dark:bg-slate-800/80 border border-amber-200/70 dark:border-amber-900/30 space-y-3 text-center sm:text-left shadow-inner">
            <p className="text-base font-semibold text-slate-800 dark:text-slate-100">
              &ldquo;{currentJoke.setup}&rdquo;
            </p>
            <div className="p-3 rounded-lg bg-white dark:bg-slate-900 border border-amber-200/50 dark:border-slate-700">
              <p className="text-lg font-bold text-amber-600 dark:text-amber-400">
                {currentJoke.punchline}
              </p>
            </div>
          </div>

          <DialogFooter className="flex flex-col-reverse sm:flex-row gap-2 sm:justify-between items-center pt-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={nextJoke}
              className="w-full sm:w-auto inline-flex items-center gap-1.5 text-xs text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800"
            >
              <RefreshCw className="w-3.5 h-3.5" />
              Another Joke
            </Button>
            <Button
              type="button"
              onClick={() => setModalOpen(false)}
              className="w-full sm:w-auto bg-amber-600 hover:bg-amber-700 text-white font-medium text-sm"
            >
              Haha, thanks! 👋
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
