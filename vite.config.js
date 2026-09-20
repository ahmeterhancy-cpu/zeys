import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            /*
             * Fontlar derleme aninda indirilip kendi sunucumuzdan servis edilir.
             * Calisma aninda hicbir dis istege cikilmaz (gizlilik + hiz).
             *
             * Cormorant Garamond: logonun el yazisina eslik eden zarif serif.
             * Jost: logodaki genis aralikli "FASHION HOUSE" geometrik sans.
             */
            fonts: [
                bunny('Cormorant Garamond', { weights: [400, 500, 600], styles: ['normal', 'italic'] }),
                bunny('Jost', { weights: [300, 400, 500, 600] }),
            ],
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
