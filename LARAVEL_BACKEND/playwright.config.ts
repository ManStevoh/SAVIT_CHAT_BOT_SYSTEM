import { defineConfig, devices } from '@playwright/test'

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8080'

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'list',
  use: {
    baseURL,
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer:
    process.env.PLAYWRIGHT_BASE_URL || process.env.PLAYWRIGHT_SKIP_SERVER
      ? undefined
      : {
          command:
            process.env.PLAYWRIGHT_SERVER_CMD ??
            'php -S 127.0.0.1:8080 -t public',
          url: baseURL,
          cwd: process.cwd(),
          reuseExistingServer: !process.env.CI,
          timeout: 120_000,
        },
})
