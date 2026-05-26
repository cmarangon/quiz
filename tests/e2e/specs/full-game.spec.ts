import { test, expect } from '@playwright/test';
import {
    loginAsHost,
    startGameFromDashboard,
    openSpectator,
    joinAsPlayer,
    startGame,
    answerQuestion,
    endQuestion,
    nextQuestion,
    expectFinished,
} from '../fixtures/game';

// The three questions in E2eGameSeeder, with their correct and a wrong answer.
const QUESTIONS: { body: string; correct: string; wrong: string }[] = [
    { body: 'What is 2 + 2?', correct: '4', wrong: '3' },
    { body: 'What color is the sky on a clear day?', correct: 'Blue', wrong: 'Green' },
    { body: 'How many sides does a triangle have?', correct: '3', wrong: '2' },
];

test('full game: host + spectator + 2 players play through every question', async ({ browser }) => {
    const hostContext = await browser.newContext();
    const spectatorContext = await browser.newContext();
    const aliceContext = await browser.newContext();
    const bobContext = await browser.newContext();

    const hostPage = await hostContext.newPage();

    // 1. Host logs in and starts a game
    await loginAsHost(hostPage);
    const code = await startGameFromDashboard(hostPage);
    expect(code).toMatch(/^[A-Z0-9]+$/);

    // 2. Spectator joins lobby
    const spectatorPage = await openSpectator(spectatorContext, code);
    await expect(spectatorPage.locator('[data-test="spectator-phase"]')).toHaveAttribute('data-phase', 'lobby');

    // 3. Players join
    const alicePage = await joinAsPlayer(aliceContext, code, 'Alice');
    const bobPage = await joinAsPlayer(bobContext, code, 'Bob');

    // WebSocket fan-out: host and spectator both see both players
    await expect(hostPage.locator('[data-test="host-player-row"][data-player-nickname="Alice"]')).toBeVisible();
    await expect(hostPage.locator('[data-test="host-player-row"][data-player-nickname="Bob"]')).toBeVisible();
    await expect(spectatorPage.locator('[data-test="spectator-player-chip"][data-player-nickname="Alice"]')).toBeVisible();
    await expect(spectatorPage.locator('[data-test="spectator-player-chip"][data-player-nickname="Bob"]')).toBeVisible();
    await expect(spectatorPage.locator('[data-test="spectator-player-count"]')).toHaveText('2');

    // 4. Host starts the game
    await startGame(hostPage);

    // 5. Play every question; calling nextQuestion on the last one triggers GameFinished
    for (let i = 0; i < QUESTIONS.length; i++) {
        const q = QUESTIONS[i];

        await expect(spectatorPage.locator('[data-test="spectator-question-body"]')).toHaveText(q.body);

        await expect(alicePage.locator('[data-test="player-phase"]')).toHaveAttribute('data-phase', 'answering');
        await expect(bobPage.locator('[data-test="player-phase"]')).toHaveAttribute('data-phase', 'answering');

        // Alice correct, Bob wrong — deterministic score delta
        await answerQuestion(alicePage, q.correct);
        await answerQuestion(bobPage, q.wrong);

        await expect(hostPage.locator('[data-test="answer-progress"]')).toHaveText(/2\s*\/\s*2/);

        await endQuestion(hostPage);

        // Always advance; on last question this broadcasts GameFinished
        await nextQuestion(hostPage);
    }

    // 6. End of game assertions
    await expectFinished(hostPage);
    await expect(spectatorPage.locator('[data-test="spectator-phase"]')).toHaveAttribute('data-phase', 'finished');

    // Alice (all correct) outranks Bob (all wrong) on the spectator scoreboard
    const aliceRow = spectatorPage.locator('[data-test="spectator-leaderboard-row"][data-player-nickname="Alice"]');
    const bobRow = spectatorPage.locator('[data-test="spectator-leaderboard-row"][data-player-nickname="Bob"]');
    await expect(aliceRow).toBeVisible();
    await expect(bobRow).toBeVisible();

    const aliceScore = parseInt(((await aliceRow.innerText()).match(/\d+/g) || ['0']).pop()!, 10);
    const bobScore = parseInt(((await bobRow.innerText()).match(/\d+/g) || ['0']).pop()!, 10);
    expect(aliceScore).toBeGreaterThan(bobScore);
    expect(aliceScore).toBe(30); // 3 correct × 10 points
    expect(bobScore).toBe(0);

    // Players see the finished screen
    await expect(alicePage.locator('[data-test="player-final-score"]')).toHaveText('30');
    await expect(bobPage.locator('[data-test="player-final-score"]')).toHaveText('0');

    await hostContext.close();
    await spectatorContext.close();
    await aliceContext.close();
    await bobContext.close();
});
