<?php

echo "Processing " . basename(__FILE__);

describe("home page", function () {
    $validcodes = [200, 302, 307];
    it("has status in " . implode(" ", $validcodes), function () use (
        $validcodes,
    ) {
        $response = $this->get("/");
        $status = $response->getStatusCode();
        // $response->assertStatus(200);

        expect($status)->toBeInt()->toBein($validcodes);
    });

    it("passes a dummy test", function () {
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
