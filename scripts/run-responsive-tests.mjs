import { mkdtemp, open, rm } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import { tmpdir } from 'node:os';
import path from 'node:path';
import process from 'node:process';

const root = process.cwd();
const playwrightArgs = process.argv.slice(2);
const tempRoot = await mkdtemp(path.join(tmpdir(), 'rec-responsive-'));
const database = path.join(tempRoot, 'responsive.sqlite');
const configCache = path.join(tempRoot, 'config.php');
await open(database, 'w').then((file) => file.close());

const env = {
    ...process.env,
    APP_ENV: 'testing',
    APP_DEBUG: 'false',
    APP_CONFIG_CACHE: configCache,
    APP_URL: 'http://127.0.0.1:8173',
    CACHE_STORE: 'array',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    LOG_CHANNEL: 'null',
    QUEUE_CONNECTION: 'sync',
    RESPONSIVE_BASE_URL: 'http://127.0.0.1:8173',
    SESSION_DRIVER: 'file',
};

function executable(name) {
    if (process.platform !== 'win32') return name;
    return name === 'composer' ? 'composer.bat' : `${name}.cmd`;
}

function run(command, args, options = {}) {
    return new Promise((resolve, reject) => {
        const child = spawn(command, args, {
            cwd: root,
            env,
            stdio: 'inherit',
            ...options,
        });
        child.on('error', reject);
        child.on('exit', (code) => code === 0
            ? resolve()
            : reject(new Error(`${command} exited with code ${code}`)));
    });
}

async function waitForServer(url) {
    const deadline = Date.now() + 30_000;
    while (Date.now() < deadline) {
        try {
            const response = await fetch(url);
            if (response.ok) return;
        } catch {
            // Server is still starting.
        }
        await new Promise((resolve) => setTimeout(resolve, 250));
    }
    throw new Error(`Responsive audit server did not start at ${url}`);
}

let server;
try {
    await run(executable('composer'), ['dump-autoload', '--no-scripts', '--classmap-authoritative'], {
        shell: process.platform === 'win32',
        stdio: 'ignore',
    });
    await run('php', ['artisan', 'migrate:fresh', '--force', '--no-ansi'], { stdio: 'ignore' });
    await run('php', ['artisan', 'db:seed', '--class=ResponsiveAuditSeeder', '--force', '--no-ansi'], { stdio: 'ignore' });

    server = spawn('php', [
        '-S',
        '127.0.0.1:8173',
        path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'),
    ], { cwd: path.join(root, 'public'), env, stdio: 'ignore' });
    await waitForServer(`${env.RESPONSIVE_BASE_URL}/up`);
    await run(executable('npx'), ['playwright', 'test', '--config=playwright.responsive.config.mjs', ...playwrightArgs], {
        shell: process.platform === 'win32',
    });
} finally {
    if (server && !server.killed) server.kill();
    await rm(tempRoot, { recursive: true, force: true });
}
