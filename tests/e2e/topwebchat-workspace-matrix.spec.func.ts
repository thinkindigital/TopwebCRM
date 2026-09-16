import { expect, test } from '@playwright/test';
import { e2eFixture, requiredEnv } from './fixture';

// Follow-ups do ledger E14: matriz viewport/dark/teclado (V-01–V-05, V-07, V-08).
// Requer E2E_FIXTURE_PASSWORD além de E2E_BASE_URL. Sem mocks, sem credenciais
// commitadas. O global setup cria a fixture e falha se ela não puder ser criada.

async function loginAs(page: any, email: string, password: string) {
  await page.goto('/admin/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);
}

test('inbox: filas, contadores e campo visíveis e operáveis por teclado', async ({ page }) => {
  const fixture = e2eFixture();
  await loginAs(page, fixture.wallet_a.email, requiredEnv('E2E_FIXTURE_PASSWORD'));
  await page.goto('/admin/topweb-chat');

  await expect(page.locator('#topwebchat-search-input')).toBeVisible();
  for (const queue of ['mine', 'unassigned']) {
    await expect(page.locator(`a[href*="queue=${queue}"]`).first()).toBeVisible();
  }

  // Walkthrough por teclado até o campo e abertura do primeiro resultado.
  await page.keyboard.press('Tab');
  await page.locator('#topwebchat-search-input').fill(fixture.wallet_a.person_name);
  await page.locator('#topwebchat-search-results [role="option"]').first().waitFor();
  await page.keyboard.press('ArrowDown');
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/\/admin\/topweb-chat\/conversations\/\d+/);
  await expect(page.locator('#topweb-chat-timeline')).toBeVisible();
});

test('conversa: timeline, composer e contexto renderizam', async ({ page }) => {
  const fixture = e2eFixture();
  await loginAs(page, fixture.wallet_a.email, requiredEnv('E2E_FIXTURE_PASSWORD'));
  await page.goto('/admin/topweb-chat');
  await page.locator('#topwebchat-search-input').fill(fixture.wallet_a.person_name);
  await page.locator('#topwebchat-search-results [role="option"]').first().waitFor();
  await page.locator('#topwebchat-search-results [role="option"]').first().click();

  await expect(page.locator('#topweb-chat-timeline')).toBeVisible();
  await expect(page.locator('#topweb-chat-send-form')).toBeVisible();
  await expect(page.locator('#topweb-chat-send-form textarea')).toBeVisible();
  await expect(page.locator('body')).toContainText(fixture.wallet_a.lead_title);

  const context = page.locator('[data-workspace-region="context"]');
  if (await context.isVisible()) {
    // R1K keeps the context column open at wide desktop widths.
    await expect(context).toBeVisible();
  } else {
    await page.locator('[data-context-open]').click();
    await expect(context).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(context).toBeHidden();
  }
});

test.describe('dark mode', () => {
  test.use({ colorScheme: 'dark' });

  test('inbox e conversa legíveis no escuro', async ({ page }) => {
    const fixture = e2eFixture();
    await loginAs(page, fixture.wallet_a.email, requiredEnv('E2E_FIXTURE_PASSWORD'));
    await page.goto('/admin/topweb-chat');
    await expect(page.locator('#topwebchat-search-input')).toBeVisible();
    await page.locator('#topwebchat-search-input').fill(fixture.wallet_a.person_name);
    await page.locator('#topwebchat-search-results [role="option"]').first().waitFor();
    await expect(page.locator('#topwebchat-search-results')).toBeVisible();
    await page.locator('#topwebchat-search-results [role="option"]').first().click();
    await expect(page.locator('#topweb-chat-timeline')).toBeVisible();
    await expect(page.locator('#topweb-chat-send-form')).toBeVisible();
  });
});
