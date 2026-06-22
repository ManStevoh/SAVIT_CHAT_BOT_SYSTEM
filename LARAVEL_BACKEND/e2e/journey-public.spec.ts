import { test, expect } from '@playwright/test'
import { clearAuthStorage, loginAsCompany } from './helpers/auth'

test.describe.configure({ mode: 'serial' })

test.describe('Full public & auth journey', () => {
  test('landing → register → forgot password → login → order-paid page', async ({
    page,
  }) => {
    test.setTimeout(120_000)
    await clearAuthStorage(page)

    await page.goto('/')
    await expect(
      page.getByRole('heading', {
        name: /turn whatsapp into your best sales channel/i,
      })
    ).toBeVisible({ timeout: 15_000 })

    await page.goto('/register')
    await expect(page.getByRole('heading', { name: /create your account/i })).toBeVisible()

    await page.goto('/forgot-password')
    await expect(page.getByRole('heading', { name: /forgot password/i })).toBeVisible()

    await page.goto('/login')
    await expect(page.getByRole('heading', { name: /welcome back/i })).toBeVisible()

    await loginAsCompany(page)
    await expect(
      page.getByText(/happening with your business today/i)
    ).toBeVisible({ timeout: 20_000 })

    await page.goto('/order-paid?session_id=test_session')
    await expect(page.getByText(/payment received|thank you/i).first()).toBeVisible({
      timeout: 15_000,
    })
  })
})
