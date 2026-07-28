<?php

use App\Http\Controllers\UnitController;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->property = Property::create(['name' => 'Mosaiques', 'slug' => 'mosaiques', 'is_active' => true]);
    $this->unit = Unit::create([
        'property_id' => $this->property->id,
        'name' => 'Moon',
        'slug' => 'moon',
        'is_active' => true,
    ]);
});

it('serves the unit page it is meant to serve', function () {
    $this->get('/mosaiques/moon')->assertSuccessful();
});

it('answers 404 when the unit does not belong to the property', function () {
    $other = Property::create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);

    $this->get("/{$other->slug}/{$this->unit->slug}")->assertNotFound();
});

/**
 * The case scanners hit all day: //shell.php matches the catch-all {property}/{unit} with an empty
 * first segment. Route model binding is skipped for an empty segment, and Laravel then resolves the
 * type-hints from the container — so the controller receives BLANK models, not null. Both ids being
 * null, a plain !== comparison saw no mismatch and let the request reach the view, where it became
 * a 500.
 *
 * Called directly rather than over HTTP on purpose: the test client normalises "//shell.php" into
 * "/shell.php", so an HTTP test would pass without ever reproducing this.
 */
it('answers 404 when route binding resolved nothing', function () {
    expect(fn () => app(UnitController::class)->show(new Property, new Unit))
        ->toThrow(NotFoundHttpException::class);
});
