import { defineConfig, devices } from '@playwright/test';

// Secure-e2e — TopwebChat timeline (fases /prototype → /tdd).
// Execução: E2E_BASE_URL=https://crm.exemplo E2E_ADMIN_EMAIL=... E2E_ADMIN_PASSWORD=...
//   E2E_USER_EMAIL=... E2E_USER_PASSWORD=... E2E_CONVERSATION_ID=... npx playwright test
// Pré-requisitos: stack dev com o código do PR, 1 conversa com mensagens, 1 admin com
// topweb_chat.* e 1 usuário SEM topweb_chat.inbox.view. Nunca commitar credenciais.
export default defineConfig({
  testDir: '.',
  testMatch: ['*.spec.func.ts', '*.spec.sec.ts'],
  fullyParallel: false,
  retries: 0,
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile', use: { ...devices['Pixel 7'] } },
  ],
});
