import { expect, test } from '@playwright/test';

// E14-R2 S8 — ciclo operacional do corretor sem sair da conversa.
// Requer env: E2E_BASE_URL, E2E_ADMIN_EMAIL, E2E_ADMIN_PASSWORD.
// Sem mocks, sem credenciais commitadas. Usa a primeira conversa da fila
// (fixture-agnóstico: não depende das fixtures Zeta, mutáveis no DEV).
// Envios reais nunca são disparados: o submit do batch só ocorre com a
// instance fora de ready (caminho de rejeição) ou é pulado com registro.

async function loginAsAdmin(page: any) {
  await page.goto('/admin/login');
  await page.locator('input[name="email"]').fill(process.env.E2E_ADMIN_EMAIL ?? '');
  await page.locator('input[name="password"]').fill(process.env.E2E_ADMIN_PASSWORD ?? '');
  await page.locator('form button.primary-button').click();
  await expect(page).not.toHaveURL(/login/);
}

async function openFirstConversation(page: any) {
  await page.goto('/admin/topweb-chat?queue=all');
  await page.locator('[data-topwebchat-workspace]').waitFor();
  const href = await page.locator('a.twp-row[href*="/admin/topweb-chat/conversations/"]').first().getAttribute('href');
  expect(href).toBeTruthy();
  await page.goto(href as string);
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
  await page.locator('[data-note-form] textarea[name="content"]').fill(secret);
  await page.locator('[data-note-form] button.primary-button').click();
  await expect(page.locator('#topweb-chat-timeline')).toContainText(secret);

  page.on('dialog', (dialog) => dialog.accept());
  const deleteButton = page.locator(`article[data-note-id] [data-note-delete]`).first();
  const noteId = await deleteButton.getAttribute('data-note-id');
  await deleteButton.click();
  await expect(page.locator(`[data-note-id="${noteId}"]`)).toHaveCount(0);
  await expect(page.locator('#topweb-chat-timeline')).not.toContainText(secret);
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
  test.info().annotations.push({ type: 'canal', description: `instance status no DEV: ${status}` });

  if (status !== 'ready') {
    await page.locator('#topweb-chat-send-form button.primary-button').click();
    await expect(page.locator('[data-tray-item]').first()).toContainText(/Falhou|Enviando|Pronto/, { timeout: 15000 });
  }
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
