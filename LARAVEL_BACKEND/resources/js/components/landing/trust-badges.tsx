import { BadgeCheck, Lock, Server, UserCheck } from "lucide-react"

const badges = [
  {
    icon: BadgeCheck,
    title: "Official WhatsApp API",
    description: "Meta Cloud API — not unofficial QR hacks.",
  },
  {
    icon: Lock,
    title: "Encrypted",
    description: "TLS in transit. Secure storage for business data.",
  },
  {
    icon: UserCheck,
    title: "Human handoff",
    description: "Agents take over any chat. Bot pauses until released.",
  },
  {
    icon: Server,
    title: "Tenant isolation",
    description: "Each company's data and AI credentials stay separate.",
  },
]

export function TrustBadges() {
  return (
    <section className="landing-divider bg-muted/20 py-12">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {badges.map((badge) => (
            <div key={badge.title} className="flex gap-3">
              <badge.icon className="mt-0.5 h-5 w-5 shrink-0 text-primary" strokeWidth={1.75} />
              <div>
                <h3 className="text-sm font-semibold text-foreground">{badge.title}</h3>
                <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                  {badge.description}
                </p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
