<?php

describe('Build', function () {
    test('has a valid vite manifest', function () {
        $manifest = public_path('build/manifest.json');

        // The dev watcher (composer run dev, vite build --watch) rewrites this file on every
        // rebuild, so it can be momentarily missing or half-written; retry until it reads as a
        // non-empty array rather than flake on that window. In CI, where nothing rewrites it, the
        // first read already succeeds.
        $data = null;
        for ($attempt = 0; $attempt < 30 && ! is_array($data); $attempt++) {
            $raw = @file_get_contents($manifest);
            $data = $raw === false ? null : json_decode($raw, true);

            if (! is_array($data) || $data === []) {
                $data = null;
                usleep(100_000);
            }
        }

        expect($data)->toBeArray('Run npm run build first')->not->toBeEmpty();
    });
});
