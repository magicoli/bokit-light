import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/calendar.css",
                "resources/css/properties.css",
                "resources/js/app.js",
                "resources/js/units-edit.js",
                "resources/css/markdown.css",
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
