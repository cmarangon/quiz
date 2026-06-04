import { Page, BrowserContext, expect } from '@playwright/test';

const HOST_EMAIL = process.env.E2E_HOST_EMAIL || 'e2e-host@test.local';
const HOST_PASSWORD = process.env.E2E_HOST_PASSWORD || 'password';
const QUIZ_TITLE = process.env.E2E_QUIZ_TITLE || 'E2E Test Quiz';

export async function loginAsHost(page: Page): Promise<void> {
    await page.goto('/login');
    await page.locator('input[type="email"]').fill(HOST_EMAIL);
    await page.locator('input[type="password"]').fill(HOST_PASSWORD);
    await page.locator('[data-test="login-button"]').click();
    await page.waitForURL('**/dashboard');
}

export async function startGameFromDashboard(page: Page): Promise<string> {
    return startGameFromDashboardFor(page, QUIZ_TITLE);
}

export async function startGameFromDashboardFor(page: Page, title: string): Promise<string> {
    // Scope to the quiz row (wire:key="quiz-…"); a separate active-session row
    // (wire:key="session-…") can share the same title and would otherwise make
    // this locator match two elements.
    const row = page.locator('tr[wire\\:key^="quiz-"]', { hasText: title });
    await expect(row).toBeVisible();
    await row.locator('[data-test="quiz-play"]').click();
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
    emoji: string = '🚀',
): Promise<Page> {
    const page = await context.newPage();
    await page.goto(`/join/${code}`);
    await page.locator(`[data-test="join-emoji-option"][data-emoji="${emoji}"]`).click();
    await page.locator('[data-test="join-nickname-input"]').fill(nickname);
    // Picking an emoji prepends it to the live name preview before submitting.
    await expect(page.locator('[data-test="join-name-preview"]')).toContainText(emoji);
    await page.locator('[data-test="join-submit"]').click();
    // URL includes ?player_id=N — use ** suffix to match query params
    await page.waitForURL(`**/game/${code}/play**`);
    // The header renders emoji + nickname via <x-player-name>, so assert both.
    await expect(page.locator('[data-test="player-nickname"]')).toContainText(nickname);
    await expect(page.locator('[data-test="player-nickname"]')).toContainText(emoji);
    return page;
}

export async function startGame(hostPage: Page): Promise<void> {
    await hostPage.locator('[data-test="host-start-game"]').click();
    await expect(hostPage.locator('[data-test="host-phase"]')).toHaveAttribute('data-phase', 'playing');
}

export async function answerQuestion(playerPage: Page, label: string): Promise<void> {
    await playerPage
        .locator(`[data-test="player-answer-option"][data-answer-label="${label}"]`)
        .click();
    await expect(playerPage.locator('[data-test="player-phase"]')).toHaveAttribute('data-phase', 'answered');
}

// Drop a pin on the geo-guesser map and submit. Clicking the map element's
// centre yields a deterministic-enough lat/lng for a smoke test.
export async function answerGeoQuestion(playerPage: Page): Promise<void> {
    const map = playerPage.locator('[data-test="geo-map"]');
    await expect(map).toBeVisible();
    await map.click();
    const submit = playerPage.locator('[data-test="geo-submit"]');
    await expect(submit).toBeEnabled();
    await submit.click();
    await expect(playerPage.locator('[data-test="player-phase"]')).toHaveAttribute('data-phase', 'answered');
}

export async function endQuestion(hostPage: Page): Promise<void> {
    await hostPage.locator('[data-test="host-end-question"]').click();
    await expect(hostPage.locator('[data-test="host-phase"]')).toHaveAttribute('data-phase', 'reviewing');
}

// No phase assertion: on the last question this transitions to 'finished', on others to 'playing'.
export async function nextQuestion(hostPage: Page): Promise<void> {
    await hostPage.locator('[data-test="host-next-question"]').click();
}

export async function expectFinished(hostPage: Page): Promise<void> {
    await expect(hostPage.locator('[data-test="host-phase"]')).toHaveAttribute('data-phase', 'finished');
}
