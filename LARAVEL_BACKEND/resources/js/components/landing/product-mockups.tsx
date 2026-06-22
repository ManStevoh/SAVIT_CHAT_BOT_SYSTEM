/** High-fidelity UI mockups for the landing product section (no image assets required). */

export function InboxMockup() {
  const chats = [
    { name: "Grace M.", preview: "I'd like to order the combo meal", time: "2m", unread: 2, active: true },
    { name: "James O.", preview: "Is M-Pesa payment available?", time: "14m", unread: 0, active: false },
    { name: "Amina K.", preview: "Order #1042 confirmed, thanks!", time: "1h", unread: 0, active: false },
  ]

  return (
    <div className="flex h-full flex-col bg-background text-[11px]">
      <div className="flex items-center justify-between border-b border-border/60 px-4 py-2.5">
        <span className="font-semibold text-foreground">Inbox</span>
        <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
          3 active
        </span>
      </div>
      <div className="flex-1 space-y-0.5 p-2">
        {chats.map((chat) => (
          <div
            key={chat.name}
            className={`flex items-start gap-2.5 rounded-lg px-2.5 py-2 ${
              chat.active ? "bg-primary/5 ring-1 ring-primary/15" : "hover:bg-muted/50"
            }`}
          >
            <div className="relative shrink-0">
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-muted text-[10px] font-semibold text-foreground">
                {chat.name.charAt(0)}
              </div>
              {chat.active && (
                <span className="absolute -bottom-0.5 -right-0.5 h-2 w-2 rounded-full border border-background bg-emerald-500" />
              )}
            </div>
            <div className="min-w-0 flex-1">
              <div className="flex items-center justify-between gap-2">
                <span className="truncate font-medium text-foreground">{chat.name}</span>
                <span className="shrink-0 text-[10px] text-muted-foreground">{chat.time}</span>
              </div>
              <p className="truncate text-[10px] text-muted-foreground">{chat.preview}</p>
            </div>
            {chat.unread > 0 && (
              <span className="flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[9px] font-medium text-primary-foreground">
                {chat.unread}
              </span>
            )}
          </div>
        ))}
      </div>
    </div>
  )
}

export function OrdersMockup() {
  const orders = [
    { id: "#1042", customer: "Grace M.", total: "KES 2,798", status: "Paid", statusColor: "text-emerald-600 bg-emerald-50" },
    { id: "#1041", customer: "James O.", total: "KES 1,450", status: "Pending", statusColor: "text-amber-600 bg-amber-50" },
  ]

  return (
    <div className="flex h-full flex-col bg-background p-4 text-[11px]">
      <div className="mb-3 grid grid-cols-3 gap-2">
        {[
          { label: "Today", value: "24" },
          { label: "Pending", value: "6" },
          { label: "Revenue", value: "48K" },
        ].map((s) => (
          <div key={s.label} className="rounded-lg bg-muted/60 px-2 py-2 text-center ring-1 ring-border/50">
            <div className="text-sm font-semibold tabular-nums text-foreground">{s.value}</div>
            <div className="text-[10px] text-muted-foreground">{s.label}</div>
          </div>
        ))}
      </div>
      <div className="space-y-1.5">
        <div className="grid grid-cols-[auto_1fr_auto_auto] gap-2 px-2 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
          <span>Order</span>
          <span>Customer</span>
          <span>Total</span>
          <span>Status</span>
        </div>
        {orders.map((o) => (
          <div
            key={o.id}
            className="grid grid-cols-[auto_1fr_auto_auto] items-center gap-2 rounded-lg bg-muted/40 px-2 py-2 ring-1 ring-border/40"
          >
            <span className="font-medium text-foreground">{o.id}</span>
            <span className="truncate text-muted-foreground">{o.customer}</span>
            <span className="tabular-nums font-medium text-foreground">{o.total}</span>
            <span className={`rounded px-1.5 py-0.5 text-[9px] font-medium ${o.statusColor}`}>
              {o.status}
            </span>
          </div>
        ))}
      </div>
    </div>
  )
}

export function AnalyticsMockup() {
  const bars = [35, 52, 41, 68, 55, 78, 62]
  const days = ["M", "T", "W", "T", "F", "S", "S"]

  return (
    <div className="flex h-full flex-col bg-background p-4 text-[11px]">
      <div className="mb-4 flex items-center justify-between">
        <div>
          <p className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
            Messages (7d)
          </p>
          <p className="text-xl font-semibold tabular-nums text-foreground">12,847</p>
          <p className="text-[10px] text-emerald-600">+18.2% vs last week</p>
        </div>
        <div className="rounded-lg bg-primary/10 px-2.5 py-1.5 text-[10px] font-medium text-primary">
          Live
        </div>
      </div>
      <div className="flex flex-1 items-end justify-between gap-1.5">
        {bars.map((h, i) => (
          <div key={i} className="flex flex-1 flex-col items-center gap-1">
            <div
              className="w-full rounded-sm bg-primary/80"
              style={{ height: `${h}%`, minHeight: 4 }}
            />
            <span className="text-[9px] text-muted-foreground">{days[i]}</span>
          </div>
        ))}
      </div>
    </div>
  )
}
