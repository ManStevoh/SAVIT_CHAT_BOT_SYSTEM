import { Check } from "lucide-react"
import { Reveal } from "./reveal"

export type FeatureCatalogItem = {
  title: string
  detail?: string
}

export type FeatureCatalogGroup = {
  id?: string
  label?: string
  title: string
  customer?: string
  items?: FeatureCatalogItem[]
}

export function LandoFeatureCatalog({
  title,
  description,
  groups = [],
}: {
  title?: string
  description?: string
  groups?: FeatureCatalogGroup[]
}) {
  if (groups.length === 0) return null

  return (
    <section id="feature-list" className="bg-background py-14 lg:py-20">
      <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <Reveal>
          <div className="mx-auto max-w-3xl text-center">
            {title && <h2 className="text-3xl font-bold text-foreground sm:text-4xl">{title}</h2>}
            {description && (
              <p className="mt-4 text-base leading-relaxed text-muted-foreground sm:text-lg">{description}</p>
            )}
          </div>
        </Reveal>

        <nav aria-label="Feature groups" className="mx-auto mt-8 flex max-w-4xl flex-wrap items-center justify-center gap-2">
          {groups.map((group) => {
            const href = group.id ? `#${group.id}` : undefined
            if (!href) return null
            return (
              <a
                key={group.id}
                href={href}
                className="rounded-full border border-border bg-card px-3 py-1.5 text-xs font-semibold text-foreground transition-colors hover:border-primary/40 hover:text-primary"
              >
                {group.label ? `${group.label} · ` : ""}
                {group.title}
              </a>
            )
          })}
        </nav>

        <div className="mt-12 space-y-6">
          {groups.map((group, index) => (
            <Reveal key={group.id ?? group.title} delayMs={index * 40}>
              <article
                id={group.id}
                className="scroll-mt-28 overflow-hidden rounded-3xl border border-border bg-card shadow-sm"
              >
                <div className="border-b border-border bg-muted/40 px-6 py-5 sm:px-8">
                  <p className="text-xs font-bold tracking-widest text-primary uppercase">
                    {group.label ? `${group.label} · ` : ""}
                    {group.title}
                  </p>
                  {group.customer && (
                    <p className="mt-2 max-w-3xl text-base leading-relaxed text-foreground">
                      <span className="font-semibold">What the customer does: </span>
                      {group.customer}
                    </p>
                  )}
                </div>
                <ul className="grid gap-0 sm:grid-cols-2">
                  {(group.items ?? []).map((item) => (
                    <li key={item.title} className="flex gap-3 border-t border-border px-6 py-4 sm:border-t sm:px-8">
                      <Check className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden />
                      <div>
                        <p className="text-sm font-semibold text-card-foreground">{item.title}</p>
                        {item.detail && <p className="mt-1 text-sm leading-relaxed text-muted-foreground">{item.detail}</p>}
                      </div>
                    </li>
                  ))}
                </ul>
              </article>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  )
}
