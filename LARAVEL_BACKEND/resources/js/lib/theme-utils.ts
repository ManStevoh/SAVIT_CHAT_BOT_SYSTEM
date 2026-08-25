export type BrandTheme = {
  primary_color?: string | null
  accent_color?: string | null
  bg_color?: string | null
  font_family?: string | null
  border_radius?: string | null
  announcement_bar?: string | null
  announcement_bar_bg?: string | null
  announcement_bar_text?: string | null
  footer_text?: string | null
  seo_title?: string | null
  seo_description?: string | null
}

export type FontOption = {
  value: string
  label: string
  fontFamily: string
}

export const FONT_OPTIONS: FontOption[] = [
  { value: 'sans', label: 'Modern Sans (Inter / System)', fontFamily: "'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" },
  { value: 'plus_jakarta', label: 'Tech Geometric (Plus Jakarta Sans)', fontFamily: "'Plus Jakarta Sans', 'Inter', system-ui, sans-serif" },
  { value: 'serif', label: 'Luxury Serif (Playfair / Georgia)', fontFamily: "'Playfair Display', Georgia, Cambria, 'Times New Roman', serif" },
  { value: 'outfit', label: 'Friendly Rounded (Outfit / Poppins)', fontFamily: "'Outfit', 'Poppins', system-ui, sans-serif" },
  { value: 'mono', label: 'Clean Mono (JetBrains Mono)', fontFamily: "'JetBrains Mono', 'Fira Code', Menlo, Monaco, Consolas, monospace" },
]

export type RadiusOption = {
  value: string
  label: string
  radius: string
  className: string
}

export const RADIUS_OPTIONS: RadiusOption[] = [
  { value: 'none', label: 'Sharp (0px)', radius: '0px', className: 'rounded-none' },
  { value: 'sm', label: 'Subtle (6px)', radius: '6px', className: 'rounded-md' },
  { value: 'md', label: 'Modern (12px)', radius: '12px', className: 'rounded-xl' },
  { value: 'full', label: 'Pill / Full (9999px)', radius: '9999px', className: 'rounded-full' },
]

export type ColorPreset = {
  name: string
  primary: string
  accent: string
  description: string
}

export const COLOR_PRESETS: ColorPreset[] = [
  { name: 'Relay Modern', primary: '#2563eb', accent: '#3b82f6', description: 'Classic high-contrast tech blue' },
  { name: 'Emerald Forest', primary: '#10b981', accent: '#059669', description: 'Fresh, organic & trustworthy green' },
  { name: 'Violet Luxury', primary: '#8b5cf6', accent: '#7c3aed', description: 'Creative, premium & vibrant purple' },
  { name: 'Sunset Glow', primary: '#f43f5e', accent: '#fb7185', description: 'Bold coral & energetic rose' },
  { name: 'Amber Artisan', primary: '#f59e0b', accent: '#d97706', description: 'Warm, culinary & crafted amber' },
  { name: 'Midnight Obsidian', primary: '#0f172a', accent: '#334155', description: 'Sleek luxury & dark minimal slate' },
  { name: 'Ocean Teal', primary: '#0d9488', accent: '#14b8a6', description: 'Calm, crisp & modern cyan/teal' },
  { name: 'Rose Blossom', primary: '#e11d48', accent: '#f43f5e', description: 'Chic, fashionable retail pink' },
  { name: 'Terracotta Earth', primary: '#c2410c', accent: '#ea580c', description: 'Warm lifestyle, coffee & pottery goods' },
]

export function resolveStorefrontStyle(theme?: BrandTheme | null): React.CSSProperties {
  const t = theme || {}
  const fontOpt = FONT_OPTIONS.find((f) => f.value === t.font_family)
  const radiusOpt = RADIUS_OPTIONS.find((r) => r.value === t.border_radius)

  return {
    ['--sf-primary' as string]: t.primary_color || '#0f172a',
    ['--sf-accent' as string]: t.accent_color || t.primary_color || '#0f172a',
    ['--sf-bg' as string]: t.bg_color || '#ffffff',
    ['--sf-font' as string]: fontOpt?.fontFamily || "'Inter', system-ui, -apple-system, sans-serif",
    ['--sf-radius' as string]: radiusOpt?.radius || '12px',
    fontFamily: fontOpt?.fontFamily,
  }
}
