import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import fs from 'node:fs';
import { defineConfig, type ServerOptions } from 'vite';

/**
 * Dev server must match the browser hostname (ir4-project.test via hosts/DNS),
 * not the LAN IP — otherwise the TLS cert SAN fails and Firefox reports a
 * bogus CORS error with status (null).
 *
 * Generate certs on the machine that runs Vite (do not reuse MediaMTX certs):
 *   mkcert -cert-file auto.crt -key-file auto.key \
 *     ir4-project.test "*.ir4-project.test" localhost 127.0.0.1 ::1
 *
 * Override host with VITE_DEV_HOST if needed.
 */
const devHost: string = process.env.VITE_DEV_HOST ?? 'ir4-project.test';
const keyPath: string = './auto.key';
const certPath: string = './auto.crt';
const hasTls: boolean = fs.existsSync(keyPath) && fs.existsSync(certPath);

const server: ServerOptions = {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    cors: {
        origin: [`https://${devHost}`, `http://${devHost}`],
    },
    hmr: {
        host: devHost,
        port: 5173,
        protocol: hasTls ? 'wss' : 'ws',
        clientPort: 5173,
    },
};

if (hasTls) {
    server.https = {
        key: fs.readFileSync(keyPath),
        cert: fs.readFileSync(certPath),
    };
    server.origin = `https://${devHost}:5173`;
} else {
    server.origin = `http://${devHost}:5173`;
}

export default defineConfig({
    server,
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
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
        }),
    ],
});
