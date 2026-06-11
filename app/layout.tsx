import type { Metadata, Viewport } from 'next'
import { Plus_Jakarta_Sans, Instrument_Serif, Geist_Mono } from 'next/font/google'
import { Analytics } from '@vercel/analytics/next'
import { AppBrandingProvider } from '@/components/providers/AppBrandingProvider'
import { ThemeProvider } from '@/components/theme-provider'
import { Toaster } from '@/components/ui/toaster'
import { Toaster as SonnerToaster } from '@/components/ui/sonner'
import './globals.css'

const plusJakarta = Plus_Jakarta_Sans({
  subsets: ['latin'],
  variable: '--font-body',
  weight: ['400', '500', '600', '700'],
})

const instrumentSerif = Instrument_Serif({
  subsets: ['latin'],
  variable: '--font-display-family',
  weight: ['400'],
})

const geistMono = Geist_Mono({
  subsets: ['latin'],
  variable: '--font-geist-mono',
})

export const metadata: Metadata = {
  title: 'Savit Chat - Automate Your WhatsApp Business',
  description: 'AI-powered WhatsApp automation platform for businesses. Automatically reply to customers, manage orders, and analyze conversations.',
  keywords: ['WhatsApp automation', 'AI chatbot', 'business messaging', 'customer engagement', 'order management'],
}

export const viewport: Viewport = {
  themeColor: '#fafaf9',
  width: 'device-width',
  initialScale: 1,
}

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  return (
    <html lang="en" suppressHydrationWarning>
      <body className={`${plusJakarta.variable} ${instrumentSerif.variable} ${geistMono.variable} font-sans antialiased`}>
        <ThemeProvider attribute="class" defaultTheme="light" enableSystem={false} storageKey="savit-theme">
          <AppBrandingProvider>
            {children}
            <Toaster />
            <SonnerToaster position="top-right" richColors closeButton />
            <Analytics />
          </AppBrandingProvider>
        </ThemeProvider>
      </body>
    </html>
  )
}
