<?php

it('has a valid vite manifest', function () {
    $manifest = public_path('build/manifest.json');

    expect(file_exists($manifest))->toBeTrue('Run npm run build first')
        ->and(json_decode(file_get_contents($manifest), true))->toBeArray()->not->toBeEmpty();
});
