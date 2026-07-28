<?php

use App\Models\Property;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;


uses(RefreshDatabase::class);

beforeEach(function () {
    $this->property = Property::create(['name' => 'Mosaiques', 'slug' => 'mosaiques', 'is_active' => true]);
    Unit::create([
        'property_id' => $this->property->id,
        'name' => 'Moon',
        'slug' => 'moon',
        'is_active' => true,
    ]);
});

// The limiter counts per address and its counters outlive a test, so each test browses from its
// own one rather than resetting shared state.
it('lets ordinary browsing through', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);

    foreach (range(1, 20) as $ignored) {
        $this->get('/mosaiques/moon')->assertSuccessful();
    }
});

it('answers 429 once the public limit is exceeded', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);

    foreach (range(1, 60) as $ignored) {
        $this->get('/mosaiques/moon');
    }

    $this->get('/mosaiques/moon')->assertStatus(429);
});

/**
 * The log line is the point of the exercise, not the rejection: it is what tells afterwards
 * whether the limit ever fired on ordinary use — which would say something about the pages rather
 * than about the visitor.
 */
it('logs what was rejected, with enough to judge it', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3']);

    $logged = null;
    Log::listen(function ($message) use (&$logged) {
        if (str_contains($message->message, '[Throttle]')) {
            $logged ??= $message->context;
        }
    });

    foreach (range(1, 61) as $ignored) {
        $this->get('/mosaiques/moon?probe=1');
    }

    expect($logged)->not->toBeNull();
    expect($logged['url'])->toContain('/mosaiques/moon?probe=1');
    expect($logged)->toHaveKeys(['ip', 'user_agent', 'referer', 'route', 'method']);
    expect($logged['route'])->toBe('units.show');
});
