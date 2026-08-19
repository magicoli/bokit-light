<?php

use App\Sync\Contracts\SourceConnector;
use App\Models\Property;
use App\Models\Unit;
use App\Sync\Ical\BookingSyncIcal;
use App\Sync\Ical\IcalConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

describe('IcalConnector', function () {
    beforeEach(function () {
        $this->property = Property::create(['name' => 'IcalP', 'slug' => 'ical-p', 'is_active' => true]);
        $this->unit = Unit::create(['property_id' => $this->property->id, 'name' => 'IcalU', 'slug' => 'ical-u', 'is_active' => true]);
        $this->unit->setRelation('property', $this->property);
        $this->connector = new IcalConnector(new BookingSyncIcal);
    });

    test('implements SourceConnector', function () {
        expect($this->connector)->toBeInstanceOf(SourceConnector::class);
    });

    test('has source type ical', function () {
        expect($this->connector->sourceType())->toBe('ical');
    });

    test('builds display label and source key from the config label', function () {
        $config = ['type' => 'ical', 'label' => 'beds24.com', 'url' => 'https://beds24.com/feed.ics'];

        expect($this->connector->displayLabel($config))->toBe('iCal beds24.com')
            ->and($this->connector->sourceKey($this->unit, $config))->toBe('ical:beds24.com');
    });

    test('falls back to the URL host when no label is configured', function () {
        $config = ['type' => 'ical', 'url' => 'https://feeds.example.com/cal.ics'];

        expect($this->connector->sourceKey($this->unit, $config))->toBe('ical:feeds.example.com');
    });

    test('throws when no URL is configured', function () {
        $this->connector->fetchBookings($this->unit, ['type' => 'ical', 'url' => '']);
    })->throws(RuntimeException::class, 'URL');

    test('throws when the feed cannot be fetched', function () {
        Http::fake(['*' => Http::response('', 500)]);

        $this->connector->fetchBookings($this->unit, ['type' => 'ical', 'url' => 'https://example.com/cal.ics']);
    })->throws(RuntimeException::class, '500');

    test('normalizes feed events', function () {
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'BEGIN:VEVENT',
            'UID:uid-aaa@beds24.com',
            'DTSTART;VALUE=DATE:20270108',
            'DTEND;VALUE=DATE:20270111',
            'SUMMARY:Gudule Lapointe',
            'DESCRIPTION:STATUS:confirmed/123\\nEMAIL:gudule@example.com\\nGUESTS:3/2/1',
            'END:VEVENT',
            'END:VCALENDAR',
        ]);
        Http::fake(['*' => Http::response($ics, 200)]);

        $bookings = $this->connector->fetchBookings(
            $this->unit,
            ['type' => 'ical', 'label' => 'beds24.com', 'url' => 'https://beds24.com/feed.ics'],
        );

        expect($bookings)->toHaveCount(1);

        $booking = $bookings[0];
        expect($booking->externalId)->toBe('uid-aaa@beds24.com')
            ->and($booking->checkIn)->toBe('2027-01-08')
            ->and($booking->checkOut)->toBe('2027-01-11')
            ->and($booking->guestName)->toBe('Gudule Lapointe')
            ->and($booking->status)->toBe('confirmed')
            ->and($booking->email)->toBe('gudule@example.com')
            ->and($booking->adults)->toBe(2)
            ->and($booking->children)->toBe(1)
            ->and($booking->channel)->toBe('beds24.com')
            ->and($booking->legacyUid)->toBe('uid-aaa@beds24.com')
            ->and($booking->claimsOrigin)->toBeFalse();
    });

    test('skips past or ongoing unavailable blocks but keeps future ones', function () {
        $past = now()->subDays(10)->format('Ymd');
        $pastEnd = now()->addDays(5)->format('Ymd');
        $future = now()->addDays(30)->format('Ymd');
        $futureEnd = now()->addDays(40)->format('Ymd');

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'BEGIN:VEVENT',
            'UID:uid-past-block@airbnb.com',
            "DTSTART;VALUE=DATE:{$past}",
            "DTEND;VALUE=DATE:{$pastEnd}",
            'SUMMARY:Unavailable',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'UID:uid-future-block@airbnb.com',
            "DTSTART;VALUE=DATE:{$future}",
            "DTEND;VALUE=DATE:{$futureEnd}",
            'SUMMARY:Unavailable',
            'END:VEVENT',
            'END:VCALENDAR',
        ]);
        Http::fake(['*' => Http::response($ics, 200)]);

        $bookings = $this->connector->fetchBookings(
            $this->unit,
            ['type' => 'ical', 'label' => 'airbnb', 'url' => 'https://airbnb.com/cal.ics'],
        );

        expect($bookings)->toHaveCount(1)
            ->and($bookings[0]->externalId)->toBe('uid-future-block@airbnb.com')
            ->and($bookings[0]->status)->toBe('blocked');
    });

    test('declares a Beds24 origin hint when the UID embeds a beds24 booking id', function () {
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'BEGIN:VEVENT',
            'UID:20261103140000-b84186183@beds24.com',
            'DTSTART;VALUE=DATE:20271103',
            'DTEND;VALUE=DATE:20271205',
            'SUMMARY:Booking 84186183',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'UID:regular-uid@airbnb.com',
            'DTSTART;VALUE=DATE:20271201',
            'DTEND;VALUE=DATE:20271210',
            'SUMMARY:Normal Guest',
            'END:VEVENT',
            'END:VCALENDAR',
        ]);
        Http::fake(['*' => Http::response($ics, 200)]);

        $bookings = $this->connector->fetchBookings(
            $this->unit,
            ['type' => 'ical', 'label' => 'api.beds24.com', 'url' => 'https://api.beds24.com/feed.ics'],
        );

        expect($bookings)->toHaveCount(2)
            ->and($bookings[0]->originHint)->toBe(['type' => 'beds24', 'external_id' => '84186183'])
            ->and($bookings[1]->originHint)->toBeNull();
    });

    test('skips events without UID or dates', function () {
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'BEGIN:VEVENT',
            'SUMMARY:No UID here',
            'DTSTART;VALUE=DATE:20270108',
            'DTEND;VALUE=DATE:20270111',
            'END:VEVENT',
            'END:VCALENDAR',
        ]);
        Http::fake(['*' => Http::response($ics, 200)]);

        $bookings = $this->connector->fetchBookings(
            $this->unit,
            ['type' => 'ical', 'label' => 'x', 'url' => 'https://example.com/cal.ics'],
        );

        expect($bookings)->toBeEmpty();
    });
});
