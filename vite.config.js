import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                // Manifeste CSS de l'espace public (base + coquille), charge sur toutes les pages web.
                'resources/css/web.css',
                // Un point d'entree CSS par page (charge uniquement par la page concernee).
                'resources/css/web/home/index.css',
                'resources/css/web/about/index.css',
                'resources/css/web/understand-gpsr/index.css',
                'resources/css/web/services/index.css',
                'resources/css/web/pricing/index.css',
                'resources/css/web/excluded-products/index.css',
                'resources/css/web/get-started/index.css',
                'resources/css/web/contact/index.css',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
