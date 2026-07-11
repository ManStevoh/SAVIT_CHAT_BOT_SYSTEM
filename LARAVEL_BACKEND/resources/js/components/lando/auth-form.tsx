import { cn } from "@/lib/utils"

export const landoBtnClass =
  "h-11 w-full rounded-lg bg-[#2563eb] text-white hover:bg-[#1d4ed8] font-medium"

export const landoInputClass =
  "h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-black outline-none focus:border-[#2563eb] focus:ring-1 focus:ring-[#2563eb]"

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
