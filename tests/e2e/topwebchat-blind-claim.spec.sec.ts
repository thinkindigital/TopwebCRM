import { expect, test } from '@playwright/test';
import { e2eFixture, requiredEnv } from './fixture';

test('agente sem concessão sensível e sem assignment assume item cego para si', async ({ page, browser }) => {
  const fixture = e2eFixture();

  await page.goto('/admin/login');
  await page.locator('input[name="email"]').fill(requiredEnv('E2E_USER_EMAIL'));
  await page.locator('input[name="password"]').fill(requiredEnv('E2E_USER_PASSWORD'));
  await page.locator('button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);

  await page.goto('/admin/topweb-chat?queue=unassigned');
  const item = page.locator(`[data-blind-conversation-id="${fixture.blind_conversation_id}"]`);
  await expect(item).toBeVisible();
  await item.getByRole('button', { name: /Assumir|Claim/i }).click();

  await expect(page).toHaveURL(/queue=unassigned/);
  await page.goto(`/admin/topweb-chat/conversations/${fixture.blind_conversation_id}`);
  await expect(page.locator('[data-topwebchat-workspace]')).toBeVisible();

  const admin = await browser.newPage();
  try {
    await admin.goto('/admin/login');
    await admin.locator('input[name="email"]').fill(requiredEnv('E2E_ADMIN_EMAIL'));
    await admin.locator('input[name="password"]').fill(requiredEnv('E2E_ADMIN_PASSWORD'));
    await admin.locator('button.primary-button').click();
    await expect(admin).not.toHaveURL(/login/);
    await admin.goto(`/admin/topweb-chat/conversations/${fixture.blind_conversation_id}`);

    const token = await admin.locator('input[name="_token"]').first().inputValue();
    const response = await admin.evaluate(async ({ id, csrf }) => {
      const result = await fetch(`/admin/topweb-chat/conversations/${id}/assignment`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-TOKEN': csrf,
        },
        body: new URLSearchParams({ assigned_user_id: '' }),
      });

      return result.status;
    }, { id: fixture.blind_conversation_id, csrf: token });

    expect([200, 302]).toContain(response);
  } finally {
    await admin.close();
  }
});
