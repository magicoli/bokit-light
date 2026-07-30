<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves the home page to a visitor', function () {
    $this->get('/')->assertSuccessful()->assertSee('BOKIT');
});

it('serves the home page to somebody signed in', function () {
    // The panel builds a user menu as soon as a request is authenticated, and that menu needs the
    // panel's own logout route. An anonymous request never touches it — which is how a missing
    // route went unnoticed twice, the page answering 200 to curl and 500 to a signed-in browser.
    $user = User::create([
        'name' => 'Visitor',
        'email' => 'visitor@test.local',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user)->get('/')->assertSuccessful();
});
