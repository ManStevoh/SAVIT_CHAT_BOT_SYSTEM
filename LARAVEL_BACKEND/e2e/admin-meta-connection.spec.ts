import { test, expect } from '@playwright/test'
import { loginAsAdmin, loginAsCompany } from './helpers/auth'

test.describe('Admin Meta connection tester', () => {
  test('settings integrations tests Meta and names the rejected credential', async ({
    page,
  }) => {
    test.setTimeout(180_000)
    await loginAsAdmin(page)
    await page.goto('/admin/settings?tab=integrations')
    await expect(page.getByRole('heading', { name: /platform settings/i })).toBeVisible({
      timeout: 20_000,
    })

    const testButton = page.getByRole('button', { name: /test meta connection/i })
    await expect(testButton).toBeVisible({ timeout: 20_000 })

    await testButton.click()
    await expect(page.getByText(/meta connection failed/i)).toBeVisible({ timeout: 30_000 })
    await expect(page.getByText(/meta app id is missing/i)).toBeVisible()
    await expect(page.getByText(/meta app id/i).filter({ hasText: /rejected/i })).toBeVisible()

    await page.getByLabel(/meta app id \(embedded signup\)/i).fill('111111111111111')
    await page.getByLabel(/meta app secret \(for token exchange\)/i).fill('e2e-wrong-secret')
    await testButton.click()
    await expect(page.getByText(/meta connection failed/i)).toBeVisible({ timeout: 45_000 })
    await expect(page.getByText(/meta rejected the app id/i)).toBeVisible()
    await expect(page.getByText(/meta app id/i).filter({ hasText: /rejected/i })).toBeVisible()

    await page.getByLabel(/meta app id \(embedded signup\)/i).fill('846055524940193')
    await page.getByLabel(/meta app secret \(for token exchange\)/i).fill('e2e-wrong-secret')
    await testButton.click()
    await expect(page.getByText(/meta connection failed/i)).toBeVisible({ timeout: 45_000 })
    await expect(
      page.getByText(/meta rejected the app secret used for token exchange|meta rejected the app id/i)
    ).toBeVisible()
    await expect(page.getByText(/rejected/i).first()).toBeVisible()
  })

  test('company user cannot call the Meta tester', async ({ page }) => {
    await loginAsCompany(page)
    const token = await page.evaluate(
      () => localStorage.getItem('auth_token') ?? sessionStorage.getItem('auth_token')
    )
    expect(token).toBeTruthy()

    const response = await page.request.post('/api/admin/settings/test-meta', {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
      data: {
        whatsappEmbeddedAppId: '846055524940193',
      },
    })
    expect(response.status()).toBe(403)
  })
})
