'use client'

import { Suspense } from 'react'
import { useSearchParams } from 'next/navigation'
import Link from 'next/link'
import { Head } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { CheckCircle, XCircle } from 'lucide-react'
import { LandoNavbar } from '@/components/lando/navbar'
import { LandoFooter } from '@/components/lando/footer'
import { useCmsGlobal } from '@/lib/api-hooks'
import type { CmsLink, CmsSection } from '@/components/lando/types'

function getSectionContent(sections: CmsSection[], key: string) {
  return sections.find((s) => s.key === key)?.content ?? {}
}

function OrderPaidContent() {
  const searchParams = useSearchParams()
  const cancelled = searchParams.get('cancelled') === '1'
  const sessionId = searchParams.get('session_id')
  const { data: globalData } = useCmsGlobal()
  const globalSections = globalData?.sections ?? []
  const navbarContent = getSectionContent(globalSections, 'navbar')
  const footerContent = getSectionContent(globalSections, 'footer')

  return (
    <>
      <Head title="Order payment — RelayIQ" />
      <div className="lando-page min-h-screen bg-[#f3f4f6]">
        <LandoNavbar
          links={(navbarContent.links as CmsLink[]) ?? []}
          loginLabel={String(navbarContent.loginLabel ?? 'Log in')}
          loginHref={String(navbarContent.loginHref ?? '/login')}
          signupLabel={String(navbarContent.signupLabel ?? 'Sign up')}
          signupHref={String(navbarContent.signupHref ?? '/register')}
        />
        <div className="flex min-h-[60vh] items-center justify-center px-4 py-28">
          <div className="w-full max-w-md rounded-3xl bg-white p-8 text-center shadow-sm">
            {cancelled ? (
              <>
                <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100">
                  <XCircle className="h-7 w-7 text-amber-600" />
                </div>
                <h1 className="text-2xl font-bold text-black">Payment cancelled</h1>
                <p className="mt-3 text-gray-600">
                  You cancelled the payment. You can complete the order by replying in WhatsApp or try the payment link again.
                </p>
              </>
            ) : sessionId ? (
              <>
                <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-100">
                  <CheckCircle className="h-7 w-7 text-green-600" />
                </div>
                <h1 className="text-2xl font-bold text-black">Payment received</h1>
                <p className="mt-3 text-gray-600">
                  Thank you! Your payment was successful. We&apos;ll confirm your order in WhatsApp shortly.
                </p>
              </>
            ) : (
              <>
                <h1 className="text-2xl font-bold text-black">Order payment</h1>
                <p className="mt-3 text-gray-600">
                  You can close this page. Return to your chat to see order status.
                </p>
              </>
            )}
            <Button asChild className="mt-8 h-11 w-full rounded-lg bg-[#2563eb] text-white hover:bg-[#1d4ed8]">
              <Link href="/">Back to home</Link>
            </Button>
          </div>
        </div>
        <LandoFooter
          copyright={String(footerContent.copyright ?? '')}
          navLinks={(footerContent.navLinks as CmsLink[]) ?? []}
          socialLinks={(footerContent.socialLinks as CmsLink[]) ?? []}
          legalLinks={(footerContent.legalLinks as CmsLink[]) ?? []}
        />
      </div>
    </>
  )
}

export default function OrderPaidPage() {
  return (
    <Suspense>
      <OrderPaidContent />
    </Suspense>
  )
}
