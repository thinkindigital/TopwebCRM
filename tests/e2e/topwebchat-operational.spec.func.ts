import { expect, test } from '@playwright/test';
import { e2eFixture, requiredEnv } from './fixture';

// E14-R2 S8 — ciclo operacional do corretor sem sair da conversa.
// Requer env: E2E_BASE_URL, E2E_ADMIN_EMAIL, E2E_ADMIN_PASSWORD.
// Sem mocks, sem credenciais commitadas. Usa a primeira conversa da fila
// (fixture-agnóstico: não depende das fixtures Zeta, mutáveis no DEV).
// A fixture usa uma instância offline controlada: este caso prova a rejeição
// segura do lote sem enviar arquivos para um destinatário externo. Batch aceito
// em canal ready permanece explicitamente fora desta suíte até existir provider
// de teste autorizado.

async function loginAsAdmin(page: any) {
  await page.goto('/admin/login');
  await page.locator('input[name="email"]').fill(requiredEnv('E2E_ADMIN_EMAIL'));
  await page.locator('input[name="password"]').fill(requiredEnv('E2E_ADMIN_PASSWORD'));
  await page.locator('button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);
}

async function openFirstConversation(page: any) {
  const fixture = e2eFixture();
  await page.goto(`/admin/topweb-chat/conversations/${fixture.operational_conversation_id}`);
  await expect(page.locator('#topweb-chat-timeline')).toBeVisible();
}

async function openContext(page: any) {
  const context = page.locator('[data-workspace-region="context"]');
  if (!(await context.isVisible())) {
    await page.locator('[data-context-open]').click();
    await expect(context).toBeVisible();
  }
}

test('workspace oficial renderiza fila, conversa e contexto', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/admin/topweb-chat?queue=mine');
  await expect(page.getByRole('link', { name: /Minha fila|My queue/i })).toBeVisible();
  await expect(page.getByRole('link', { name: /Aguardando cliente|Waiting on customer/i })).toBeVisible();
  await expect(page.getByRole('link', { name: /Todas as conversas|All conversations/i })).toBeVisible();
  await openFirstConversation(page);

  await expect(page.locator('[data-topwebchat-workspace]')).toBeVisible();
  await expect(page.locator('[data-workspace-region="conversation"]')).toBeVisible();
  await expect(page.locator('#topweb-chat-send-form')).toBeVisible();
  expect(await page.locator('[data-topwebchat-workspace]').getAttribute('data-workspace-style')).toMatch(/^(R1|R1K)$/);
});

test('nota interna: cria, aparece na timeline e exclui sem reload', async ({ page }) => {
  await loginAsAdmin(page);
  await openFirstConversation(page);
  await openContext(page);

  const secret = `NOTA-E2E-${Date.now()}`;
  await page.locator('[data-note-editor] summary').click();
  await page.locator('[data-note-form] textarea[name="content"]').fill(secret);
  await page.locator('[data-note-form] button.primary-button').click();
  await expect(page.locator('#topweb-chat-timeline')).toContainText(secret);

  page.on('dialog', (dialog) => dialog.accept());
  const deleteButton = page.locator('.twp-note-item').filter({ hasText: secret }).locator('[data-note-delete]');
  const noteId = await deleteButton.getAttribute('data-note-id');
  await deleteButton.click();
  await expect(page.locator(`article[data-note-id="${noteId}"]`)).toHaveCount(0);
  await expect(page.locator('#topweb-chat-timeline')).not.toContainText(secret);
});

