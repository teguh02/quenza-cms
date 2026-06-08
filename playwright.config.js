const { defineConfig } = require('@playwright/test');

const useExternalServer = process.env.PLAYWRIGHT_USE_EXTERNAL_SERVER === '1';

module.exports = defineConfig({
    testDir: './tests/E2E',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    timeout: 60_000,
    expect: {
        timeout: 10_000,
    },
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ],
    use: {
        baseURL: 'http://127.0.0.1:8000',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: {
                browserName: 'chromium',
            },
        },
    ],
    globalSetup: require.resolve('./playwright.global-setup.js'),
    globalTeardown: require.resolve('./playwright.global-teardown.js'),
    webServer: useExternalServer
        ? undefined
        : {
              command: 'php -S 127.0.0.1:8000 -t public',
              url: 'http://127.0.0.1:8000/install',
              timeout: 120_000,
              reuseExistingServer: !process.env.CI,
          },
});
