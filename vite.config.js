import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
// import { local } from 'laravel-vite-plugin/fonts';
// import local from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/_theme.css",
                "resources/css/admin.css",
                "resources/css/app.css",
                "resources/css/calendar.css",
                "resources/css/flatpickr.css",
                "resources/css/form.css",
                "resources/css/fonts.css",
                "resources/css/glass.css",
                "resources/css/home.css",
                "resources/css/layout-flex.css",
                "resources/css/layout-grid.css",
                "resources/css/legacy.css",
                "resources/css/list.css",
                "resources/css/login.css",
                "resources/css/markdown.css",
                "resources/css/panels.css",
                "resources/css/properties.css",
                "resources/css/rates-widget.css",
                "resources/css/rates.css",
                "resources/css/units.css",
                "resources/css/user.css",
                "resources/js/app.js",
                "resources/js/bootstrap.js",
                "resources/js/flatpickr-previous.js",
                "resources/js/flatpickr.js",
                "resources/js/forms.js",
                "resources/js/home.js",
                "resources/js/panels.js",
                "resources/js/pwa.js",
                "resources/js/rates.js",
                "resources/js/units-edit.js",
            ],
            // detectTls: 'bokit-light.test',
            // refresh: true,
            refresh: [
                "resources/css/**",
                "resources/fonts/**",
                "resources/js/**",
            ],
            // fonts: [
            //     google('Inter', { alias: 'sans' }),
            //     bunny('Figtree', { alias: 'body' }),
            //     fontsource('JetBrains Mono', { alias: 'mono' }),
            //     local('Switzer', {
            //         alias: 'switzer',
            //         src: 'resources/fonts/brand-sans',
            //     }),
            // ],
            assets: [
                "assets/images/**",
                "assets/flags/**",
                "resources/fonts/**"
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: [
                "**/vendor/**",
                "**/node_modules/**",
                "**/storage/**",
                "**/bootstrap/cache/**",
                "**/.git/**",
            ],
        },
    },
});
