<?php

uses(
    Tests\TestCase::class,
    // Illuminate\Foundation\Testing\RefreshDatabase::class,
)
    ->beforeAll(function () {
        // Runs before each file...
        // echo "Processing file..." . PHP_EOL;
    })
    ->beforeEach(function () {
        // Runs before each test...
        // $testName = $this->name() ?? "test";
        // $testName = new ReflectionClass($this)->getName();
        // $testClass = preg_replace("/.*\\\Tests\\\/", "", self::class);
        $testName = preg_replace(
            "/_/",
            " ",
            preg_replace("/__(.*)__(.*)__→_/", '\\2 → ', $this->name()),
        );
        echo "  {$testName}" . PHP_EOL;
    })
    ->afterEach(function () {
        // Runs after each test...
    })
    ->afterAll(function () {
        // Runs after each file...
        echo "File completed" . PHP_EOL;
    })
    ->group("integration")
    ->in("Feature");

// stream_context_set_default([
//     "ssl" => [
//         "verify_peer" => false,
//         "verify_peer_name" => false,
//     ],
// ]);
