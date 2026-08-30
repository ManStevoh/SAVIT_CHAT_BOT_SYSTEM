type PlanOffer = {
  code?: string | null
  discountType?: string | null
  discountValue?: number | null
}

export function PlanPrice({
  price,
  originalPrice,
  offer,
  period = "per month",
  size = "lg",
}: {
  price?: string | null
  originalPrice?: string | null
  offer?: PlanOffer | null
  period?: string
  size?: "lg" | "md"
}) {
  const display = price ?? "—"
  const isCustom = display === "Custom"
  const showOriginal = !!originalPrice && originalPrice !== display
  const priceClass = size === "lg" ? "text-4xl font-bold text-card-foreground" : "text-3xl font-bold text-foreground"

  return (
    <div>
      {showOriginal ? (
        <p className="text-sm text-muted-foreground line-through tabular-nums">{originalPrice}</p>
      ) : null}
      <p className={`${priceClass} tabular-nums`}>{display}</p>
      {!isCustom ? <p className="text-sm text-muted-foreground">{period}</p> : null}
      {offer?.code ? (
        <p className="mt-1 text-xs font-medium text-primary">
          {offer.discountType === "percent" && offer.discountValue
            ? `${offer.discountValue}% off`
            : "Sale"}
          {" · "}
          {offer.code} applied at checkout
        </p>
      ) : null}
    </div>
  )
}
