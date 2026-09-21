import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/urun.js'],
            refresh: true,
            /*
             * Fontlar derleme aninda indirilip kendi sunucumuzdan servis edilir.
             * Calisma aninda hicbir dis istege cikilmaz (gizlilik + hiz).
             *
             * Poppins: PressMart referansinin govde ve baslik yazisi.
             * Cormorant Garamond italik: yalnizca vurgu satiri (referanstaki
             * el yazisi "Summer Sale" satirinin markaya uyarlanmis hali).
             */
            fonts: [
                bunny('Poppins', { weights: [300, 400, 500, 600, 700] }),
                bunny('Cormorant Garamond', { weights: [500], styles: ['italic'] }),
            ],
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
