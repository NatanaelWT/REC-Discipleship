import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    workers: 1,
    timeout: 90_000,
    expect: { timeout: 10_000 },
    reporter: [['line']],
    outputDir: 'test-results/responsive-artifacts',
    use: {
        baseURL: process.env.RESPONSIVE_BASE_URL || 'http://127.0.0.1:8173',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        colorScheme: 'light',
        reducedMotion: 'reduce',
    },
});
