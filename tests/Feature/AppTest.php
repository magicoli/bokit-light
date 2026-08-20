<?php

use Illuminate\Support\Facades\Process;

echo 'Processing '.basename(__FILE__);

describe('home page', function () {
    $validcodes = [200, 302, 307];
    test('has status in '.implode(' ', $validcodes), function () use (
        $validcodes,
    ) {
        $response = $this->get('/');
        $status = $response->getStatusCode();
        // $response->assertStatus(200);

        expect($status)->toBeInt()->toBein($validcodes);
    });

    test('passes a dummy test', function () {
        expect(true)->toBe(true);
    });
    // it("fails dummy test", function () {
    //     expect(true)->toBe(false);
    // });
    // it("reports properly a true Exception or Error", function () {
    //     $result = SomeWrongClass::someMethod();
    //     expect($result)->toBe(true);
    // });
    // it("passes another longer dummy test", function () {
    //     sleep(1);
    //     expect(true)->toBe(true);
    // });
    // it("performs a long test", function () {
    //     sleep(5);
    //     $this->assertTrue(true);
    // });
});

describe('logging', function () {
    test('rotates by default, so no single file can grow without bound', function () {
        expect(config('logging.default'))->toBe('daily');
        expect(config('logging.channels.daily.days'))->toBeGreaterThan(0);
    });
});

describe('panel component cache', function () {
    // filament:optimize (run on every deploy) var_export's each panel's registered widgets. A
    // WidgetConfiguration object there — what TicketStatsWidget::make() returns — emits
    // Class::__set_state(...), which WidgetConfiguration does not implement, so loading the cache
    // 500'd every request on the app panel (prod, releases/17). Register the class string instead.
    // Cache the components, then boot a fresh process that resolves the panel (which loads the
    // cache file); a regression reproduces the load-time fatal. Always clear so no cache is left
    // behind for the rest of the suite or local dev.
    test('the app panel survives filament:optimize caching', function () {
        $run = fn (array $cmd) => Process::path(base_path())
            ->env(['APP_ENV' => 'production', 'APP_DEBUG' => 'false'])
            ->run($cmd);

        try {
            expect($run([PHP_BINARY, 'artisan', 'filament:cache-components'])->successful())->toBeTrue();

            $boot = $run([PHP_BINARY, '-r',
                'require "vendor/autoload.php";'
                .'$app = require "bootstrap/app.php";'
                .'$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();'
                .'\Filament\Facades\Filament::getPanel("app");'
                .'echo "ok";',
            ]);

            expect($boot->successful())->toBeTrue();
            expect($boot->output())->toContain('ok');
        } finally {
            $run([PHP_BINARY, 'artisan', 'filament:clear-cached-components']);
        }
    });
});

describe('version resolution', function () {
    // Reproduces the production deploy path: APP_ENV=production, APP_DEBUG off, no APP_VERSION and
    // (on a fresh release) no storage/version — exactly the state during `composer install` →
    // post-autoload-dump → package:discover. config/app.php then falls back to
    // Composer\InstalledVersions, which lives in the global-namespace config file and MUST be
    // fully qualified: an unqualified reference resolved to \InstalledVersions, threw "Class
    // InstalledVersions not found", and took the whole deploy down (v1.1.0). Run in a subprocess
    // so the config file is evaluated fresh under that environment rather than the test's.
    test('boots under production env without a stored version', function () {
        $code = 'require "vendor/autoload.php";'
            .'$app = require "bootstrap/app.php";'
            .'$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();'
            .'echo config("app.version");';

        $result = Process::path(base_path())
            ->env(['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_VERSION' => ''])
            ->run([PHP_BINARY, '-r', $code]);

        expect($result->successful())->toBeTrue();
        expect(trim($result->output()))->not->toBe('');
    });
});
