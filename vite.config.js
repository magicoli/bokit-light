import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/calendar.css",
                "resources/css/markdown.css",
                "resources/css/properties.css",
                "resources/js/app.js",
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
