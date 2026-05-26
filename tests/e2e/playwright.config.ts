import { defineConfig, devices } from '@playwright/test';
import * as dotenv from 'dotenv';
import * as path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Load .env.e2e for local overrides; CI sets env vars directly.
dotenv.config({ path: path.resolve(__dirname, '../../.env.e2e') });

const BASE_URL = process.env.E2E_BASE_URL || 'https://quiz.falkenstein.dev';

export default defineConfig({
    testDir: './specs',
    outputDir: './test-results',
    timeout: 60_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ],

    use: {
        baseURL: BASE_URL,
        actionTimeout: 10_000,
        navigationTimeout: 15_000,
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        trace: 'on-first-retry',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
