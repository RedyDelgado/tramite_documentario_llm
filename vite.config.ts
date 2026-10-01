import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

const port = Number(process.env.VITE_PORT ?? 5174);

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
    // En Docker: escucha en todas las interfaces y el navegador conecta por localhost.
    server: {
        host: '0.0.0.0',
        port,
        strictPort: true,
        hmr: { host: 'localhost' },
        watch: {
            // Los bind mounts de Windows no emiten eventos de archivo dentro del contenedor:
            // se sondea cada segundo y solo lo que afecta al frontend.
            usePolling: process.env.VITE_POLLING === 'true',
            interval: 1000,
            ignored: [
                '**/vendor/**',
                '**/storage/**',
                '**/public/**',
                '**/app/**',
                '**/bootstrap/**',
                '**/config/**',
                '**/database/**',
                '**/tests/**',
                '**/docker/**',
                '**/ai-service/**',
                '**/docs/**',
            ],
        },
    },
});
