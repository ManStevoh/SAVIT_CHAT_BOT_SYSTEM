import { test, expect } from '@playwright/test'
import {
  loginAsAdmin,
  logoutFromApp,
  expectPageHeading,
  visitAndAssertPage,
} from './helpers/auth'

const adminPages: { path: string; heading: RegExp }[] = [
  { path: '/admin', heading: /platform overview/i },
  { path: '/admin/companies', heading: /^companies$/i },
  { path: '/admin/users', heading: /^users$/i },
  { path: '/admin/plans', heading: /^plans$/i },
  { path: '/admin/subscriptions', heading: /^subscriptions$/i },
  { path: '/admin/revenue', heading: /^revenue$/i },
  { path: '/admin/growth', heading: /growth portfolio/i },
  { path: '/admin/settings', heading: /platform settings/i },
  { path: '/admin/payment-gateways', heading: /payment gateways/i },
  { path: '/admin/testimonials', heading: /^testimonials$/i },
  { path: '/admin/landing-faqs', heading: /landing faq/i },
  { path: '/admin/ai-usage', heading: /ai usage/i },
  { path: '/admin/logs', heading: /system logs/i },
]

test.describe.configure({ mode: 'serial' })

test.describe('Full admin journey', () => {
  test('super admin visits every admin screen via sidebar and direct URL', async ({
    page,
  }) => {
    test.setTimeout(300_000)
    await loginAsAdmin(page)
    await expectPageHeading(page, /platform overview/i)

    for (const { path, heading } of adminPages) {
      await visitAndAssertPage(page, path, heading)
    }

    // Navigate via sidebar links (config items include subtitles in accessible name)
    await page.goto('/admin')
    const sidebar = page.locator('aside nav')
    for (const item of [
      'Companies',
      'Users',
      'Plans',
      'Subscriptions',
      'Revenue',
      'Growth Portfolio',
      'Settings',
      'Payment Gateways',
      'Testimonials',
      'Landing FAQ',
      'AI Usage',
      'System Logs',
    ]) {
      const link = sidebar.getByRole('link', { name: item })
      await link.scrollIntoViewIfNeeded()
      await link.click()
      await expect(link).toBeVisible()
    }

    await logoutFromApp(page)
  })
})