test('activity lifecycle: cria, reagenda e conclui sem reload', async ({ page }) => {
  await loginAsAdmin(page);
  await openFirstConversation(page);
  await openContext(page);

  const from = new Date(Date.now() + 2 * 60 * 60 * 1000).toISOString().slice(0, 16);
  const to = new Date(Date.now() + 2.5 * 60 * 60 * 1000).toISOString().slice(0, 16);
  const create = page.locator('[data-activity-create] form');
  await page.locator('[data-activity-create] summary').click();
  await create.locator('select[name="type"]').selectOption('call');
  await create.locator('input[name="schedule_from"]').fill(from);
  await create.locator('input[name="schedule_to"]').fill(to);
  await create.locator('button.primary-button').click();

  const next = page.locator('[data-next-action-card][data-next-action-id]').first();
  await expect(next).toHaveCount(1);
  const activityId = await next.getAttribute('data-next-action-id');
  expect(activityId).toBeTruthy();

  const rescheduledFrom = new Date(Date.now() + 3 * 60 * 60 * 1000).toISOString().slice(0, 16);
  const rescheduledTo = new Date(Date.now() + 3.5 * 60 * 60 * 1000).toISOString().slice(0, 16);
  const rescheduleDetails = next.locator('details').filter({ hasText: /Reagendar|Reschedule/i }).first();
  const reschedule = rescheduleDetails.locator('form[data-activity-form]');
  await rescheduleDetails.locator('summary').click();
  await reschedule.locator('input[name="schedule_from"]').fill(rescheduledFrom);
  await reschedule.locator('input[name="schedule_to"]').fill(rescheduledTo);
  await reschedule.locator('button.secondary-button').click();
  const rescheduled = page.locator('[data-next-action-card][data-next-action-id]').first();
  await expect(rescheduled).toContainText(/Hoje|\d{2}\/\d{2}/);
  const rescheduledId = await rescheduled.getAttribute('data-next-action-id');
  expect(rescheduledId).toBeTruthy();

  await page.locator(`[data-next-action-id="${rescheduledId}"] form[data-activity-form]`).first().locator('button.secondary-button').click();
  await expect(page.locator(`[data-next-action-id="${rescheduledId}"]`)).toHaveCount(0);
  await expect(page.locator(`[data-recent-activity-id="${rescheduledId}"]`)).toHaveCount(1);
});

test('owner transfer: Lead e todas as conversas seguem o novo responsável', async ({ page, browser }) => {
  const fixture = e2eFixture();
  await loginAsAdmin(page);
  await openFirstConversation(page);
  await openContext(page);

  const transfer = async (label: string) => {
    await page.locator('[data-owner-transfer-form] select[name="assigned_user_id"]').selectOption({ label });
    await page.locator('[data-owner-transfer-form] button').click();
    await openContext(page);
  };

  try {
    await transfer('Wallet B');
    await expect(page.locator('[data-owner-transfer-form] select')).toHaveValue(/\d+/);

    const walletA = await browser.newContext();
    const walletAPage = await walletA.newPage();
    await walletAPage.goto('/admin/login');
    await walletAPage.locator('input[name="email"]').fill(fixture.wallet_a.email);
    await walletAPage.locator('input[name="password"]').fill(requiredEnv('E2E_FIXTURE_PASSWORD'));
    await walletAPage.locator('button.primary-button').click();
    await expect(walletAPage).not.toHaveURL(/login/);
    const denied = await walletAPage.request.get(`/admin/topweb-chat/conversations/${fixture.wallet_a.open_conversation_id}`);
    expect(denied.status()).toBe(403);
    await walletA.close();
  } finally {
    await page.goto(`/admin/topweb-chat/conversations/${fixture.operational_conversation_id}`);
    await openContext(page);
    await transfer('Wallet A');
  }
});

test('tray: 3 anexos, remove o segundo, envia conforme o canal', async ({ page }) => {
  await loginAsAdmin(page);
  await openFirstConversation(page);

  const files = [0, 1, 2].map((i) => ({
    name: `e2e-${i}.txt`,
    mimeType: 'text/plain',
    buffer: Buffer.from(`conteudo e2e ${i}`),
  }));
  await page.locator('#topweb-chat-document-input').setInputFiles(files);
  await expect(page.locator('[data-tray-item]')).toHaveCount(3);

  const secondKey = await page.locator('[data-tray-item]').nth(1).getAttribute('data-tray-item');
  await page.locator(`[data-tray-remove="${secondKey}"]`).click();
  await expect(page.locator('[data-tray-item]')).toHaveCount(2);
  await expect(page.locator(`[data-tray-item="${secondKey}"]`)).toHaveCount(0);

  const status = await page.locator('#topweb-chat-instance-status').getAttribute('title');
   expect(status).toMatch(/unavailable|indisponível|offline|desconhecido/i);
  await expect(page.locator('#topweb-chat-send-form button.primary-button')).toBeDisabled();
  await expect(page.locator('[data-tray-item]')).toHaveCount(2);
});

test.describe('dark mode', () => {
  test.use({ colorScheme: 'dark' });

  test('workspace legível no escuro com cookie global', async ({ page, context }) => {
    await context.addCookies([{
      name: 'dark_mode', value: '1', domain: new URL(process.env.E2E_BASE_URL ?? '').hostname, path: '/', secure: true,
    }]);
    await loginAsAdmin(page);
    await openFirstConversation(page);
    await expect(page.locator('#topweb-chat-timeline')).toBeVisible();
    await expect(page.locator('#topweb-chat-send-form')).toBeVisible();
  });
});
