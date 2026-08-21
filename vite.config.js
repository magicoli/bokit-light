import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
// import { local } from 'laravel-vite-plugin/fonts';
// import local from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";
import { execFileSync } from "node:child_process";

/**
 * Regenerates the wallpapers as part of the build itself — closeBundle fires after every build,
 * including each rebuild in `vite build --watch` (the "start"/"dev" scripts), so this is not a
 * separate step a developer or a deploy script has to remember to run on the side (see °118).
 */
function wallpapers() {
    return {
        name: "bokit-wallpapers",
        closeBundle() {
            execFileSync("php", ["artisan", "bokit:wallpapers"], { stdio: "inherit" });
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            // One main entry per surface, each importing its own secondary resources, instead
            // of a top-level entry per file: `vite build --watch` reprocesses the whole input
            // graph on every save, so ~30 entries meant every save rebuilt everything and left
            // the manifest in a broken intermediate state for a few seconds - a real source of
            // the ViteManifestNotFoundException flakiness during development.
            // - panels.css/panels.js: the Filament panels (imports _theme, legacy, fonts, glass)
            // - app.css/app.js: the legacy (pre-Filament) front-end (imports layout-grid, form,
            //   list, login, properties, units, rates-widget, flatpickr, admin)
            // - calendar.css, rates.css/rates.js, home.css/home.js: kept separate because each
            //   is heavy and used on exactly one page - bundling them into app/panels would ship
            //   their weight everywhere for no benefit
            // - fonts.css: also imported by panels.css and app.css, but must stay its own entry
            //   too since HasSharedPanelConfig resolves it directly via Vite::asset()
            input: [
                "resources/css/app.css",
                "resources/css/calendar.css",
                "resources/css/fonts.css",
                "resources/css/home.css",
                "resources/css/panels.css",
                "resources/css/rates.css",
                "resources/js/app.js",
                "resources/js/home.js",
                "resources/js/panels.js",
                "resources/js/rates.js",
            ],
            // detectTls: 'bokit-light.test',
            refresh: true,
            // refresh: [
            //     "resources/css/**",
            //     "resources/fonts/**",
            //     "resources/js/**",
            // ],
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
                "assets/logos/**",
                "assets/fonts/**"
            ],
        }),
        tailwindcss(),
        wallpapers(),
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
