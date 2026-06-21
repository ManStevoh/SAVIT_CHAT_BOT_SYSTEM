import { test, expect } from '@playwright/test'

test.describe('Growth Engine dashboard', () => {
  test('marketing homepage loads', async ({ page }) => {
    await page.goto('/')
    await expect(page).toHaveTitle(/Savit Chat/i)
  })

  test('admin growth page requires auth', async ({ page }) => {
    await page.goto('/admin/growth')
    await expect(page).toHaveURL(/login/)
  })

  test('growth route redirects unauthenticated users to login', async ({ page }) => {
    await page.goto('/dashboard/growth')
    await expect(page).toHaveURL(/login/)
  })

  test('growth page supports tab query param in URL', async ({ page }) => {
    await page.goto('/dashboard/growth?tab=platforms')
    await expect(page).toHaveURL(/login/)
  })
})
