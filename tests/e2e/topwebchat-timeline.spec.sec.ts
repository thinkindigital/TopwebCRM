import { expect, test } from '@playwright/test';
import { e2eFixture, requiredEnv } from './fixture';

// Segurança (negative testing) — sem mocks: requisições reais contra o servidor.
// Requer env: E2E_BASE_URL, E2E_USER_EMAIL, E2E_USER_PASSWORD (SEM topweb_chat.inbox.view).

const conversationUrl = (suffix = '') => (
  `/admin/topweb-chat/conversations/${e2eFixture().operational_conversation_id}${suffix}`
);

test('sem login: conversa não vaza dados', async ({ page }) => {
  await page.goto(conversationUrl());
  // 302 -> /admin/login (goto segue o redirect): sem timeline, sem conteúdo.
  await expect(page).toHaveURL(/login/);
  await expect(page.locator('#topweb-chat-timeline')).toHaveCount(0);
  await expect(page.locator('body')).not.toContainText('Ola, preciso de ajuda');
});

test('sem permissão: show, messages, media e client-events bloqueados', async ({ page }) => {
  await page.goto('/admin/login');
  await page.locator('input[name="email"]').fill(requiredEnv('E2E_USER_EMAIL'));
  await page.locator('input[name="password"]').fill(requiredEnv('E2E_USER_PASSWORD'));
  await page.locator('button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);
  await page.goto('/admin/topweb-chat?queue=all');
  const loginToken = await page.locator('input[name="_token"]').first().inputValue();

  for (const url of [conversationUrl(), conversationUrl('/messages')]) {
    const status = await page.evaluate(async (u) => {
      const res = await fetch(u, { headers: { Accept: 'application/json' } });
      return res.status;
    }, url);
    expect([401, 403, 404]).toContain(status);
  }

  const postStatus = await page.evaluate(async ({ url, csrf }) => {
    const res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ level: 'info', event: 'client.initialized', context: {} }),
    });
    return res.status;
  }, { url: conversationUrl('/client-events'), csrf: loginToken });

  expect(postStatus).toBe(403);
});

test('fragmento exige auth e respeita escopo', async ({ page }) => {
  // Origem válida para o fetch (ainda sem login).
  await page.goto('/admin/login');
  // Sem login: segue redirect até /login, sem HTML do chat.
  const anon = await page.evaluate(async (u) => {
    const res = await fetch(u, { headers: { Accept: 'text/html' } });
    return { url: res.url, body: await res.text() };
  }, conversationUrl('/messages?fragment=timeline'));
  expect(anon.url).toMatch(/login/);
  expect(anon.body).not.toContain('data-message-id');

  // Usuário sem inbox.view: bloqueado também no fragmento.
  await page.goto('/admin/login');
  await page.locator('input[name="email"]').fill(requiredEnv('E2E_USER_EMAIL'));
  await page.locator('input[name="password"]').fill(requiredEnv('E2E_USER_PASSWORD'));
  await page.locator('button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);
  await page.goto('/admin/topweb-chat?queue=all');
  const denied = await page.evaluate(async (u) => {
    const res = await fetch(u, { headers: { Accept: 'text/html' } });
    return res.status;
  }, conversationUrl('/messages?fragment=timeline'));
  expect([401, 403, 404]).toContain(denied);
});

test('notas internas não vazam no fragmento sem permissão', async ({ browser }) => {
  const secret = `NOTA-E2E-${Date.now()}`;
  const adminCtx = await browser.newContext();
  const admin = await adminCtx.newPage();
  await admin.goto('/admin/login');
  await admin.locator('input[name="email"]').fill(requiredEnv('E2E_ADMIN_EMAIL'));
  await admin.locator('input[name="password"]').fill(requiredEnv('E2E_ADMIN_PASSWORD'));
  await admin.locator('button.primary-button').click();
  await expect(admin).not.toHaveURL(/login/);
  await admin.goto(conversationUrl());
  const token = await admin.evaluate(() => (
    document.querySelector('#topweb-chat-send-form [name="_token"]')?.getAttribute('value') ?? ''
  ));
  const created = await admin.evaluate(async ({ url, csrf, content }) => {
    const res = await fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrf },
      body: new URLSearchParams({ content }),
    });
    return res.status;
  }, { url: conversationUrl('/notes'), csrf: token, content: secret });
  expect([200, 302]).toContain(created);

  const userCtx = await browser.newContext();
  const user = await userCtx.newPage();
  await user.goto('/admin/login');
  await user.locator('input[name="email"]').fill(requiredEnv('E2E_USER_EMAIL'));
  await user.locator('input[name="password"]').fill(requiredEnv('E2E_USER_PASSWORD'));
  await user.locator('button.primary-button').click();
  await expect(user).not.toHaveURL(/login/);
  const body = await user.evaluate(async (u) => {
    const res = await fetch(u, { headers: { Accept: 'text/html' } });
    return await res.text();
  }, conversationUrl('/messages?fragment=timeline'));
  expect(body).not.toContain(secret);
  await adminCtx.close();
  await userCtx.close();
});

test('assignment sem permissão retorna 403 e não atribui', async ({ page }) => {
  // Transferência não autorizada precisa falhar com a resposta contratual.
  await page.goto('/admin/login');
  await page.locator('input[name="email"]').fill(requiredEnv('E2E_USER_EMAIL'));
  await page.locator('input[name="password"]').fill(requiredEnv('E2E_USER_PASSWORD'));
  await page.locator('button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);
  await page.goto('/admin/topweb-chat?queue=all');
  const loginToken = await page.locator('input[name="_token"]').first().inputValue();
  const status = await page.evaluate(async ({ url, csrf }) => {
    const res = await fetch(url, {
      method: 'PUT', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ assigned_user_id: 1 }),
    });
    return res.status;
  }, { url: conversationUrl('/assignment'), csrf: loginToken });
  expect([401, 403]).toContain(status);
});

test('client-events rejeita nível e contexto inválidos', async ({ page }) => {
  // Login como ADMIN (tem permissão): a validação 422 precisa ser alcançável.
  await page.goto('/admin/login');
  await page.locator('input[name="email"]').fill(requiredEnv('E2E_ADMIN_EMAIL'));
  await page.locator('input[name="password"]').fill(requiredEnv('E2E_ADMIN_PASSWORD'));
  await page.locator('button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);
  await page.goto(conversationUrl());
  await expect(page.locator('#topweb-chat-timeline')).toBeVisible();

  // Mesmo seletor do show.blade.php (o layout admin não expõe meta csrf-token).
  const token = await page.evaluate(() => (
    document.querySelector('#topweb-chat-send-form [name="_token"]')?.getAttribute('value') ?? ''
  ));

  // level fora do enum e context não-array => 422; chave desconhecida no
  // context => 202 com a chave descartada pelo allowlist (sem vazamento).
  const cases: Array<{ payload: unknown; expected: number }> = [
    { payload: { level: 'debug', event: 'x', context: {} }, expected: 422 },
    { payload: { level: 'error', event: 'client.refresh_failed', context: 'nao-e-array' }, expected: 422 },
    { payload: { level: 'error', event: 'client.refresh_failed', context: { content: 'descartado' } }, expected: 202 },
  ];

  for (const { payload, expected } of cases) {
    const status = await page.evaluate(async ({ url, csrf, payload }) => {
      const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
      });
      return res.status;
    }, { url: conversationUrl('/client-events'), csrf: token, payload });
    expect(expected).toBe(status);
  }
});
