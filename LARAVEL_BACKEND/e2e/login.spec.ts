import { test, expect } from '@playwright/test'

const companyEmail =
  process.env.PLAYWRIGHT_TEST_EMAIL ?? 'demo1@company.local'
const companyPassword = process.env.PLAYWRIGHT_TEST_PASSWORD ?? 'password'
const adminEmail =
  process.env.PLAYWRIGHT_TEST_ADMIN_EMAIL ?? 'superadmin@savit.local'
const adminPassword =
  process.env.PLAYWRIGHT_TEST_ADMIN_PASSWORD ?? 'password'

test.describe('Login flow', () => {
  test('login page loads', async ({ page }) => {
    await page.goto('/login')
    await expect(page.getByRole('heading', { name: /welcome back/i })).toBeVisible()
    await expect(page.getByLabel(/^email$/i)).toBeVisible()
    await expect(page.getByLabel(/^password$/i)).toBeVisible()
    await expect(page.getByRole('button', { name: /sign in/i })).toBeVisible()
  })

  test('protected routes redirect unauthenticated users to login', async ({
    page,
  }) => {
    await page.goto('/dashboard')
    await expect(page).toHaveURL(/\/login/)

    await page.goto('/admin')
    await expect(page).toHaveURL(/\/login/)

    await page.goto('/dashboard/growth')
    await expect(page).toHaveURL(/\/login/)
  })

  test('company user can sign in and reach dashboard', async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel(/^email$/i).fill(companyEmail)
    await page.getByLabel(/^password$/i).fill(companyPassword)
    await page.getByRole('button', { name: /sign in/i }).click()
    await page.waitForURL(/\/dashboard/, { timeout: 30_000 })
    await expect(
      page.getByText(/happening with your business today/i)
    ).toBeVisible({ timeout: 15_000 })
  })

  test('admin user can sign in and reach admin', async ({ page }) => {
    await page.goto('/login')
    await page.getByLabel(/^email$/i).fill(adminEmail)
    await page.getByLabel(/^password$/i).fill(adminPassword)
    await page.getByRole('button', { name: /sign in/i }).click()
    await page.waitForURL(/\/admin/, { timeout: 30_000 })
    await expect(
      page.getByRole('heading', { name: /platform overview/i })
    ).toBeVisible({ timeout: 15_000 })
  })
})
