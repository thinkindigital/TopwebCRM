import { expect, test } from '@playwright/test';

// E14-R2 S8 — negativos das novas superfícies (sem mocks, sem fixtures fixas).
// Requer env: E2E_BASE_URL, E2E_ADMIN_EMAIL, E2E_ADMIN_PASSWORD,
// E2E_USER_EMAIL, E2E_USER_PASSWORD (sem grants operacionais).

async function login(page: any, email: string, password: string) {
  await page.goto('/admin/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('form button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);
}

test('sem grant: batch direto e exclusão de nota alheia negados, nada persiste', async ({ browser }) => {
  const adminCtx = await browser.newContext();
  const admin = await adminCtx.newPage();
  await login(admin, process.env.E2E_ADMIN_EMAIL ?? '', process.env.E2E_ADMIN_PASSWORD ?? '');
  await admin.goto('/admin/topweb-chat?queue=all');
  const href = await admin.locator('a.twp-row[href*="/admin/topweb-chat/conversations/"]').first().getAttribute('href');
  const conversationId = (href as string).match(/conversations\/(\d+)/)?.[1];
  expect(conversationId).toBeTruthy();
  await admin.goto(href as string);
  await expect(admin.locator('[data-note-form]')).toBeAttached();

  const secret = `NOTA-SEC-${Date.now()}`;
  const token = await admin.evaluate(() => (
    document.querySelector('#topweb-chat-send-form [name="_token"]')?.getAttribute('value')
    ?? document.querySelector('[data-note-form] [name="_token"]')?.getAttribute('value')
    ?? ''
  ));
  const created = await admin.evaluate(async ({ id, csrf, content }) => {
    const res = await fetch(`/admin/topweb-chat/conversations/${id}/notes`, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrf },
      body: new URLSearchParams({ content }),
    });
    return res.status;
  }, { id: conversationId, csrf: token, content: secret });
  expect([200, 302]).toContain(created);

  const noteId = await admin.evaluate(async ({ id }) => {
    const res = await fetch(`/admin/topweb-chat/conversations/${id}/context`, { headers: { Accept: 'text/html' } });
    const html = await res.text();
    const match = [...html.matchAll(/data-note-id="(\d+)"/g)].map((m) => m[1]);
    return match[0] ?? null;
  }, { id: conversationId });

  const countMessages = (page: any, id: string) => page.evaluate(async (conversationId: string) => {
    const res = await fetch(`/admin/topweb-chat/conversations/${conversationId}/messages?fragment=timeline`, { headers: { Accept: 'text/html' } });
    const html = await res.text();
    return { status: res.status, count: (html.match(/data-message-id="/g) ?? []).length };
  }, id);

  const before = await countMessages(admin, conversationId as string);
  expect(before.status).toBe(200);

  const userCtx = await browser.newContext();
  const user = await userCtx.newPage();
  await login(user, process.env.E2E_USER_EMAIL ?? '', process.env.E2E_USER_PASSWORD ?? '');

  const batchDenied = await user.evaluate(async ({ id }) => {
    const form = new FormData();
    form.append('attachments[0][file]', new File(['x'], 'x.txt', { type: 'text/plain' }));
    form.append('attachments[0][operation_key]', '123e4567-e89b-12d3-a456-426614174000');
    const res = await fetch(`/admin/topweb-chat/conversations/${id}/messages/batch`, {
      method: 'POST', credentials: 'same-origin', body: form,
      headers: { Accept: 'application/json' },
    });
    return res.status;
  }, { id: conversationId });
  // Negado sem executar: 401/403/404 esperados; 500 conhecido (página de erro,
  // classe bug #104) também nega — a contagem abaixo prova que nada persistiu.
  expect([401, 403, 404, 500]).toContain(batchDenied);

  const after = await countMessages(admin, conversationId as string);
  expect(after).toEqual(before);

  if (noteId) {
    const deleteDenied = await user.evaluate(async ({ id, note }) => {
      const res = await fetch(`/admin/topweb-chat/conversations/${id}/notes/${note}`, {
        method: 'DELETE', credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      return res.status;
    }, { id: conversationId, note: noteId });

    if (deleteDenied === 500) {
      // Classe bug #104 (página de erro): prova que nada foi executado.
      const html = await admin.evaluate(async ({ id }) => {
        const res = await fetch(`/admin/topweb-chat/conversations/${id}/context`, { headers: { Accept: 'text/html' } });
        return await res.text();
      }, { id: conversationId });
      expect(html).toContain(secret);
    } else {
      expect([401, 403, 404]).toContain(deleteDenied);
    }
  }

  const contextDenied = await user.evaluate(async ({ id }) => {
    const res = await fetch(`/admin/topweb-chat/conversations/${id}/context`, { headers: { Accept: 'text/html' } });
    return res.status;
  }, { id: conversationId });
  expect([401, 403, 404]).toContain(contextDenied);

  await userCtx.close();
  await adminCtx.close();
});
