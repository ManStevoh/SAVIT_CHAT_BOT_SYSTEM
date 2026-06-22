import { cn } from "@/lib/utils"

interface SectionHeaderProps {
  label?: string
  title: string
  description?: string
  align?: "center" | "left"
  className?: string
}

export function SectionHeader({
  label,
  title,
  description,
  align = "center",
  className,
}: SectionHeaderProps) {
  return (
    <div
      className={cn(
        "mb-14 lg:mb-16",
        align === "center" ? "text-center mx-auto max-w-2xl" : "max-w-xl",
        className
      )}
    >
      {label && (
        <p className="mb-3 text-xs font-medium uppercase tracking-widest text-accent">
          {label}
        </p>
      )}
      <h2 className="font-display text-3xl font-normal text-foreground sm:text-4xl lg:text-[2.75rem] lg:leading-[1.15]">
        {title}
      </h2>
      {description && (
        <p className="mt-4 text-base leading-relaxed text-muted-foreground">
          {description}
        </p>
      )}
    </div>
  )
}
