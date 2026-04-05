import type { Metadata, Viewport } from 'next'
import { Inter, Geist_Mono } from 'next/font/google'
import { Analytics } from '@vercel/analytics/next'
import { AppBrandingProvider } from '@/components/providers/AppBrandingProvider'
import { ThemeProvider } from '@/components/theme-provider'
import { Toaster } from '@/components/ui/toaster'
import './globals.css'

const inter = Inter({ 
  subsets: ["latin"],
  variable: "--font-inter"
});

const geistMono = Geist_Mono({ 
  subsets: ["latin"],
  variable: "--font-geist-mono"
});

export const metadata: Metadata = {
  title: 'Savit Chat - Automate Your WhatsApp Business',
  description: 'AI-powered WhatsApp automation platform for businesses. Automatically reply to customers, manage orders, and analyze conversations.',
  keywords: ['WhatsApp automation', 'AI chatbot', 'business messaging', 'customer engagement', 'order management'],
}

export const viewport: Viewport = {
  themeColor: '#0f172a',
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
      <body className={`${inter.variable} ${geistMono.variable} font-sans antialiased`}>
        <ThemeProvider attribute="class" defaultTheme="dark" enableSystem storageKey="savit-theme">
          <AppBrandingProvider>
            {children}
            <Toaster />
            <Analytics />
          </AppBrandingProvider>
        </ThemeProvider>
      </body>
    </html>
  )
}
