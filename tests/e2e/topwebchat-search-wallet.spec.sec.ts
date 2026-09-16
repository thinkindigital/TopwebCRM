import { expect, test } from '@playwright/test';
import { e2eFixture, requiredEnv } from './fixture';

// V-06 (#101) + follow-ups — carteira logada, zero oráculo, matriz viewport.
async function loginAs(page: any, email: string, password: string) {
  await page.goto('/admin/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);
}

test('carteira: A acha o próprio e não vê o de B (zero oráculo)', async ({ page }) => {
  const fixture = e2eFixture();
  await loginAs(page, fixture.wallet_a.email, requiredEnv('E2E_FIXTURE_PASSWORD'));

  const own = await page.evaluate(async () => {
    const r = await fetch('/admin/topweb-chat/conversations/search?q=TopwebChat%20E2E%20Person%20A', { headers: { Accept: 'application/json' } });
    return { status: r.status, body: await r.json() };
  });
  expect(own.status).toBe(200);
  expect(own.body.data.length).toBeGreaterThan(0);
  expect(own.body.data.map((item: { id: number }) => item.id)).toContain(fixture.wallet_a.closed_conversation_id);
  const raw = JSON.stringify(own.body);
  expect(raw).not.toContain('s.whatsapp.net');
  expect(raw).not.toContain('5511900000002');

  const alien = await page.evaluate(async () => {
    const r = await fetch('/admin/topweb-chat/conversations/search?q=TopwebChat%20E2E%20Person%20B', { headers: { Accept: 'application/json' } });
    return { status: r.status, body: await r.json() };
  });
  expect(alien.status).toBe(200);
  expect(alien.body.data).toHaveLength(0);
});

test('IDOR: A não abre a conversa de B pela URL direta', async ({ page }) => {
  const fixture = e2eFixture();
  await loginAs(page, fixture.wallet_a.email, requiredEnv('E2E_FIXTURE_PASSWORD'));
  const status = await page.evaluate(async (id) => {
    const r = await fetch(`/admin/topweb-chat/conversations/${id}`, { headers: { Accept: 'application/json' } });
    return r.status;
  }, String(fixture.wallet_b.conversation_id));
  expect(status).toBe(403);
});

test('teclado: setas + Enter navegam do campo ao resultado', async ({ page }) => {
  const fixture = e2eFixture();
  await loginAs(page, fixture.wallet_a.email, requiredEnv('E2E_FIXTURE_PASSWORD'));
  await page.goto('/admin/topweb-chat');
  const field = page.locator('#topwebchat-search-input');
  await expect(field).toBeVisible();
  await field.fill(fixture.wallet_a.person_name);
  await page.locator('#topwebchat-search-results [role="option"]').first().waitFor();
  await page.keyboard.press('ArrowDown');
  await expect(field).toHaveAttribute('aria-activedescendant', 'topwebchat-search-option-0');
  expect(await page.evaluate(() => document.activeElement?.id)).toBe('topwebchat-search-input');
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/\/admin\/topweb-chat\/conversations\/\d+/);
  await expect(page.locator('body')).toContainText(fixture.wallet_a.person_name);
});

test('contexto e próxima ação renderizam sem trocar de tela (V-02/V-04)', async ({ page }) => {
  const fixture = e2eFixture();
  await loginAs(page, fixture.wallet_a.email, requiredEnv('E2E_FIXTURE_PASSWORD'));
  const id = await page.evaluate(async () => {
    const r = await fetch('/admin/topweb-chat/conversations/search?q=TopwebChat%20E2E%20Person%20A', { headers: { Accept: 'application/json' } });
    const body = await r.json();
    return body.data[0].id;
  });
  await page.goto(`/admin/topweb-chat/conversations/${id}`);
  await expect(page.locator('body')).toContainText(fixture.wallet_a.person_name);
  await expect(page.locator('body')).toContainText(fixture.wallet_a.lead_title);
});

test('envelope da próxima ação mostra decisão sem vazar texto livre (V-04)', async ({ page }) => {
  const fixture = e2eFixture();
  await loginAs(page, fixture.wallet_a.email, requiredEnv('E2E_FIXTURE_PASSWORD'));
  const id = await page.evaluate(async () => {
    const r = await fetch('/admin/topweb-chat/conversations/search?q=TopwebChat%20E2E%20Person%20A', { headers: { Accept: 'application/json' } });
    const body = await r.json();
    return body.data[0].id;
  });
  await page.goto(`/admin/topweb-chat/conversations/${id}`);
  await expect(page.locator('body')).toContainText('Call');
  await expect(page.locator('body')).toContainText(e2eFixture().wallet_a.person_name);
  await expect(page.locator('body')).not.toContainText('Segredo do envelope XYZ');
  await expect(page.locator('body')).not.toContainText('Texto livre sigiloso ABC');
});

test.describe('dark mode', () => {
  test.use({ colorScheme: 'dark' });

  test('campo e resultados legíveis no escuro', async ({ page }) => {
    const fixture = e2eFixture();
    await loginAs(page, fixture.wallet_a.email, requiredEnv('E2E_FIXTURE_PASSWORD'));
    await page.goto('/admin/topweb-chat');
    const field = page.locator('#topwebchat-search-input');
    await expect(field).toBeVisible();
    await field.fill(fixture.wallet_a.person_name);
    await page.locator('#topwebchat-search-results [role="option"]').first().waitFor();
    await expect(page.locator('#topwebchat-search-results')).toBeVisible();
  });
});
