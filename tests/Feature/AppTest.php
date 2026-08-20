<?php

echo "Processing " . basename(__FILE__);

describe("home page", function () {
    $validcodes = [200, 302, 307];
    test("has status in " . implode(" ", $validcodes), function () use (
        $validcodes,
    ) {
        $response = $this->get("/");
        $status = $response->getStatusCode();
        // $response->assertStatus(200);

        expect($status)->toBeInt()->toBein($validcodes);
    });

    test("passes a dummy test", function () {
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

describe("logging", function () {
    test("rotates by default, so no single file can grow without bound", function () {
        expect(config("logging.default"))->toBe("daily");
        expect(config("logging.channels.daily.days"))->toBeGreaterThan(0);
    });
});

describe("version resolution", function () {
    // Reproduces the production deploy path: APP_ENV=production, APP_DEBUG off, no APP_VERSION and
    // (on a fresh release) no storage/version — exactly the state during `composer install` →
    // post-autoload-dump → package:discover. config/app.php then falls back to
    // Composer\InstalledVersions, which lives in the global-namespace config file and MUST be
    // fully qualified: an unqualified reference resolved to \InstalledVersions, threw "Class
    // InstalledVersions not found", and took the whole deploy down (v1.1.0). Run in a subprocess
    // so the config file is evaluated fresh under that environment rather than the test's.
    test("boots under production env without a stored version", function () {
        $code = 'require "vendor/autoload.php";'
            . '$app = require "bootstrap/app.php";'
            . '$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();'
            . 'echo config("app.version");';

        $result = \Illuminate\Support\Facades\Process::path(base_path())
            ->env(["APP_ENV" => "production", "APP_DEBUG" => "false", "APP_VERSION" => ""])
            ->run([PHP_BINARY, "-r", $code]);

        expect($result->successful())->toBeTrue();
        expect(trim($result->output()))->not->toBe("");
    });
});
