import { test, expect } from '@playwright/test'
import { loginAsCompany, loginAsAdmin } from './helpers/auth'

test.describe('WhatsApp settings UI', () => {
  test('company settings shows WhatsApp tab with connect or connected state', async ({ page }) => {
    await loginAsCompany(page)
    await page.goto('/dashboard/settings')
    await page.getByRole('tab', { name: /whatsapp/i }).click()
    await expect(page.getByText(/whatsapp business/i).first()).toBeVisible({ timeout: 15_000 })
    const connectBtn = page.getByRole('button', { name: /connect with facebook/i })
    const connectedBadge = page.getByText(/^connected$/i)
    const notConfigured = page.getByText(/whatsapp signup is not configured/i)
    await expect(connectBtn.or(connectedBadge).or(notConfigured).first()).toBeVisible({ timeout: 15_000 })
  })

  test('admin whatsapp monitor page loads', async ({ page }) => {
    await loginAsAdmin(page)
    await page.goto('/admin/whatsapp')
    await expect(page.getByRole('heading', { name: /whatsapp connections/i })).toBeVisible({
      timeout: 15_000,
    })
    await expect(page.getByText(/platform meta configuration/i)).toBeVisible()
  })
})
