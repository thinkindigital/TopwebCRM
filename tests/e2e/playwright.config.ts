import { defineConfig, devices } from '@playwright/test';

// Secure-e2e — TopwebChat timeline (fases /prototype → /tdd).
// Execução: E2E_BASE_URL=https://crm.exemplo E2E_EXPECTED_BUILD_SHA=<sha>
//   E2E_ADMIN_EMAIL=... E2E_ADMIN_PASSWORD=... E2E_USER_EMAIL=... E2E_USER_PASSWORD=...
//   E2E_FIXTURE_PASSWORD=... npx playwright test
// Pré-requisitos: stack dev com o código do PR, 1 conversa com mensagens, 1 admin com
// topweb_chat.* e 1 usuário SEM topweb_chat.inbox.view. Nunca commitar credenciais.
export default defineConfig({
  testDir: '.',
  testMatch: ['*.spec.func.ts', '*.spec.sec.ts'],
  globalSetup: './global-setup.ts',
  globalTeardown: './global-teardown.ts',
  fullyParallel: false,
  // All projects share one isolated database fixture; serialize them to avoid
  // cross-project mutations such as claims and owner transfers.
  workers: 1,
  retries: 0,
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile', use: { ...devices['Pixel 7'] } },
    { name: 'wide', use: { ...devices['Desktop Chrome'], viewport: { width: 1920, height: 1080 } } },
    { name: 'notebook', use: { ...devices['Desktop Chrome'], viewport: { width: 1366, height: 768 } } },
  ],
});
