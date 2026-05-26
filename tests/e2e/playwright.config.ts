import { defineConfig, devices } from '@playwright/test';
import * as dotenv from 'dotenv';
import * as path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

dotenv.config({ path: path.resolve(__dirname, '../../.env.e2e') });

const APP_PORT = 8001;
const REVERB_PORT = 8081;
const BASE_URL = `http://localhost:${APP_PORT}`;

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

    webServer: [
        {
            name: 'laravel-serve',
            command: 'php artisan migrate:fresh --seeder=E2eGameSeeder --force --env=e2e && php artisan serve --port=8001 --env=e2e',
            cwd: path.resolve(__dirname, '../..'),
            url: BASE_URL,
            reuseExistingServer: false,
            timeout: 60_000,
            stdout: 'pipe',
            stderr: 'pipe',
        },
        {
            name: 'reverb',
            command: `php artisan reverb:start --port=${REVERB_PORT} --env=e2e`,
            cwd: path.resolve(__dirname, '../..'),
            url: `http://localhost:${REVERB_PORT}`,
            reuseExistingServer: false,
            timeout: 30_000,
            stdout: 'pipe',
            stderr: 'pipe',
        },
        {
            // Queue worker has no HTTP port; reuse the app URL as the liveness check.
            // Playwright proceeds once the app URL responds — guaranteed since laravel-serve starts first.
            name: 'queue',
            command: 'php artisan queue:work --queue=default --tries=1 --timeout=30 --env=e2e',
            cwd: path.resolve(__dirname, '../..'),
            url: BASE_URL,
            reuseExistingServer: false,
            timeout: 30_000,
            stdout: 'pipe',
            stderr: 'pipe',
        },
    ],
});
