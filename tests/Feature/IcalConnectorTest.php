<?php

use App\Contracts\SourceConnector;
use App\Models\Property;
use App\Models\Unit;
use App\Services\BookingSyncIcal;
use App\Services\IcalConnector;
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

    it('implements SourceConnector', function () {
        expect($this->connector)->toBeInstanceOf(SourceConnector::class);
    });

    it('has source type ical', function () {
        expect($this->connector->sourceType())->toBe('ical');
    });

    it('builds display label and source key from the config label', function () {
        $config = ['type' => 'ical', 'label' => 'beds24.com', 'url' => 'https://beds24.com/feed.ics'];

        expect($this->connector->displayLabel($config))->toBe('iCal beds24.com')
            ->and($this->connector->sourceKey($this->unit, $config))->toBe('ical:beds24.com');
    });

    it('falls back to the URL host when no label is configured', function () {
        $config = ['type' => 'ical', 'url' => 'https://feeds.example.com/cal.ics'];

        expect($this->connector->sourceKey($this->unit, $config))->toBe('ical:feeds.example.com');
    });

    it('throws when no URL is configured', function () {
        $this->connector->fetchBookings($this->unit, ['type' => 'ical', 'url' => '']);
    })->throws(RuntimeException::class, 'URL');

    it('throws when the feed cannot be fetched', function () {
        Http::fake(['*' => Http::response('', 500)]);

        $this->connector->fetchBookings($this->unit, ['type' => 'ical', 'url' => 'https://example.com/cal.ics']);
    })->throws(RuntimeException::class, '500');

    it('normalizes feed events', function () {
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
            ->and($booking->legacyUid)->toBe('uid-aaa@beds24.com');
    });

    it('skips events without UID or dates', function () {
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
