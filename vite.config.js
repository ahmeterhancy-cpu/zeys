import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Fontlar logo geldikten sonra secilecek; bunny() ile derleme aninda
            // indirilip kendi sunucumuzdan servis edilecek (calisma aninda dis istek yok).
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
