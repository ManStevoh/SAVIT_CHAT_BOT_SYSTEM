import Link from "next/link"
import { ArrowRight } from "lucide-react"
import { Button } from "@/components/ui/button"

export function CtaSection() {
  return (
    <section className="section-padding">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="rounded-lg border border-border/70 bg-foreground px-6 py-12 text-center sm:px-10 sm:py-14">
          <h2 className="text-2xl font-bold tracking-tight text-background sm:text-3xl">
            Start selling on WhatsApp this week
          </h2>
          <p className="mx-auto mt-3 max-w-lg text-sm leading-relaxed text-background/70 sm:text-base">
            Connect your number, add your products, and let AI handle the first reply. Your team
            stays in control.
          </p>

          <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
            <Button size="lg" asChild className="h-11 gap-2 rounded-md px-6 wa-cta border-0 shadow-none">
              <Link href="/register">
                Start free trial
                <ArrowRight className="h-4 w-4" />
              </Link>
            </Button>
            <Button
              size="lg"
              variant="outline"
              asChild
              className="h-11 rounded-md border-background/25 bg-transparent px-6 text-background hover:bg-background/10 hover:text-background"
            >
              <Link href="#pricing">See pricing</Link>
            </Button>
          </div>

          <p className="mt-4 text-xs text-background/60">
            14-day trial · No credit card · Cancel anytime
          </p>
        </div>
      </div>
    </section>
  )
}
