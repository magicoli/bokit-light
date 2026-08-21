<?php

use BezhanSalleh\LanguageSwitch\LanguageSwitch;

test('every configured locale has a real, publicly reachable flag file', function () {
    $flags = LanguageSwitch::make()->getFlags();

    foreach (config('app.locales') as $locale) {
        expect($flags)->toHaveKey($locale);

        $path = public_path('vendor/blade-flags/'."language-{$locale}.svg");
        expect($path)->toBeFile();
    }
});
