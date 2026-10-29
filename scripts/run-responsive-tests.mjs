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

function terminateProcessTree(child) {
    if (!child || child.killed) return;
    if (process.platform === 'win32') {
        spawn('taskkill', ['/pid', String(child.pid), '/t', '/f'], {
            windowsHide: true,
            stdio: 'ignore',
        });
        return;
    }
    child.kill('SIGTERM');
}

function run(command, args, options = {}) {
    const {
        label = `${command} ${args.join(' ')}`,
        timeoutMs = 120_000,
        ...spawnOptions
    } = options;

    return new Promise((resolve, reject) => {
        const child = spawn(command, args, {
            cwd: root,
            env,
            stdio: 'inherit',
            windowsHide: true,
            ...spawnOptions,
        });
        let settled = false;
        const timer = setTimeout(() => {
            if (settled) return;
            settled = true;
            terminateProcessTree(child);
            reject(new Error(`${label} timed out after ${timeoutMs}ms`));
        }, timeoutMs);

        child.on('error', (error) => {
            if (settled) return;
            settled = true;
            clearTimeout(timer);
            reject(new Error(`${label} failed to start: ${error.message}`));
        });
        child.on('exit', (code, signal) => {
            if (settled) return;
            settled = true;
            clearTimeout(timer);
            if (code === 0) {
                resolve();
                return;
            }
            reject(new Error(`${label} exited with code ${code ?? 'null'}${signal ? ` (${signal})` : ''}`));
        });
    });
}

async function waitForServer(url) {
    console.log(`[responsive] waiting for ${url}`);
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
    if (process.env.RESPONSIVE_REFRESH_AUTOLOAD === '1') {
        await run(executable('composer'), ['dump-autoload', '--no-scripts', '--classmap-authoritative'], {
            label: 'composer dump-autoload',
            timeoutMs: 60_000,
            shell: process.platform === 'win32',
        });
    } else {
        console.log('[responsive] using existing vendor autoload (set RESPONSIVE_REFRESH_AUTOLOAD=1 to refresh)');
    }
    await run('php', ['artisan', 'migrate:fresh', '--force', '--no-ansi'], {
        label: 'php artisan migrate:fresh',
        timeoutMs: 60_000,
    });
    await run('php', ['artisan', 'db:seed', '--class=ResponsiveAuditSeeder', '--force', '--no-ansi'], {
        label: 'php artisan db:seed ResponsiveAuditSeeder',
        timeoutMs: 60_000,
    });

    server = spawn('php', [
        '-S',
        '127.0.0.1:8173',
        path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'),
    ], { cwd: path.join(root, 'public'), env, stdio: 'ignore' });
    await waitForServer(`${env.RESPONSIVE_BASE_URL}/up`);
    await run(executable('npx'), ['playwright', 'test', '--config=playwright.responsive.config.mjs', ...playwrightArgs], {
        label: 'playwright responsive suite',
        timeoutMs: 15 * 60_000,
        shell: process.platform === 'win32',
    });
} finally {
    terminateProcessTree(server);
    await rm(tempRoot, { recursive: true, force: true });
}
