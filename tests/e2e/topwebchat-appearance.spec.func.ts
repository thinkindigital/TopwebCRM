import { expect, test } from '@playwright/test';

// E14-R2 S8 — Aparência R1/R1K pela UI administrativa (também valida S7 E2E).
// Requer env: E2E_BASE_URL, E2E_ADMIN_EMAIL, E2E_ADMIN_PASSWORD.
// Ao final, restaura R1K. Screenshots por estilo em test-results (CI artifacts).

async function loginAsAdmin(page: any) {
  await page.goto('/admin/login');
  await page.locator('input[name="email"]').fill(process.env.E2E_ADMIN_EMAIL ?? '');
  await page.locator('input[name="password"]').fill(process.env.E2E_ADMIN_PASSWORD ?? '');
  await page.locator('form button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);
}

async function workspaceStyle(page: any) {
  await page.goto('/admin/topweb-chat?queue=all');
  await page.locator('[data-topwebchat-workspace]').waitFor();
  const href = await page.locator('a.twp-row[href*="/admin/topweb-chat/conversations/"]').first().getAttribute('href');
  await page.goto(href as string);
  await page.locator('[data-topwebchat-workspace]').waitFor();
  return page.locator('[data-topwebchat-workspace]').getAttribute('data-workspace-style');
}

async function setStyle(page: any, value: 'R1' | 'R1K') {
  await page.goto('/admin/configuration/topwebchat/appearance');
  const select = page.locator('select[name="topwebchat[appearance][style][workspace_style]"]').first();
  await expect(select).toBeVisible({ timeout: 15000 });
  await select.selectOption(value);
  await page.locator('button.primary-button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
  await page.goto('/admin/configuration/topwebchat/appearance');
  await expect(page.locator('select[name="topwebchat[appearance][style][workspace_style]"]')).toHaveValue(value);
}

test('alterna R1K, R1 e restaura R1K pela configuração', async ({ page }) => {
  await loginAsAdmin(page);
  expect(await workspaceStyle(page)).toBe('R1K');

  await setStyle(page, 'R1');
  expect(await workspaceStyle(page)).toBe('R1');
  await page.screenshot({ path: 'test-results/appearance-r1.png' });

  await setStyle(page, 'R1K');
  expect(await workspaceStyle(page)).toBe('R1K');
  await page.screenshot({ path: 'test-results/appearance-r1k.png' });
});
