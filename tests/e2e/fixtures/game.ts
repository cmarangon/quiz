import { Page, BrowserContext, expect } from '@playwright/test';

const HOST_EMAIL = process.env.E2E_HOST_EMAIL || 'e2e-host@test.local';
const HOST_PASSWORD = process.env.E2E_HOST_PASSWORD || 'password';
const QUIZ_TITLE = process.env.E2E_QUIZ_TITLE || 'E2E Test Quiz';

export async function loginAsHost(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/email/i).fill(HOST_EMAIL);
    await page.getByLabel(/password/i).fill(HOST_PASSWORD);
    await page.getByRole('button', { name: /log in|sign in/i }).click();
    await page.waitForURL('**/dashboard');
}

export async function startGameFromDashboard(page: Page): Promise<string> {
    const row = page.locator('tr', { hasText: QUIZ_TITLE });
    await expect(row).toBeVisible();
    await row.getByRole('button', { name: /play/i }).click();
    await page.waitForURL(/\/game\/[A-Z0-9]+\/host/);
    const code = await page.locator('[data-test="join-code"]').innerText();
    return code.trim();
}

export async function openSpectator(context: BrowserContext, code: string): Promise<Page> {
    const page = await context.newPage();
    await page.goto(`/game/${code}/spectator`);
    return page;
}

export async function joinAsPlayer(
    context: BrowserContext,
    code: string,
    nickname: string,
): Promise<Page> {
    const page = await context.newPage();
    await page.goto(`/join/${code}`);
    await page.locator('[data-test="join-nickname-input"]').fill(nickname);
    await page.getByRole('button', { name: /join game/i }).click();
    // URL includes ?player_id=N — use ** suffix to match query params
    await page.waitForURL(`**/game/${code}/play**`);
    await expect(page.locator('[data-test="player-nickname"]')).toContainText(nickname);
    return page;
}

export async function startGame(hostPage: Page): Promise<void> {
    await hostPage.getByRole('button', { name: /start game/i }).click();
    await expect(hostPage.locator('[data-test="host-phase"]')).toHaveAttribute('data-phase', 'playing');
}

export async function answerQuestion(playerPage: Page, label: string): Promise<void> {
    await playerPage
        .locator(`[data-test="player-answer-option"][data-answer-label="${label}"]`)
        .click();
    await expect(playerPage.locator('[data-test="player-phase"]')).toHaveAttribute('data-phase', 'answered');
}

export async function endQuestion(hostPage: Page): Promise<void> {
    await hostPage.getByRole('button', { name: /end question/i }).click();
    await expect(hostPage.locator('[data-test="host-phase"]')).toHaveAttribute('data-phase', 'reviewing');
}

// No phase assertion: on the last question this transitions to 'finished', on others to 'playing'.
export async function nextQuestion(hostPage: Page): Promise<void> {
    await hostPage.getByRole('button', { name: /next question/i }).click();
}

export async function expectFinished(hostPage: Page): Promise<void> {
    await expect(hostPage.locator('[data-test="host-phase"]')).toHaveAttribute('data-phase', 'finished');
}
