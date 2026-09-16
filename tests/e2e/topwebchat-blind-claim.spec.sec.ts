import { expect, test } from '@playwright/test';
import { e2eFixture, requiredEnv } from './fixture';

test('agente sem concessão sensível e sem assignment assume item cego para si', async ({ page }) => {
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
});
