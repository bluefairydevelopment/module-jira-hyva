import { defineConfig, devices } from '@playwright/test';

/**
 * BASE_URL IS REQUIRED - refuse rather than guess a host.
 *
 * Taken from module-order-comments, where a default of the marketing sandbox meant
 * every admin spec ran against a host that could not contain the feature and failed
 * for a reason that had nothing to do with the code.
 */
const baseURL = process.env.BASE_URL;

if (!baseURL) {
    throw new Error(
        'BASE_URL is not set. Point it at a Magento install with BlueFairy_Jira enabled, e.g.\n'
        + '  BASE_URL=https://luma.example ADMIN_PATH=/admin npx playwright test\n'
    );
}

export default defineConfig({
    testDir: './tests',
    timeout: 300_000,
    expect: { timeout: 15_000 },
    // One worker. Both ACL specs drive the SAME admin area as different users, and
    // Magento's admin session is per-cookie-jar but the ACL cache is not - two roles
    // being edited concurrently is a race whose failure looks like a permission bug.
    workers: 1,
    fullyParallel: false,
    reporter: 'line',
    use: {
        baseURL,
        ignoreHTTPSErrors: true,
        viewport: { width: 1440, height: 1100 },
        actionTimeout: 30_000,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
