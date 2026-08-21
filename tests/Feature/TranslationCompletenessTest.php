<?php

/**
 * Every locale in config('app.locales') must have a full, matching set of translation keys —
 * this is what makes a language actually selectable rather than a silent wall of fallback-locale
 * text (dev/project-tenant-sub-sites.md). `en` is the reference: every lang/en/*.php file must
 * exist, with the exact same keys (recursively), under lang/{locale}/.
 *
 * @return array<int, string>
 */
function flattenTranslationKeys(array $array, string $prefix = ''): array
{
    $keys = [];

    foreach ($array as $key => $value) {
        $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        if (is_array($value)) {
            $keys = [...$keys, ...flattenTranslationKeys($value, $path)];
        } else {
            $keys[] = $path;
        }
    }

    return $keys;
}

/**
 * @return array<int, string>
 */
function referenceTranslationFiles(): array
{
    return collect(glob(lang_path('en/*.php')))
        ->map(fn (string $path): string => basename($path))
        ->sort()
        ->values()
        ->all();
}

/**
 * @return array<int, string>
 */
function nonEnglishConfiguredLocales(): array
{
    return collect(config('app.locales'))
        ->reject(fn (string $locale): bool => $locale === 'en')
        ->values()
        ->all();
}

test('every configured locale has every reference translation file, with the exact same keys as en', function () {
    $files = referenceTranslationFiles();
    $failures = [];

    foreach (nonEnglishConfiguredLocales() as $locale) {
        foreach ($files as $file) {
            $localePath = lang_path("{$locale}/{$file}");

            if (! file_exists($localePath)) {
                $failures[] = "{$locale}/{$file}: file missing";

                continue;
            }

            $referenceKeys = flattenTranslationKeys(require lang_path("en/{$file}"));
            $localeKeys = flattenTranslationKeys(require $localePath);

            $missing = array_values(array_diff($referenceKeys, $localeKeys));
            $extra = array_values(array_diff($localeKeys, $referenceKeys));

            if ($missing !== []) {
                $failures[] = "{$locale}/{$file}: missing keys: ".implode(', ', $missing);
            }
            if ($extra !== []) {
                $failures[] = "{$locale}/{$file}: extra keys not in en: ".implode(', ', $extra);
            }
        }
    }

    expect($failures)->toBe([]);
});
