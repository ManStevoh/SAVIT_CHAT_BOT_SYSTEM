import { test, expect } from '@playwright/test'
import {
  loginAsCompany,
  logoutFromApp,
  visitAndAssertPage,
} from './helpers/auth'

const companyPages: { path: string; heading: RegExp }[] = [
  { path: '/dashboard', heading: /good (morning|afternoon|evening)|happening with your business/i },
  { path: '/dashboard/chats', heading: /^chats$/i },
  { path: '/dashboard/customers', heading: /^customers$/i },
  { path: '/dashboard/orders', heading: /^orders$/i },
  { path: '/dashboard/products', heading: /^products$/i },
  { path: '/dashboard/faq', heading: /faq automation/i },
  { path: '/dashboard/analytics', heading: /^analytics$/i },
  { path: '/dashboard/growth', heading: /growth engine/i },
  { path: '/dashboard/subscription', heading: /^subscription$/i },
  { path: '/dashboard/settings', heading: /^settings$/i },
]

test.describe.configure({ mode: 'serial' })

test.describe('Full company user journey', () => {
  test('company owner visits every dashboard screen', async ({ page }) => {
    test.setTimeout(300_000)
    await loginAsCompany(page)
    await expect(
      page.getByText(/happening with your business today/i)
    ).toBeVisible({ timeout: 15_000 })

    for (const { path, heading } of companyPages) {
      await visitAndAssertPage(page, path, heading)
    }

    // Sidebar navigation
    await page.goto('/dashboard')
    const sidebar = page.locator('aside nav')
    for (const item of [
      'Chats',
      'Customers',
      'Orders',
      'Products',
      'FAQ Automation',
      'Analytics',
      'Growth Engine',
      'Subscription',
      'Settings',
    ]) {
      await sidebar.getByRole('link', { name: item }).click()
      await expect(sidebar.getByRole('link', { name: item })).toBeVisible()
    }

    await logoutFromApp(page)
  })
})
