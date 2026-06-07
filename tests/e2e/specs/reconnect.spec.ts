import { test, expect } from '@playwright/test';
import { loginAsHost, startGameFromDashboard, joinAsPlayer } from '../fixtures/game';

test('a returning player re-scanning the join code resumes the same identity', async ({ browser }) => {
    const hostContext = await browser.newContext();
    const playerContext = await browser.newContext();

    const hostPage = await hostContext.newPage();
    await loginAsHost(hostPage);
    const code = await startGameFromDashboard(hostPage);

    // Join once; capture the player_id from the play URL.
    const playerPage = await joinAsPlayer(playerContext, code, 'Reconnector');
    const firstId = new URL(playerPage.url()).searchParams.get('player_id');
    expect(firstId).not.toBeNull();

    // Re-open the join code in the SAME context (localStorage persists).
    await playerPage.goto(`/join/${code}`);

    // Auto-resume: bounced back to the play screen as the same player, no form.
    await playerPage.waitForURL(`**/game/${code}/play**`);
    const secondId = new URL(playerPage.url()).searchParams.get('player_id');
    expect(secondId).toBe(firstId);
    await expect(playerPage.locator('[data-test="player-nickname"]')).toContainText('Reconnector');

    // Exactly one player on the host roster — no duplicate was minted.
    await expect(hostPage.locator('[data-test="host-player-row"]')).toHaveCount(1);
});

test('the "not you?" link clears the saved identity and shows a fresh join form', async ({ browser }) => {
    const hostContext = await browser.newContext();
    const playerContext = await browser.newContext();

    const hostPage = await hostContext.newPage();
    await loginAsHost(hostPage);
    const code = await startGameFromDashboard(hostPage);

    const playerPage = await joinAsPlayer(playerContext, code, 'Sharer');

    // Use the escape hatch from the waiting screen.
    await playerPage.locator('[data-test="player-not-you"]').click();
    await playerPage.waitForURL(`**/join/${code}`);

    // The fresh join form is shown (emoji grid visible), not an auto-redirect.
    await expect(playerPage.locator('[data-test="join-emoji-grid"]')).toBeVisible();
});
