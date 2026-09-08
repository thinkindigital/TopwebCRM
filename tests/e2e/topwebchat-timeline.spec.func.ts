import { expect, test } from '@playwright/test';

// Funcional — timeline T1.1 (âncora) + T1.2 (diff) + locale.
// Requer env: E2E_BASE_URL, E2E_ADMIN_EMAIL, E2E_ADMIN_PASSWORD, E2E_CONVERSATION_ID.

const loginAsAdmin = async (page) => {
  await page.goto('/admin/login');
  await page.locator('input[name="email"]').fill(process.env.E2E_ADMIN_EMAIL ?? '');
  await page.locator('input[name="password"]').fill(process.env.E2E_ADMIN_PASSWORD ?? '');
  await page.locator('form button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);
};

test('timeline ancora no fundo sem RangeError de locale', async ({ page }) => {
  const rangeErrors: string[] = [];
  page.on('pageerror', (error) => {
    if (String(error?.message ?? error).includes('locale')) {
      rangeErrors.push(String(error?.message ?? error));
    }
  });

  await loginAsAdmin(page);
  await page.goto(`/admin/topweb-chat/conversations/${process.env.E2E_CONVERSATION_ID}`);

  const timeline = page.locator('#topweb-chat-timeline');
  await expect(timeline).toBeVisible();
  await expect(page.locator('#topweb-chat-anchor')).toHaveCount(1);

  const nearBottom = await timeline.evaluate((el) => (
    el.scrollHeight - el.scrollTop - el.clientHeight < 120
  ));
  expect(nearBottom).toBe(true);
  expect(rangeErrors).toEqual([]);
});

test('scroll preservado ao ler histórico durante o polling', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto(`/admin/topweb-chat/conversations/${process.env.E2E_CONVERSATION_ID}`);

  const timeline = page.locator('#topweb-chat-timeline');
  await expect(timeline).toBeVisible();
  await timeline.evaluate((el) => { el.scrollTop = 0; });
  await page.waitForTimeout(3600); // um ciclo de poll (3s)

  const top = await timeline.evaluate((el) => el.scrollTop);
  expect(top).toBeLessThan(200); // âncora ausente da vista => sem auto-scroll
  await expect(page.locator('#topweb-chat-anchor')).toHaveCount(1);
});
