import { test, expect } from '@playwright/test'

const email =
  process.env.PLAYWRIGHT_TEST_ADMIN_EMAIL ?? 'superadmin@essem.local'
const password =
  process.env.PLAYWRIGHT_TEST_ADMIN_PASSWORD ?? 'password'

test.describe('Admin (authenticated)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel(/^email$/i).fill(email)
    await page.getByLabel(/^password$/i).fill(password)
    await page.getByRole('button', { name: /sign in/i }).click()
    await page.waitForURL(/\/admin/, { timeout: 30_000 })
  })

  test('admin overview loads', async ({ page }) => {
    await page.goto('/admin')
    await expect(
      page.getByRole('heading', { name: /platform overview/i })
    ).toBeVisible({ timeout: 15_000 })
    await expect(
      page.getByText(/monitor your platform performance/i)
    ).toBeVisible()
  })

  test('admin growth page loads', async ({ page }) => {
    await page.goto('/admin/growth')
    await expect(
      page.getByRole('heading', { name: /growth portfolio/i })
    ).toBeVisible({ timeout: 15_000 })
  })
})
