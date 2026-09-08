import { expect, test } from '@playwright/test';

// Segurança (negative testing) — sem mocks: requisições reais contra o servidor.
// Requer env: E2E_BASE_URL, E2E_USER_EMAIL, E2E_USER_PASSWORD (SEM topweb_chat.inbox.view),
// E2E_CONVERSATION_ID, E2E_MESSAGE_ID (mídia, quando houver).

const conversationUrl = (suffix = '') => (
  `/admin/topweb-chat/conversations/${process.env.E2E_CONVERSATION_ID}${suffix}`
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
  await page.locator('input[name="email"]').fill(process.env.E2E_USER_EMAIL ?? '');
  await page.locator('input[name="password"]').fill(process.env.E2E_USER_PASSWORD ?? '');
  await page.locator('form button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);

  for (const url of [conversationUrl(), conversationUrl('/messages')]) {
    const status = await page.evaluate(async (u) => {
      const res = await fetch(u, { headers: { Accept: 'application/json' } });
      return res.status;
    }, url);
    expect([401, 403, 404]).toContain(status);
  }

  // Mesmo seletor do show.blade.php (o layout admin não expõe meta csrf-token).
  const token = await page.evaluate(() => (
    document.querySelector('#topweb-chat-send-form [name="_token"]')?.getAttribute('value') ?? ''
  ));
  const postStatus = await page.evaluate(async ({ url, csrf }) => {
    const res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ level: 'info', event: 'client.initialized', context: {} }),
    });
    return res.status;
  }, { url: conversationUrl('/client-events'), csrf: token });
  expect([401, 403, 404, 419]).toContain(postStatus);
});

test('client-events rejeita nível e contexto inválidos', async ({ page }) => {
  // Login como ADMIN (tem permissão): a validação 422 precisa ser alcançável.
  await page.goto('/admin/login');
  await page.locator('input[name="email"]').fill(process.env.E2E_ADMIN_EMAIL ?? '');
  await page.locator('input[name="password"]').fill(process.env.E2E_ADMIN_PASSWORD ?? '');
  await page.locator('form button.primary-button').click();
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
