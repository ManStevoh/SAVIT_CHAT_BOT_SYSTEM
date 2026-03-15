'use client'

import { useSearchParams } from 'next/navigation'
import Link from 'next/link'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { CheckCircle, XCircle } from 'lucide-react'

/**
 * Shown after Stripe Checkout for an order: success_url or cancel_url redirects here.
 * Query: ?session_id=... (success) or ?cancelled=1 (cancelled).
 */
export default function OrderPaidPage() {
  const searchParams = useSearchParams()
  const cancelled = searchParams.get('cancelled') === '1'
  const sessionId = searchParams.get('session_id')

  return (
    <div className="min-h-screen flex items-center justify-center p-4 bg-muted/30">
      <Card className="w-full max-w-md">
        <CardHeader>
          {cancelled ? (
            <>
              <div className="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-amber-500/20">
                <XCircle className="h-6 w-6 text-amber-600" />
              </div>
              <CardTitle>Payment cancelled</CardTitle>
              <CardDescription>
                You cancelled the payment. You can complete the order by replying in WhatsApp or try the payment link again.
              </CardDescription>
            </>
          ) : sessionId ? (
            <>
              <div className="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-green-500/20">
                <CheckCircle className="h-6 w-6 text-green-600" />
              </div>
              <CardTitle>Payment received</CardTitle>
              <CardDescription>
                Thank you! Your payment was successful. We&apos;ll confirm your order in WhatsApp shortly.
              </CardDescription>
            </>
          ) : (
            <>
              <CardTitle>Order payment</CardTitle>
              <CardDescription>
                You can close this page. Return to your chat to see order status.
              </CardDescription>
            </>
          )}
        </CardHeader>
        <CardContent>
          <Button asChild variant="outline" className="w-full">
            <Link href="/">Back to home</Link>
          </Button>
        </CardContent>
      </Card>
    </div>
  )
}
