import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/calendar.css",
                "resources/css/forms.css",
                "resources/css/markdown.css",
                "resources/css/rates.css",
                "resources/css/properties.css",
                "resources/css/units.css",
                "resources/js/app.js",
                "resources/js/forms.js",
                "resources/js/rates.js",
                "resources/js/rate-calculator.js",
                "resources/js/units-edit.js",
            ],
            refresh: ["resources/css/**", "resources/js/**"],
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
