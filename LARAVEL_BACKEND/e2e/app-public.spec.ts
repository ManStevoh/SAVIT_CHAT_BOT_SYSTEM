import { test, expect } from '@playwright/test'

test.describe('Public app pages', () => {
  test('marketing homepage loads', async ({ page }) => {
    await page.goto('/')
    await expect(page).toHaveTitle(/Savit Chat/i)
    await expect(
      page.getByRole('heading', {
        name: /turn whatsapp into your best sales channel/i,
      })
    ).toBeVisible()
  })

  test('admin growth page requires auth', async ({ page }) => {
    await page.goto('/admin/growth')
    await expect(page).toHaveURL(/\/login/)
  })

  test('growth route redirects unauthenticated users to login', async ({
    page,
  }) => {
    await page.goto('/dashboard/growth')
    await expect(page).toHaveURL(/\/login/)
  })
})
