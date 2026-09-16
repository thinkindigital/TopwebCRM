import { expect, test } from '@playwright/test';

// Follow-ups do ledger E14: matriz viewport/dark/teclado (V-01–V-05, V-07, V-08).
// Requer env: E2E_BASE_URL, E2E_WALLET_A_EMAIL, E2E_WALLET_A_PASSWORD.
// Sem mocks, sem credenciais commitadas.

async function loginAs(page: any, email: string, password: string) {
  await page.goto('/admin/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('form button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);
}

// Fixtures Zeta vivem só no banco de dev e são mutáveis; sem elas, a
// cobertura equivalente está em topwebchat-operational.spec.func.ts.
async function requireZetaFixture(page: any) {
  const found = await page.evaluate(async () => {
    const r = await fetch('/admin/topweb-chat/conversations/search?q=Zeta%20Alfa', { headers: { Accept: 'application/json' } });
    const body = await r.json().catch(() => ({}));
    return (body?.data?.length ?? 0) > 0;
  });

  test.skip(!found, 'fixtures Zeta ausentes no DEV (drift de dados)');
}

test('inbox: filas, contadores e campo visíveis e operáveis por teclado', async ({ page }) => {
  await loginAs(page, process.env.E2E_WALLET_A_EMAIL ?? '', process.env.E2E_WALLET_A_PASSWORD ?? '');
  await requireZetaFixture(page);
  await page.goto('/admin/topweb-chat');

  await expect(page.locator('#topwebchat-search-input')).toBeVisible();
  for (const queue of ['mine', 'unassigned']) {
    await expect(page.locator(`a[href*="queue=${queue}"]`).first()).toBeVisible();
  }

  // Walkthrough por teclado até o campo e abertura do primeiro resultado.
  await page.keyboard.press('Tab');
  await page.locator('#topwebchat-search-input').fill('Zeta Alfa');
  await page.locator('#topwebchat-search-results [role="option"]').first().waitFor();
  await page.locator('#topwebchat-search-input').press('ArrowDown');
  await page.locator('#topwebchat-search-input').press('Enter');
  await expect(page).toHaveURL(/\/admin\/topweb-chat\/conversations\/\d+/);
  await expect(page.locator('#topweb-chat-timeline')).toBeVisible();
});

test('conversa: timeline, composer e contexto renderizam', async ({ page }) => {
  await loginAs(page, process.env.E2E_WALLET_A_EMAIL ?? '', process.env.E2E_WALLET_A_PASSWORD ?? '');
  await requireZetaFixture(page);
  await page.goto('/admin/topweb-chat');
  await page.locator('#topwebchat-search-input').fill('Zeta Alfa');
  await page.locator('#topwebchat-search-results [role="option"]').first().waitFor();
  await page.locator('#topwebchat-search-results [role="option"]').first().click();

  await expect(page.locator('#topweb-chat-timeline')).toBeVisible();
  await expect(page.locator('#topweb-chat-send-form')).toBeVisible();
  await expect(page.locator('#topweb-chat-send-form textarea')).toBeVisible();
  await expect(page.locator('body')).toContainText('Zeta Alfa Negocio');

  const context = page.locator('[data-workspace-region="context"]');
  await expect(context).toBeHidden();
  await page.locator('[data-context-open]').click();
  await expect(context).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(context).toBeHidden();
});

test.describe('dark mode', () => {
  test.use({ colorScheme: 'dark' });

  test('inbox e conversa legíveis no escuro', async ({ page }) => {
    await loginAs(page, process.env.E2E_WALLET_A_EMAIL ?? '', process.env.E2E_WALLET_A_PASSWORD ?? '');
    await requireZetaFixture(page);
    await page.goto('/admin/topweb-chat');
    await expect(page.locator('#topwebchat-search-input')).toBeVisible();
    await page.locator('#topwebchat-search-input').fill('Zeta Alfa');
    await page.locator('#topwebchat-search-results [role="option"]').first().waitFor();
    await expect(page.locator('#topwebchat-search-results')).toBeVisible();
    await page.locator('#topwebchat-search-results [role="option"]').first().click();
    await expect(page.locator('#topweb-chat-timeline')).toBeVisible();
    await expect(page.locator('#topweb-chat-send-form')).toBeVisible();
  });
});
