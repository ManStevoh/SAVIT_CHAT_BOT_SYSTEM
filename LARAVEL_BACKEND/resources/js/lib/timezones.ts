/**
 * World timezones (IANA identifiers) for platform default timezone and company settings.
 * Uses Intl.supportedValuesOf('timeZone') when available (modern browsers/Node 20+),
 * otherwise falls back to a comprehensive static list.
 */

export interface TimezoneOption {
  value: string
  label: string
  /** e.g. "America" for grouping */
  region: string
}

/** Human-readable label from IANA id (e.g. "America/New_York" → "New York (Eastern)") */
function labelFor(value: string): string {
  try {
    const parts = value.split("/")
    const city = parts[parts.length - 1]?.replace(/_/g, " ") ?? value
    const formatter = new Intl.DateTimeFormat("en-US", {
      timeZone: value,
      timeZoneName: "shortOffset",
    })
    const offset = formatter.formatToParts(new Date()).find((p) => p.type === "timeZoneName")?.value ?? ""
    return offset ? `${city} (${offset})` : city
  } catch {
    return value.replace(/_/g, " ")
  }
}

function getTimezoneList(): string[] {
  if (typeof Intl !== "undefined" && "supportedValuesOf" in Intl) {
    try {
      return (Intl as unknown as { supportedValuesOf(key: string): string[] }).supportedValuesOf("timeZone")
    } catch {
      // ignore
    }
  }
  // Fallback: comprehensive list of common IANA timezones (all regions)
  return [
    "UTC",
    "Africa/Cairo",
    "Africa/Johannesburg",
    "Africa/Lagos",
    "Africa/Nairobi",
    "America/Argentina/Buenos_Aires",
    "America/Bogota",
    "America/Chicago",
    "America/Denver",
    "America/Lima",
    "America/Los_Angeles",
    "America/Mexico_City",
    "America/New_York",
    "America/Sao_Paulo",
    "America/Toronto",
    "America/Vancouver",
    "Asia/Bangkok",
    "Asia/Dubai",
    "Asia/Ho_Chi_Minh",
    "Asia/Hong_Kong",
    "Asia/Jakarta",
    "Asia/Karachi",
    "Asia/Kolkata",
    "Asia/Seoul",
    "Asia/Shanghai",
    "Asia/Singapore",
    "Asia/Tokyo",
    "Australia/Melbourne",
    "Australia/Sydney",
    "Europe/Amsterdam",
    "Europe/Berlin",
    "Europe/Istanbul",
    "Europe/London",
    "Europe/Moscow",
    "Europe/Paris",
    "Europe/Rome",
    "Pacific/Auckland",
    "Pacific/Fiji",
  ]
}

/** All timezone options sorted by region then label, with UTC first */
export function getTimezoneOptions(): TimezoneOption[] {
  const list = getTimezoneList()
  const options: TimezoneOption[] = list.map((value) => {
    const region = value.split("/")[0] ?? "Other"
    return { value, label: labelFor(value), region }
  })
  const utcFirst = options.filter((o) => o.value === "UTC")
  const rest = options.filter((o) => o.value !== "UTC").sort((a, b) => a.region.localeCompare(b.region) || a.label.localeCompare(b.label))
  return [...utcFirst, ...rest]
}

/** Grouped by region for SelectGroup display. UTC is its own group first. */
export function getTimezoneGroups(): { label: string; options: TimezoneOption[] }[] {
  const options = getTimezoneOptions()
  const groups = new Map<string, TimezoneOption[]>()
  for (const opt of options) {
    const groupLabel = opt.value === "UTC" ? "UTC" : opt.region
    if (!groups.has(groupLabel)) groups.set(groupLabel, [])
    groups.get(groupLabel)!.push(opt)
  }
  const order = ["UTC", "Africa", "America", "Asia", "Australia", "Europe", "Pacific"]
  const result: { label: string; options: TimezoneOption[] }[] = []
  for (const label of order) {
    if (groups.has(label)) result.push({ label, options: groups.get(label)! })
  }
  const remaining = [...groups.entries()].filter(([k]) => !order.includes(k))
  for (const [label, opts] of remaining.sort((a, b) => a[0].localeCompare(b[0]))) {
    result.push({ label, options: opts })
  }
  return result
}
