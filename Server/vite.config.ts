import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, type ServerOptions } from 'vite';

/**
 * Local default: HTTP Vite on 127.0.0.1 (matches `php artisan serve`).
 *
 * Herd HTTPS (optional):
 *   VITE_DEV_HTTPS=1 VITE_DEV_HOST=ir4-project.test npm run dev
 *   with mkcert auto.crt / auto.key for ir4-project.test
 */
const useHttps: boolean =
    process.env.VITE_DEV_HTTPS === '1' &&
    fs.existsSync('./auto.key') &&
    fs.existsSync('./auto.crt');

const devHost: string =
    process.env.VITE_DEV_HOST ?? (useHttps ? 'ir4-project.test' : '127.0.0.1');

const rootDir: string = path.dirname(fileURLToPath(import.meta.url));

const server: ServerOptions = {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    origin: `${useHttps ? 'https' : 'http'}://${devHost}:5173`,
    cors: {
        origin: [
            'http://127.0.0.1:8000',
            'http://127.0.0.1:8001',
            'http://localhost:8000',
            'http://localhost:8001',
            'https://localhost:8000',
            'https://localhost:8001',
            'http://ir4-project.test',
            'https://ir4-project.test',
            `http://${devHost}`,
            `https://${devHost}`,
        ],
    },
    hmr: {
        host: devHost,
        port: 5173,
        clientPort: 5173,
        protocol: useHttps ? 'wss' : 'ws',
    },
};

if (useHttps) {
    server.https = {
        key: fs.readFileSync('./auto.key'),
        cert: fs.readFileSync('./auto.crt'),
    };
}

export default defineConfig({
    server,
    optimizeDeps: {
        include: ['pusher-js', 'laravel-echo', '@laravel/echo-react'],
    },
    ssr: {
        // Real pusher-js Node build uses require() and breaks Vite SSR. Stub it;
        // app.tsx configures Echo with the null broadcaster during SSR.
        resolve: {
            alias: {
                'pusher-js': path.resolve(
                    rootDir,
                    'resources/js/shims/pusher-js-ssr.ts',
                ),
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
            command:
                process.env.WAYFINDER_COMMAND ??
                'php artisan wayfinder:generate',
        }),
    ],
});
