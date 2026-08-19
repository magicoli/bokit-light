<?php

describe('Build', function () {
    test('has a valid vite manifest', function () {
        $manifest = public_path('build/manifest.json');

        // The dev watcher (composer run dev, vite build --watch) rewrites this file on every
        // rebuild, so it can be momentarily absent; retry briefly rather than flake on that
        // window. In CI, where nothing rewrites it, the first read already succeeds.
        $contents = null;
        for ($attempt = 0; $attempt < 10 && ($contents === null || $contents === false); $attempt++) {
            $contents = @file_get_contents($manifest);
            if ($contents === false) {
                usleep(100_000);
            }
        }

        expect($contents)->not->toBeFalse('Run npm run build first')
            ->and(json_decode($contents, true))->toBeArray()->not->toBeEmpty();
    });
});
