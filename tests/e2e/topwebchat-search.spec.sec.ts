import { expect, test } from '@playwright/test';

// V-06 (#101) — busca segura, negative testing sem mocks.
// Escopo deste spec: contexto anônimo (sem credenciais no ambiente).
// Casos com carteira (zero oráculo entre dois donos) vivem no Pest
// (ConversationSearchTest, TB-09) com fixtures isoladas — o banco
// compartilhado de dev não recebe fixtures de teste.
// Requer env: E2E_BASE_URL.

const searchUrl = (q: string) => (
  `${process.env.E2E_BASE_URL ?? ''}/admin/topweb-chat/conversations/search?q=${encodeURIComponent(q)}`
);

test('anônimo: busca redireciona para login sem vazar dados', async ({ page }) => {
  await page.goto('/admin/login');
  const res = await page.evaluate(async (u) => {
    const r = await fetch(u, { headers: { Accept: 'application/json' } });
    return { url: r.url, status: r.status, body: await r.text() };
  }, searchUrl('teste'));

  expect(res.url).toMatch(/login/);
  expect(res.body).not.toContain('remote_jid');
  expect(res.body).not.toContain('s.whatsapp.net');
});

test('anônimo: inbox é login, sem campo de busca exposto', async ({ page }) => {
  await page.goto('/admin/topweb-chat');
  await expect(page).toHaveURL(/login/);
  await expect(page.locator('#topwebchat-search-input')).toHaveCount(0);
});

test('query maliciosa não é refletida nem quebra o endpoint', async ({ page }) => {
  const payload = '<script>alert(1)</script>';
  await page.goto('/admin/login');
  const res = await page.evaluate(async (u) => {
    const r = await fetch(u, { headers: { Accept: 'application/json' } });
    return { url: r.url, status: r.status, body: await r.text() };
  }, searchUrl(payload));

  // Anônimo: redirect de login. Logado sem carteira: 200 vazio (Pest cobre).
  // Em nenhum caso o payload volta cru no corpo.
  expect(res.body).not.toContain(payload);
  expect([200, 302].includes(res.status) || res.url.match(/login/)).toBeTruthy();
});
