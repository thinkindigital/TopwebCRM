import { mkdir, readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const baseURL = 'https://crmdev.scgroup.com.br';
const credentialDocument = JSON.parse(await readFile('/root/.topweb-ops/dev-login-credentials.json', 'utf8'));
const captures = [
  { name: '01-desktop-light-normal', width: 1920, height: 1080, theme: 'light', scenario: 'linked-action' },
  { name: '02-desktop-light-multiple-attachments', width: 1920, height: 1080, theme: 'light', scenario: 'attachments-multiple' },
  { name: '02a-desktop-light-two-attachments', width: 1920, height: 1080, theme: 'light', scenario: 'attachments-multiple', params: { attachment_count: '2' } },
  { name: '02b-desktop-light-remove-second', width: 1920, height: 1080, theme: 'light', scenario: 'attachments-multiple', action: 'remove-second' },
  { name: '03-desktop-light-activity-create', width: 1920, height: 1080, theme: 'light', scenario: 'activity-create' },
  { name: '04-desktop-light-note-create', width: 1920, height: 1080, theme: 'light', scenario: 'note-create' },
  { name: '05-desktop-light-stage-open', width: 1920, height: 1080, theme: 'light', scenario: 'stage-open' },
  { name: '05a-desktop-light-stage-rejected', width: 1920, height: 1080, theme: 'light', scenario: 'stage-open', params: { stage_error: '1' } },
  { name: '05b-desktop-light-activity-completed', width: 1920, height: 1080, theme: 'light', scenario: 'action-existing', params: { activity_completed: '1' } },
  { name: '06-desktop-dark-normal', width: 1920, height: 1080, theme: 'dark', scenario: 'linked-action' },
  { name: '07-desktop-dark-offline', width: 1920, height: 1080, theme: 'dark', scenario: 'offline' },
  { name: '08-desktop-dark-multiple-attachments', width: 1920, height: 1080, theme: 'dark', scenario: 'attachments-multiple' },
  { name: '09-desktop-dark-activity', width: 1920, height: 1080, theme: 'dark', scenario: 'action-existing' },
  { name: '10-1366-light-context-drawer', width: 1366, height: 768, theme: 'light', scenario: 'linked-action', context: true },
  { name: '11-1366-dark-attachment', width: 1366, height: 768, theme: 'dark', scenario: 'attachment-single' },
  { name: '12-mobile-light-conversation', width: 390, height: 844, theme: 'light', scenario: 'linked-action' },
  { name: '13-mobile-light-attachments', width: 390, height: 844, theme: 'light', scenario: 'attachments-multiple' },
  { name: '14-mobile-light-context-sheet', width: 390, height: 844, theme: 'light', scenario: 'stage-open', context: true },
  { name: '15-mobile-light-activity-sheet', width: 390, height: 844, theme: 'light', scenario: 'activity-create' },
  { name: '16-mobile-dark-conversation', width: 390, height: 844, theme: 'dark', scenario: 'dense' },
  { name: '17-mobile-dark-attachment-failed', width: 390, height: 844, theme: 'dark', scenario: 'attachment-failed' },
  { name: '18-mobile-dark-context-sheet', width: 390, height: 844, theme: 'dark', scenario: 'unlinked', context: true },
  { name: '19-mobile-dark-activity-sheet', width: 390, height: 844, theme: 'dark', scenario: 'activity-create' },
];

await mkdir('screenshots/r1k-final', { recursive: true });
const browser = await chromium.launch({ headless: true });

async function createPage(width, height, theme) {
  const context = await browser.newContext({ viewport: { width, height }, colorScheme: theme });
  await context.addCookies([{
    name: 'dark_mode', value: theme === 'dark' ? '1' : '0', domain: 'crmdev.scgroup.com.br', path: '/', secure: true,
  }]);
  return { context, page: await context.newPage() };
}

async function login(page, credential) {
  await page.goto(`${baseURL}/admin/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="email"]').fill(credential.email);
  await page.locator('input[name="password"]').fill(credential.password);
  await page.locator('form button.primary-button').click();
  await page.waitForURL((url) => !url.pathname.includes('/login'));
}

function channel(value) {
  const number = Number(value) / 255;
  return number <= 0.04045 ? number / 12.92 : ((number + 0.055) / 1.055) ** 2.4;
}

function contrast(foreground, background) {
  const parse = (color) => color.match(/[\d.]+/g).slice(0, 3).map(Number);
  const luminance = (color) => {
    const [r, g, b] = parse(color).map(channel);
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  };
  const light = Math.max(luminance(foreground), luminance(background));
  const dark = Math.min(luminance(foreground), luminance(background));
  return (light + 0.05) / (dark + 0.05);
}

let credential;
for (const candidate of credentialDocument.accounts.filter((account) => account.status)) {
  const { context, page } = await createPage(1280, 800, 'light');
  await login(page, candidate).catch(() => {});
  if (!page.url().includes('/login')) {
    const response = await page.goto(`${baseURL}/admin/topweb-chat`, { waitUntil: 'networkidle' });
    if (response?.ok() && await page.locator('a[href*="/admin/topweb-chat/conversations/"]').count()) {
      credential = candidate;
      await context.close();
      break;
    }
  }
  await context.close();
}

if (!credential) throw new Error('No stored DEV account can access TopwebChat conversations');

try {
  for (const capture of captures) {
    const { context, page } = await createPage(capture.width, capture.height, capture.theme);
    try {
      page.on('pageerror', (error) => console.error(`Page error in ${capture.name}:`, error.message));
      page.on('console', (message) => {
        if (message.type() === 'error') console.error(`Console error in ${capture.name}:`, message.text());
      });
      await login(page, credential);
      await page.goto(`${baseURL}/admin/topweb-chat`, { waitUntil: 'networkidle' });
      const conversationHref = await page.locator('a[href*="/admin/topweb-chat/conversations/"]').first().getAttribute('href');
      if (!conversationHref) throw new Error('No accessible conversation found');

      const url = new URL(conversationHref, baseURL);
      url.searchParams.set('variant', 'R1K');
      url.searchParams.set('scenario', capture.scenario);
      url.searchParams.set('pane', 'conversation');
      url.searchParams.set('clean', '1');
      if (capture.context) url.searchParams.set('context', '1');
      for (const [key, value] of Object.entries(capture.params ?? {})) url.searchParams.set(key, value);

      const response = await page.goto(url.toString(), { waitUntil: 'networkidle' });
      if (!response?.ok()) throw new Error(`R1K failed at ${capture.name}`);
      const root = page.locator('[data-prototype-root]');
      await root.waitFor({ state: 'visible' });
      if (await root.getAttribute('data-interactive') !== 'ready') throw new Error(`Prototype script did not initialize: ${capture.name}`);
      if (await root.getAttribute('data-interactive-end') !== 'ready') throw new Error(`Prototype script stopped before binding interactions: ${capture.name}`);

      if (capture.action === 'remove-second') {
        await page.locator('[data-remove-attachment]').nth(1).click();
      }

      const layout = await page.evaluate(() => {
        const prototype = document.querySelector('[data-prototype-root]');
        const context = document.querySelector('.twp-context');
        const timeline = document.querySelector('.twp-timeline');
        const bodyText = document.querySelector('.twp-bubble p');
        const tabs = document.querySelector('.twp-tabs');
        return {
          scenario: prototype.dataset.scenario,
          dark: document.documentElement.classList.contains('dark'),
          overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
          tabsOverflow: tabs.scrollWidth > tabs.clientWidth + 1,
          queueVisible: getComputedStyle(document.querySelector('.twp-queue')).display !== 'none',
          conversationVisible: getComputedStyle(document.querySelector('.twp-conversation')).display !== 'none',
          contextVisible: getComputedStyle(context).display !== 'none',
          contextPosition: getComputedStyle(context).position,
          timelineBackground: getComputedStyle(timeline).backgroundColor,
          textColor: getComputedStyle(bodyText).color,
          bubbleBackground: getComputedStyle(bodyText.closest('.twp-bubble')).backgroundColor,
        };
      });

      if (layout.scenario !== capture.scenario) throw new Error(`Scenario mismatch: ${capture.name}`);
      if (layout.dark !== (capture.theme === 'dark')) throw new Error(`Theme mismatch: ${capture.name}`);
      if (layout.overflow || (capture.width >= 1024 && layout.tabsOverflow)) throw new Error(`Horizontal overflow: ${capture.name}`);
      if (!layout.conversationVisible) throw new Error(`Conversation hidden: ${capture.name}`);
      if (capture.width >= 1440 && (!layout.queueVisible || !layout.contextVisible || layout.contextPosition === 'fixed')) throw new Error(`Wide layout mismatch: ${capture.name}`);
      if (capture.width < 1440 && capture.context && (!layout.contextVisible || layout.contextPosition !== 'fixed')) throw new Error(`Drawer mismatch: ${capture.name}`);
      if (capture.theme === 'dark' && layout.timelineBackground === 'rgb(0, 0, 0)') throw new Error(`Absolute black timeline: ${capture.name}`);
      if (contrast(layout.textColor, layout.bubbleBackground) < 4.5) throw new Error(`Body contrast below AA: ${capture.name}`);

      const scenarioSelector = {
        'attachments-multiple': '[data-attachment-item]',
        'attachment-failed': '.twp-attachment-item.is-falhou',
        'activity-create': '[data-activity-flow].is-open',
        'note-create': '[data-note-editor].is-open',
        'stage-open': '[data-stage-wrap].is-open',
        offline: '.twp-offline',
      }[capture.scenario];
      if (scenarioSelector && capture.params?.stage_error !== '1' && !(await page.locator(scenarioSelector).first().isVisible())) throw new Error(`Scenario state hidden: ${capture.name}`);
      if (capture.action === 'remove-second' && await page.locator('[data-attachment-item]').filter({ hasText: 'Planta_A.jpg' }).count()) throw new Error(`Second attachment not removed: ${capture.name}`);
      if (capture.params?.stage_error === '1') {
        const errorState = await page.locator('[data-stage-error]').evaluate((element) => ({ hidden: element.hidden, display: getComputedStyle(element).display, rect: element.getBoundingClientRect().toJSON() }));
        if (errorState.hidden || errorState.display === 'none') throw new Error(`Stage error hidden: ${capture.name} ${JSON.stringify(errorState)}`);
      }
      if (capture.params?.activity_completed === '1' && !(await page.getByText('Visita concluida', { exact: true }).isVisible())) throw new Error(`Completed activity missing: ${capture.name}`);

      await page.screenshot({ path: `screenshots/r1k-final/${capture.name}.png` });

      if (capture.name === '01-desktop-light-normal') {
        const composer = page.locator('[data-composer-input]');
        const messagesBefore = await page.locator('.twp-message').count();
        await composer.fill('Primeira linha');
        await composer.press('Shift+Enter');
        await composer.type('Segunda linha');
        if (!(await composer.inputValue()).includes('\n')) throw new Error('Shift+Enter did not create a new line');
        await composer.press('Enter');
        const messagesAfter = await page.locator('.twp-message').count();
        const composerAfter = await composer.inputValue();
        if (messagesAfter <= messagesBefore || composerAfter !== '') throw new Error(`Enter did not add the local message: ${messagesBefore}/${messagesAfter}/${JSON.stringify(composerAfter)}`);
      }
      if (capture.name === '03-desktop-light-activity-create') {
        await page.locator('[data-create-activity]').click();
        if (!(await page.getByText('Atividade criada. Esta e agora a proxima acao.', { exact: true }).isVisible())) throw new Error('Activity create flow did not update next action');
      }
      if (capture.name === '04-desktop-light-note-create') {
        await page.locator('[data-add-note]').click();
        if (await page.locator('[data-note-editor]').isVisible()) throw new Error('Note editor remained open after save');
        if ((await page.getByText('Cliente pediu retorno apos as 18h.', { exact: true }).count()) < 1) throw new Error('Internal note did not enter the timeline');
      }
      if (capture.name === '05-desktop-light-stage-open') {
        await page.locator('[data-stage-option="Negociacao"]').click();
        if (await page.locator('[data-stage-label]').textContent() !== 'Negociacao') throw new Error('Stage success did not update the local label');
      }
      if (capture.name === '09-desktop-dark-activity') {
        await page.locator('[data-complete-activity]').click();
        if (!(await page.getByText('Visita concluida', { exact: true }).first().isVisible())) throw new Error('Activity completion did not update recent activity');
      }
    } finally {
      await context.close();
    }
  }
} finally {
  await browser.close();
}
