<?php

use App\Models\Booking;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->property = Property::create(['name' => 'P', 'slug' => 'p', 'is_active' => true]);
    $this->unit = Unit::create(['property_id' => $this->property->id, 'name' => 'Zetoil', 'slug' => 'zetoil', 'is_active' => true]);

    $this->makeBooking = fn (array $attributes): Booking => Booking::create(array_merge([
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'guest_name' => 'Guest',
        'status' => 'confirmed',
        'check_in' => '2026-08-01',
        'check_out' => '2026-08-08',
    ], $attributes));
});

it('reports payment amounts and refines confirmed into paid or due', function () {
    $paid = ($this->makeBooking)(['price' => 1400, 'metadata' => ['invoice_payment_total' => 1400]]);
    $overpaid = ($this->makeBooking)(['price' => 1400, 'metadata' => ['invoice_payment_total' => 1470]]);
    $due = ($this->makeBooking)(['price' => 1400, 'metadata' => ['invoice_payment_total' => 500]]);
    $unknown = ($this->makeBooking)([]);

    expect($paid->displayStatus())->toBe('paid')
        ->and($overpaid->displayStatus())->toBe('paid')
        ->and($due->displayStatus())->toBe('due')
        ->and($due->paidAmount())->toBe(500.0)
        ->and($due->balanceAmount())->toBe(900.0)
        ->and($unknown->displayStatus())->toBe('due')
        ->and($unknown->totalAmount())->toBeNull();
});

it('passes non-confirmed statuses through', function () {
    $option = ($this->makeBooking)(['status' => 'option']);
    $quote = ($this->makeBooking)(['status' => 'quote']);
    $cancelled = ($this->makeBooking)(['status' => 'cancelled', 'price' => 100]);

    expect($option->displayStatus())->toBe('option')
        ->and($quote->displayStatus())->toBe('quote')
        ->and($cancelled->displayStatus())->toBe('cancelled');
});

it('aggregates amounts over active group members', function () {
    $unit2 = Unit::create(['property_id' => $this->property->id, 'name' => 'Moon', 'slug' => 'moon', 'is_active' => true]);

    $master = ($this->makeBooking)([
        'price' => 3000,
        'metadata' => ['invoice_payment_total' => 3000],
        'group_id' => 555,
    ]);
    ($this->makeBooking)([
        'unit_id' => $unit2->id,
        'price' => 1200,
        'group_id' => 555,
    ]);
    // Cancelled member: excluded from the sums.
    ($this->makeBooking)([
        'status' => 'cancelled',
        'price' => 5145,
        'metadata' => ['invoice_payment_total' => 999],
        'group_id' => 555,
    ]);

    expect($master->totalAmount())->toBe(4200.0)
        ->and($master->paidAmount())->toBe(3000.0)
        ->and($master->balanceAmount())->toBe(1200.0)
        ->and($master->displayStatus())->toBe('due');
});
