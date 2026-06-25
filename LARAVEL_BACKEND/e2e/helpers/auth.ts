import { expect, type Page } from '@playwright/test'

export const companyEmail =
  process.env.PLAYWRIGHT_TEST_EMAIL ?? 'demo1@company.local'
export const companyPassword =
  process.env.PLAYWRIGHT_TEST_PASSWORD ?? 'password'
export const adminEmail =
  process.env.PLAYWRIGHT_TEST_ADMIN_EMAIL ?? 'superadmin@essem.local'
export const adminPassword =
  process.env.PLAYWRIGHT_TEST_ADMIN_PASSWORD ?? 'password'

export async function clearAuthStorage(page: Page) {
  await page.goto('/login')
  await page.evaluate(() => {
    localStorage.clear()
    sessionStorage.clear()
  })
}

export async function loginAsCompany(page: Page) {
  await page.goto('/login')
  await page.getByLabel(/^email$/i).fill(companyEmail)
  await page.getByLabel(/^password$/i).fill(companyPassword)
  await page.getByRole('button', { name: /sign in/i }).click()
  await page.waitForURL(/\/dashboard/, { timeout: 30_000 })
}

export async function loginAsAdmin(page: Page) {
  await page.goto('/login')
  await page.getByLabel(/^email$/i).fill(adminEmail)
  await page.getByLabel(/^password$/i).fill(adminPassword)
  await page.getByRole('button', { name: /sign in/i }).click()
  await page.waitForURL(/\/admin/, { timeout: 30_000 })
}

export async function logoutFromApp(page: Page) {
  await page.getByRole('button').filter({ hasText: /account|owner|admin|demo|super/i }).first().click()
  await page.getByRole('menuitem', { name: /log out/i }).click()
  await expect(
    page.getByRole('heading', { name: /welcome back/i })
  ).toBeVisible({ timeout: 15_000 })
}

export async function expectPageHeading(page: Page, pattern: RegExp) {
  await expect(page.getByRole('heading', { level: 1, name: pattern }).first()).toBeVisible({
    timeout: 20_000,
  })
}

export async function visitAndAssertPage(
  page: Page,
  path: string,
  heading: RegExp
) {
  await page.goto(path, { waitUntil: 'domcontentloaded' })
  await expect(page).toHaveURL(new RegExp(`${path.replace('/', '\\/')}(\\?|$)`))
  if (path === '/dashboard') {
    await expect(
      page.getByText(/happening with your business today/i)
    ).toBeVisible({ timeout: 20_000 })
    return
  }
  await expectPageHeading(page, heading)
}
