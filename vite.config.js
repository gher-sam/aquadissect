import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 still uses Sass's legacy import/color APIs.
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.js', 'resources/css/privacy.css'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
