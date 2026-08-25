import { cn } from "@/lib/utils"
import { BRAND } from "@/lib/branding"

function WhatsAppGlyph({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" className={cn("h-4 w-4 shrink-0", className)} aria-hidden>
      <path
        fill="currentColor"
        d="M12.04 2c-5.46 0-9.91 4.43-9.91 9.88 0 1.74.46 3.45 1.32 4.95L2 22l5.35-1.4a9.9 9.9 0 0 0 4.69 1.19h.01c5.46 0 9.91-4.43 9.91-9.88C21.95 6.43 17.5 2 12.04 2Zm0 18.12h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.17.83.85-3.09-.2-.32a8.16 8.16 0 0 1-1.25-4.34c0-4.52 3.7-8.2 8.25-8.2 2.2 0 4.27.85 5.83 2.4a8.12 8.12 0 0 1 2.42 5.8c0 4.52-3.7 8.2-8.24 8.2Zm4.52-6.14c-.25-.12-1.46-.72-1.69-.8-.23-.09-.39-.12-.56.12-.16.25-.64.8-.78.96-.14.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.39-1.72-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.85-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.46-.6 1.67-1.18.21-.58.21-1.07.14-1.18-.06-.1-.23-.17-.48-.29Z"
      />
    </svg>
  )
}

type WhatsAppPartnerBadgeProps = {
  className?: string
  /** Visual density */
  size?: "sm" | "md"
  /** Soft pill vs plain text row */
  variant?: "pill" | "plain"
}

export function WhatsAppPartnerBadge({
  className,
  size = "sm",
  variant = "pill",
}: WhatsAppPartnerBadgeProps) {
  return (
    <div
      role="note"
      className={cn(
        "inline-flex max-w-full items-center gap-2 text-[#075E54]",
        size === "sm" ? "text-xs font-semibold tracking-wide" : "text-sm font-semibold",
        variant === "pill" &&
          "rounded-full border border-[#25D366]/35 bg-[#25D366]/10 px-3 py-1.5",
        className
      )}
    >
      <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#25D366] text-white">
        <WhatsAppGlyph className="h-3.5 w-3.5" />
      </span>
      <span className="min-w-0 leading-snug">{BRAND.whatsappPartner}</span>
    </div>
  )
}
