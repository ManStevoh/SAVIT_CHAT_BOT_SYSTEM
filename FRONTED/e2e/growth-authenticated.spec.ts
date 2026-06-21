import { test, expect } from '@playwright/test'

const email = process.env.PLAYWRIGHT_TEST_EMAIL
const password = process.env.PLAYWRIGHT_TEST_PASSWORD

test.describe('Growth Engine (authenticated)', () => {
  test.skip(!email || !password, 'Set PLAYWRIGHT_TEST_EMAIL and PLAYWRIGHT_TEST_PASSWORD to run')

  test.beforeEach(async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel(/email/i).fill(email!)
    await page.getByLabel(/password/i).fill(password!)
    await page.getByRole('button', { name: /sign in|log in/i }).click()
    await page.waitForURL(/dashboard/, { timeout: 30_000 })
  })

  test('growth dashboard loads with onboarding or KPIs', async ({ page }) => {
    await page.goto('/dashboard/growth')
    await expect(page.getByRole('heading', { name: /Growth Engine/i })).toBeVisible({ timeout: 15_000 })
    await expect(
      page.getByText(/Pilot onboarding|Leads|Attributed revenue|Sample data/i).first()
    ).toBeVisible()
  })

  test('growth content tab shows generator', async ({ page }) => {
    await page.goto('/dashboard/growth?tab=content')
    await expect(page.getByText(/AI content generator/i)).toBeVisible({ timeout: 15_000 })
  })
})
