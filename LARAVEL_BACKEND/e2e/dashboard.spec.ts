import { test, expect } from '@playwright/test'

const email = process.env.PLAYWRIGHT_TEST_EMAIL ?? 'demo1@company.local'
const password = process.env.PLAYWRIGHT_TEST_PASSWORD ?? 'password'

test.describe('Dashboard (authenticated)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel(/^email$/i).fill(email)
    await page.getByLabel(/^password$/i).fill(password)
    await page.getByRole('button', { name: /sign in/i }).click()
    await page.waitForURL(/\/dashboard/, { timeout: 30_000 })
  })

  test('dashboard home loads with overview content', async ({ page }) => {
    await page.goto('/dashboard')
    await expect(
      page.getByText(/happening with your business today/i)
    ).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText(/messages/i).first()).toBeVisible()
  })

  test('growth dashboard loads with onboarding or KPIs', async ({ page }) => {
    await page.goto('/dashboard/growth')
    await expect(
      page.getByRole('heading', { name: /growth engine/i })
    ).toBeVisible({ timeout: 15_000 })
    await expect(
      page
        .getByText(/pilot onboarding|leads|attributed revenue|sample data/i)
        .first()
    ).toBeVisible()
  })
})
