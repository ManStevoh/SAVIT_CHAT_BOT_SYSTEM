import { test, expect } from '@playwright/test'

async function clearAuthStorage(page: import('@playwright/test').Page) {
  await page.goto('/login')
  await page.evaluate(() => {
    localStorage.clear()
    sessionStorage.clear()
  })
}

test.describe('Public app pages', () => {
  test.beforeEach(async ({ page }) => {
    await clearAuthStorage(page)
  })
  test('marketing homepage loads', async ({ page }) => {
    await page.goto('/')
    await expect(page).toHaveTitle(/RelayIQ/i)
    await expect(
      page.getByRole('heading', {
        name: /sell on whatsapp/i,
      })
    ).toBeVisible({ timeout: 15_000 })
  })

  test('admin growth page requires auth', async ({ page }) => {
    await page.goto('/admin/growth')
    await expect(
      page.getByRole('heading', { name: /welcome back/i })
    ).toBeVisible({ timeout: 15_000 })
  })

  test('growth route redirects unauthenticated users to login', async ({
    page,
  }) => {
    await page.goto('/dashboard/growth')
    await expect(
      page.getByRole('heading', { name: /welcome back/i })
    ).toBeVisible({ timeout: 15_000 })
  })
})
