import { cn } from "@/lib/utils"

interface PageHeaderProps {
  title: string
  description?: string
  label?: string
  actions?: React.ReactNode
  className?: string
}

export function PageHeader({
  title,
  description,
  label,
  actions,
  className,
}: PageHeaderProps) {
  return (
    <div
      className={cn(
        "flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between",
        className
      )}
    >
      <div>
        {label && (
          <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
            {label}
          </p>
        )}
        <h1 className={cn("text-2xl font-semibold tracking-tight text-foreground", label && "mt-1")}>
          {title}
        </h1>
        {description && (
          <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        )}
      </div>
      {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
    </div>
  )
}
