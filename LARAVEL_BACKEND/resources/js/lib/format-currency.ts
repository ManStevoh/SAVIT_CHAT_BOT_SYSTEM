/** ISO 4217 codes for store / catalog display (dashboard + WhatsApp). */
export const CATALOG_CURRENCY_OPTIONS: { code: string; label: string }[] = [
  { code: 'USD', label: 'US Dollar' },
  { code: 'EUR', label: 'Euro' },
  { code: 'GBP', label: 'British Pound' },
  { code: 'KES', label: 'Kenyan Shilling' },
  { code: 'UGX', label: 'Ugandan Shilling' },
  { code: 'TZS', label: 'Tanzanian Shilling' },
  { code: 'RWF', label: 'Rwandan Franc' },
  { code: 'NGN', label: 'Nigerian Naira' },
  { code: 'GHS', label: 'Ghanaian Cedi' },
  { code: 'ZAR', label: 'South African Rand' },
  { code: 'EGP', label: 'Egyptian Pound' },
  { code: 'MAD', label: 'Moroccan Dirham' },
  { code: 'AED', label: 'UAE Dirham' },
  { code: 'SAR', label: 'Saudi Riyal' },
  { code: 'INR', label: 'Indian Rupee' },
  { code: 'CNY', label: 'Chinese Yuan' },
  { code: 'JPY', label: 'Japanese Yen' },
  { code: 'AUD', label: 'Australian Dollar' },
  { code: 'CAD', label: 'Canadian Dollar' },
  { code: 'CHF', label: 'Swiss Franc' },
  { code: 'SEK', label: 'Swedish Krona' },
  { code: 'NOK', label: 'Norwegian Krone' },
  { code: 'DKK', label: 'Danish Krone' },
  { code: 'PLN', label: 'Polish Złoty' },
  { code: 'BRL', label: 'Brazilian Real' },
  { code: 'MXN', label: 'Mexican Peso' },
  { code: 'SGD', label: 'Singapore Dollar' },
  { code: 'HKD', label: 'Hong Kong Dollar' },
  { code: 'NZD', label: 'New Zealand Dollar' },
  { code: 'THB', label: 'Thai Baht' },
  { code: 'PHP', label: 'Philippine Peso' },
]

export type CurrencyDisplayOptions = {
  symbol?: string | null
  thousandsSeparator?: string | null
  decimalSeparator?: string | null
}

export function currencyDisplayFromSettings(
  settings?: {
    currencySymbol?: string | null
    thousandsSeparator?: string | null
    decimalSeparator?: string | null
  } | null
): CurrencyDisplayOptions {
  return {
    symbol: settings?.currencySymbol,
    thousandsSeparator: settings?.thousandsSeparator,
    decimalSeparator: settings?.decimalSeparator,
  }
}

export function normalizeCurrencyCode(code: string | undefined | null): string {
  const raw = (code ?? 'USD').replace(/[^A-Za-z]/g, '').toUpperCase()
  return raw.length >= 3 ? raw.slice(0, 3) : 'USD'
}

export function normalizeThousandsSeparator(value: string | undefined | null): string {
  if (value === '.' || value === ',' || value === ' ' || value === "'") return value
  return ','
}

export function normalizeDecimalSeparator(
  value: string | undefined | null,
  thousands: string
): string {
  let decimal = value === '.' || value === ',' ? value : thousands === '.' ? ',' : '.'
  if (decimal === thousands) {
    decimal = thousands === ',' ? '.' : ','
  }
  return decimal
}

export function pairedDecimalForThousands(thousands: string): string {
  return thousands === '.' ? ',' : '.'
}

export function formatCurrencyAmount(
  value: number,
  currencyCode: string | undefined | null,
  options?: CurrencyDisplayOptions
): string {
  const code = normalizeCurrencyCode(currencyCode)
  const symbolRaw = options?.symbol != null ? String(options.symbol).trim() : ''
  const hasSymbol = symbolRaw !== ''
  const thousandsProvided = options?.thousandsSeparator != null
  const decimalProvided = options?.decimalSeparator != null
  const thousands = normalizeThousandsSeparator(options?.thousandsSeparator)
  const decimal = normalizeDecimalSeparator(options?.decimalSeparator, thousands)
  const hasCustom =
    hasSymbol ||
    (thousandsProvided && thousands !== ',') ||
    (decimalProvided && decimal !== '.')

  if (!hasCustom) {
    try {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: code,
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
      }).format(value)
    } catch {
      return `${code} ${value.toFixed(2)}`
    }
  }

  const symbol = hasSymbol ? symbolRaw : code
  const zeroDecimal = ['JPY', 'KRW', 'VND', 'CLP'].includes(code)
  const abs = Math.abs(value)
  const fixed = abs.toFixed(zeroDecimal ? 0 : 2)
  const [intPartRaw, fracPart = ''] = fixed.split('.')
  const withThousands = intPartRaw.replace(/\B(?=(\d{3})+(?!\d))/g, thousands)
  const number = zeroDecimal
    ? withThousands
    : `${withThousands}${decimal}${fracPart}`
  return `${symbol} ${value < 0 ? '-' : ''}${number}`
}
