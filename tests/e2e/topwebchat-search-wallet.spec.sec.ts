import { expect, test } from '@playwright/test';

// V-06 (#101) + follow-ups — carteira logada, zero oráculo, matriz viewport.
// Requer env: E2E_BASE_URL, E2E_WALLET_A_EMAIL, E2E_WALLET_A_PASSWORD,
// E2E_WALLET_B_CONVERSATION_ID (conversa da carteira B, para o IDOR).
// Fixtures "Zeta Alfa/Beta" vivem só no banco de dev. Sem mocks.

async function loginAs(page: any, email: string, password: string) {
  await page.goto('/admin/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('form button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);
}

test('carteira: A acha o próprio e não vê o de B (zero oráculo)', async ({ page }) => {
  await loginAs(page, process.env.E2E_WALLET_A_EMAIL ?? '', process.env.E2E_WALLET_A_PASSWORD ?? '');

  const own = await page.evaluate(async () => {
    const r = await fetch('/admin/topweb-chat/conversations/search?q=Zeta%20Alfa', { headers: { Accept: 'application/json' } });
    return { status: r.status, body: await r.json() };
  });
  expect(own.status).toBe(200);
  expect(own.body.data.length).toBeGreaterThan(0);
  const raw = JSON.stringify(own.body);
  expect(raw).not.toContain('s.whatsapp.net');
  expect(raw).not.toContain('5511900000002');

  const alien = await page.evaluate(async () => {
    const r = await fetch('/admin/topweb-chat/conversations/search?q=Zeta%20Beta', { headers: { Accept: 'application/json' } });
    return { status: r.status, body: await r.json() };
  });
  expect(alien.status).toBe(200);
  expect(alien.body.data).toHaveLength(0);
});

test('IDOR: A não abre a conversa de B pela URL direta', async ({ page }) => {
  await loginAs(page, process.env.E2E_WALLET_A_EMAIL ?? '', process.env.E2E_WALLET_A_PASSWORD ?? '');
  const status = await page.evaluate(async (id) => {
    const r = await fetch(`/admin/topweb-chat/conversations/${id}`, { headers: { Accept: 'application/json' } });
    return r.status;
  }, process.env.E2E_WALLET_B_CONVERSATION_ID ?? '0');
  expect(status).toBe(403);
});

test('teclado: setas + Enter navegam do campo ao resultado', async ({ page }) => {
  await loginAs(page, process.env.E2E_WALLET_A_EMAIL ?? '', process.env.E2E_WALLET_A_PASSWORD ?? '');
  await page.goto('/admin/topweb-chat');
  const field = page.locator('#topwebchat-search-input');
  await expect(field).toBeVisible();
  await field.fill('Zeta Alfa');
  await page.locator('#topwebchat-search-results [role="option"]').first().waitFor();
  await field.press('ArrowDown');
  await field.press('Enter');
  await expect(page).toHaveURL(/\/admin\/topweb-chat\/conversations\/\d+/);
  await expect(page.locator('body')).toContainText('Zeta Alfa');
});

test('contexto e próxima ação renderizam sem trocar de tela (V-02/V-04)', async ({ page }) => {
  await loginAs(page, process.env.E2E_WALLET_A_EMAIL ?? '', process.env.E2E_WALLET_A_PASSWORD ?? '');
  const id = await page.evaluate(async () => {
    const r = await fetch('/admin/topweb-chat/conversations/search?q=Zeta%20Alfa', { headers: { Accept: 'application/json' } });
    const body = await r.json();
    return body.data[0].id;
  });
  await page.goto(`/admin/topweb-chat/conversations/${id}`);
  await expect(page.locator('body')).toContainText('Zeta Alfa Contato');
  await expect(page.locator('body')).toContainText('Zeta Alfa Negocio');
});

test('envelope da próxima ação mostra decisão sem vazar texto livre (V-04)', async ({ page }) => {
  await loginAs(page, process.env.E2E_WALLET_A_EMAIL ?? '', process.env.E2E_WALLET_A_PASSWORD ?? '');
  const id = await page.evaluate(async () => {
    const r = await fetch('/admin/topweb-chat/conversations/search?q=Zeta%20Alfa', { headers: { Accept: 'application/json' } });
    const body = await r.json();
    return body.data[0].id;
  });
  await page.goto(`/admin/topweb-chat/conversations/${id}`);
  await expect(page.locator('body')).toContainText('Call');
  await expect(page.locator('body')).toContainText('E2E Carteira A');
  await expect(page.locator('body')).not.toContainText('Segredo do envelope XYZ');
  await expect(page.locator('body')).not.toContainText('Texto livre sigiloso ABC');
});

test.describe('dark mode', () => {
  test.use({ colorScheme: 'dark' });

  test('campo e resultados legíveis no escuro', async ({ page }) => {
    await loginAs(page, process.env.E2E_WALLET_A_EMAIL ?? '', process.env.E2E_WALLET_A_PASSWORD ?? '');
    await page.goto('/admin/topweb-chat');
    const field = page.locator('#topwebchat-search-input');
    await expect(field).toBeVisible();
    await field.fill('Zeta Alfa');
    await page.locator('#topwebchat-search-results [role="option"]').first().waitFor();
    await expect(page.locator('#topwebchat-search-results')).toBeVisible();
  });
});
