import { expect, test } from '@playwright/test';
import { e2eFixture, requiredEnv } from './fixture';

// E14-R2 S8 — negativos das novas superfícies (sem mocks, sem fixtures fixas).
// Requer env: E2E_BASE_URL, E2E_ADMIN_EMAIL, E2E_ADMIN_PASSWORD,
// E2E_USER_EMAIL, E2E_USER_PASSWORD (sem grants operacionais).

async function login(page: any, email: string, password: string): Promise<string> {
  await page.goto('/admin/login');
  const token = await page.locator('input[name="_token"]').inputValue();
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);
  await page.goto('/admin/topweb-chat?queue=all');
  return page.locator('input[name="_token"]').first().inputValue();
}

test('sem grant: batch direto e exclusão de nota alheia negados, nada persiste', async ({ browser }) => {
  const adminCtx = await browser.newContext();
  const admin = await adminCtx.newPage();
  const fixture = e2eFixture();
  await login(admin, requiredEnv('E2E_ADMIN_EMAIL'), requiredEnv('E2E_ADMIN_PASSWORD'));
  const conversationId = String(fixture.operational_conversation_id);
  await admin.goto(`/admin/topweb-chat/conversations/${conversationId}`);
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
  const userToken = await login(user, requiredEnv('E2E_USER_EMAIL'), requiredEnv('E2E_USER_PASSWORD'));

  const batchDenied = await user.evaluate(async ({ id, csrf }) => {
    const form = new FormData();
    form.append('attachments[0][file]', new File(['x'], 'x.txt', { type: 'text/plain' }));
    form.append('attachments[0][operation_key]', '123e4567-e89b-12d3-a456-426614174000');
    const res = await fetch(`/admin/topweb-chat/conversations/${id}/messages/batch`, {
      method: 'POST', credentials: 'same-origin', body: form,
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
    });
    return res.status;
  }, { id: conversationId, csrf: userToken });
  expect(batchDenied).toBe(403);

  const after = await countMessages(admin, conversationId as string);
  expect(after).toEqual(before);

  expect(noteId).toBeTruthy();
  const deleteDenied = await user.evaluate(async ({ id, note, csrf }) => {
    const res = await fetch(`/admin/topweb-chat/conversations/${id}/notes/${note}`, {
      method: 'DELETE', credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
    });
    return res.status;
  }, { id: conversationId, note: noteId as string, csrf: userToken });
  // The Admin ACL middleware returns 401 when the route permission is absent.
  expect(deleteDenied).toBe(401);

  const contextDenied = await user.evaluate(async ({ id }) => {
    const res = await fetch(`/admin/topweb-chat/conversations/${id}/context`, { headers: { Accept: 'text/html' } });
    return res.status;
  }, { id: conversationId });
  expect(contextDenied).toBe(403);

  await userCtx.close();
  await adminCtx.close();
});
