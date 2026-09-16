import { expect, test } from '@playwright/test';
import { requiredEnv } from './fixture';

async function login(page: any, email: string, password: string) {
  await page.goto('/admin/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);
}

test('Build Info prova o SHA esperado e não expõe ambiente', async ({ page }) => {
  await login(
    page,
    requiredEnv('E2E_ADMIN_EMAIL'),
    requiredEnv('E2E_ADMIN_PASSWORD')
  );

  const response = await page.request.get('/admin/topweb-chat/settings/build-info', {
    headers: { Accept: 'application/json' },
  });
  expect(response.status()).toBe(200);
  const body = await response.json();
  expect(body.application_commit_sha).toBe(requiredEnv('E2E_EXPECTED_BUILD_SHA'));
  expect(body.branch).toBe('dev');
  expect(body.image_revision).toContain(body.application_commit_sha);
  expect(JSON.stringify(body)).not.toMatch(/password|token|api[_-]?key|secret|APP_KEY/i);
});

test('Build Info permanece restrito a administrador', async ({ page }) => {
  await login(
    page,
    requiredEnv('E2E_USER_EMAIL'),
    requiredEnv('E2E_USER_PASSWORD')
  );

  const response = await page.request.get('/admin/topweb-chat/settings/build-info', {
    headers: { Accept: 'application/json' },
  });
  expect(response.status()).toBe(403);
});
