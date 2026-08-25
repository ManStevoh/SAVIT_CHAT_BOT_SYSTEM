import { cn } from "@/lib/utils"

export const landoBtnClass =
  "h-11 w-full rounded-lg bg-primary text-primary-foreground hover:bg-primary/90 font-medium"

export const landoInputClass =
  "h-11 w-full rounded-lg border border-border bg-background px-4 text-foreground outline-none focus:border-primary focus:ring-1 focus:ring-primary"

export function LandoAuthHeader({
  title,
  description,
  className,
}: {
  title: string
  description?: string
  className?: string
}) {
  return (
    <div className={cn("mb-8", className)}>
      <h1 className="text-3xl font-bold text-foreground sm:text-4xl">{title}</h1>
      {description && <p className="mt-2 text-base text-muted-foreground">{description}</p>}
    </div>
  )
}

export function LandoAuthError({ children }: { children: React.ReactNode }) {
  return (
    <div className="mb-6 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
      {children}
    </div>
  )
}

export function LandoAuthSuccess({ children }: { children: React.ReactNode }) {
  return (
    <div className="mb-6 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">
      {children}
    </div>
  )
}

export function LandoAuthLink({
  href,
  children,
}: {
  href: string
  children: React.ReactNode
}) {
  return (
    <a href={href} className="text-sm font-medium text-primary hover:text-primary/80">
      {children}
    </a>
  )
}
