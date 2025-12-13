<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->in("Feature");

test("example", function () {
    $response = $this->get("/");

    $response->assertStatus(200);
});
