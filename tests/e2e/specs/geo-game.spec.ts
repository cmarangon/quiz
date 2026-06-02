import { test, expect } from '@playwright/test';
import {
    loginAsHost,
    startGameFromDashboardFor,
    openSpectator,
    joinAsPlayer,
    startGame,
    answerGeoQuestion,
    endQuestion,
    nextQuestion,
    expectFinished,
} from '../fixtures/game';

const GEO_QUIZ_TITLE = process.env.E2E_GEO_QUIZ_TITLE || 'E2E Geo Quiz';

// Smoke test for the geo_guesser question type: drop a pin → submit → score.
test('geo guesser: player drops a pin, submits, and scores', async ({ browser }) => {
    const hostContext = await browser.newContext();
    const spectatorContext = await browser.newContext();
    const playerContext = await browser.newContext();

    const hostPage = await hostContext.newPage();

    // 1. Host logs in and starts the geo quiz.
    await loginAsHost(hostPage);
    const code = await startGameFromDashboardFor(hostPage, GEO_QUIZ_TITLE);
    expect(code).toMatch(/^[A-Z0-9]+$/);

    // 2. Spectator + player join.
    const spectatorPage = await openSpectator(spectatorContext, code);
    const playerPage = await joinAsPlayer(playerContext, code, 'Geo');

    await expect(hostPage.locator('[data-test="host-player-row"][data-player-nickname="Geo"]')).toBeVisible();

    // 3. Host starts the game.
    await startGame(hostPage);

    // 4. Spectator shows the geo map; player can answer.
    await expect(spectatorPage.locator('[data-test="geo-map"]')).toBeVisible();
    await expect(playerPage.locator('[data-test="player-phase"]')).toHaveAttribute('data-phase', 'answering');

    // 5. Player drops a pin and submits.
    await answerGeoQuestion(playerPage);
    await expect(hostPage.locator('[data-test="answer-progress"]')).toHaveText(/1\s*\/\s*1/);

    // 6. Host ends the question; spectator reveals the correct location.
    await endQuestion(hostPage);
    await expect(spectatorPage.locator('[data-test="geo-map"]')).toBeVisible();

    // 7. Only one question → advancing finishes the game.
    await nextQuestion(hostPage);
    await expectFinished(hostPage);

    // 8. Player earned a non-zero distance-based score.
    const finalScoreText = await playerPage.locator('[data-test="player-final-score"]').innerText();
    expect(parseInt(finalScoreText.trim(), 10)).toBeGreaterThan(0);

    await expect(spectatorPage.locator('[data-test="spectator-phase"]')).toHaveAttribute('data-phase', 'finished');
    const geoRow = spectatorPage.locator('[data-test="spectator-leaderboard-row"][data-player-nickname="Geo"]');
    await expect(geoRow).toBeVisible();
    const geoScore = parseInt(((await geoRow.innerText()).match(/\d+/g) || ['0']).pop()!, 10);
    expect(geoScore).toBeGreaterThan(0);

    await hostContext.close();
    await spectatorContext.close();
    await playerContext.close();
});
