<?php

describe("alpine loading", function () {
    test("loads alpine only through livewire, never from a cdn", function () {
        $html = $this->get("/")->getContent();

        expect($html)->not->toContain("alpinejs");
        expect($html)->toContain("livewire.js");
    });

    test("keeps the alpine directives the layout relies on", function () {
        $html = $this->get("/")->getContent();

        expect($html)->toContain("x-data");
    });
});
