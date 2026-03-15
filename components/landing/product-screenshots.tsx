import { Inbox, ShoppingBag, BarChart3, MessageSquare, Package, TrendingUp } from "lucide-react"

const screenshots = [
  {
    title: "Chat Inbox",
    description: "Manage all customer conversations in a unified inbox with AI-powered suggestions.",
    icon: Inbox,
    preview: (
      <div className="p-4 space-y-3">
        {[1, 2, 3].map((i) => (
          <div key={i} className="flex items-center gap-3 rounded-lg bg-secondary/50 p-3">
            <div className="h-10 w-10 rounded-full bg-primary/20 flex items-center justify-center">
              <MessageSquare className="h-5 w-5 text-primary" />
            </div>
            <div className="flex-1 min-w-0">
              <div className="h-3 w-24 rounded bg-foreground/20 mb-1" />
              <div className="h-2 w-32 rounded bg-muted-foreground/20" />
            </div>
            <div className="h-5 w-5 rounded-full bg-primary flex items-center justify-center text-xs text-primary-foreground font-medium">
              {i}
            </div>
          </div>
        ))}
      </div>
    ),
  },
  {
    title: "Orders Dashboard",
    description: "Track and manage orders in real-time with status updates and customer notifications.",
    icon: ShoppingBag,
    preview: (
      <div className="p-4 space-y-3">
        <div className="grid grid-cols-3 gap-2 mb-4">
          {[
            { label: "Pending", value: "12", color: "text-yellow-500" },
            { label: "Processing", value: "8", color: "text-blue-500" },
            { label: "Completed", value: "156", color: "text-primary" },
          ].map((stat) => (
            <div key={stat.label} className="rounded-lg bg-secondary/50 p-2 text-center">
              <div className={`text-lg font-bold ${stat.color}`}>{stat.value}</div>
              <div className="text-xs text-muted-foreground">{stat.label}</div>
            </div>
          ))}
        </div>
        {[1, 2].map((i) => (
          <div key={i} className="flex items-center gap-3 rounded-lg bg-secondary/50 p-3">
            <Package className="h-5 w-5 text-muted-foreground" />
            <div className="flex-1">
              <div className="h-3 w-20 rounded bg-foreground/20 mb-1" />
              <div className="h-2 w-28 rounded bg-muted-foreground/20" />
            </div>
            <div className="text-xs text-primary font-medium">$49.99</div>
          </div>
        ))}
      </div>
    ),
  },
  {
    title: "Analytics Dashboard",
    description: "Gain insights with comprehensive analytics on messages, orders, and customer behavior.",
    icon: BarChart3,
    preview: (
      <div className="p-4">
        <div className="flex items-center gap-2 mb-4">
          <TrendingUp className="h-4 w-4 text-primary" />
          <span className="text-sm font-medium text-foreground">Weekly Overview</span>
        </div>
        <div className="flex items-end justify-between gap-2 h-20">
          {[40, 65, 45, 80, 55, 90, 70].map((height, i) => (
            <div key={i} className="flex-1">
              <div
                className="w-full rounded-t bg-primary/80 transition-all hover:bg-primary"
                style={{ height: `${height}%` }}
              />
            </div>
          ))}
        </div>
        <div className="flex justify-between mt-2">
          {["M", "T", "W", "T", "F", "S", "S"].map((day, i) => (
            <span key={i} className="text-xs text-muted-foreground">{day}</span>
          ))}
        </div>
      </div>
    ),
  },
]

export function ProductScreenshots() {
  return (
    <section className="py-20 lg:py-32">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-16">
          <h2 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
            Powerful tools at your fingertips
          </h2>
          <p className="mt-4 text-lg text-muted-foreground max-w-2xl mx-auto">
            A complete suite of tools designed for modern businesses
          </p>
        </div>

        <div className="grid gap-6 md:grid-cols-3">
          {screenshots.map((item) => (
            <div
              key={item.title}
              className="group rounded-2xl border border-border bg-card overflow-hidden transition-all hover:border-primary/50 hover:shadow-lg hover:shadow-primary/5"
            >
              <div className="aspect-[4/3] bg-secondary/30 border-b border-border">
                {item.preview}
              </div>
              <div className="p-6">
                <div className="flex items-center gap-2 mb-2">
                  <item.icon className="h-5 w-5 text-primary" />
                  <h3 className="font-semibold text-foreground">{item.title}</h3>
                </div>
                <p className="text-sm text-muted-foreground">{item.description}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
