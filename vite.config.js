import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        // Tailwind : utilise uniquement par le back-office (resources/css/admin.css importe "tailwindcss").
        // L'espace public reste en CSS modulaire pur (aucun @import "tailwindcss").
        tailwindcss(),
        laravel({
            input: [
                'resources/js/app.js',
                // Manifeste JS de l'espace public (coquille), charge sur toutes les pages web.
                'resources/js/web.js',
                // Manifeste CSS de l'espace public (base + coquille), charge sur toutes les pages web.
                'resources/css/web.css',
                // Back-office (auth) : espace separe, styles sur-mesure.
                'resources/css/admin.css',
                // Un point d'entree CSS par page (charge uniquement par la page concernee).
                'resources/css/web/home/index.css',
                'resources/css/web/about/index.css',
                'resources/css/web/understand-gpsr/index.css',
                'resources/css/web/services/index.css',
                'resources/css/web/pricing/index.css',
                'resources/css/web/excluded-products/index.css',
                'resources/css/web/get-started/index.css',
                'resources/css/web/get-started/journey.css',
                'resources/css/web/contact/index.css',
                'resources/css/web/legal/index.css',
                'resources/css/web/errors/index.css',
                // Back-office : design system Falcon UI Kit (Tailwind + preset + composants).
                'resources/css/ui-kit.css',
                'resources/js/ui-kit.js',
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
