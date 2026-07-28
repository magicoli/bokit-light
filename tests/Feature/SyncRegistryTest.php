<?php

use App\Sync\Contracts\SourceConnector;
use App\Models\BookingSource;
use App\Models\Unit;
use App\Sync\SyncRegistry;

function makeRegistryConnector(string $type): SourceConnector
{
    return new class($type) implements SourceConnector
    {
        public function __construct(private string $type) {}

        public function sourceType(): string
        {
            return $this->type;
        }

        public function label(): string
        {
            return ucfirst($this->type);
        }

        public function displayLabel(array $sourceConfig): string
        {
            return $this->label();
        }

        public function sourceKey(Unit $unit, array $sourceConfig): string
        {
            return $this->type;
        }

        public function fetchBookings(Unit $unit, array $sourceConfig): array
        {
            return [];
        }

        public function externalBookingUrl(BookingSource $source): ?string
        {
            return null;
        }
    };
}

describe('SyncRegistry', function () {
    it('resolves as a singleton from the container', function () {
        $a = app(SyncRegistry::class);
        $b = app(SyncRegistry::class);

        expect($a)->toBeInstanceOf(SyncRegistry::class)
            ->and($a)->toBe($b);
    });

    it('starts empty', function () {
        expect((new SyncRegistry)->all())->toBeArray()->toBeEmpty();
    });

    it('registers a connector keyed by its source type', function () {
        $registry = new SyncRegistry;
        $connector = makeRegistryConnector('test');

        $registry->register($connector);

        expect($registry->all())->toHaveCount(1)
            ->and($registry->getForType('test'))->toBe($connector);
    });

    it('accumulates multiple connectors', function () {
        $registry = new SyncRegistry;
        $first = makeRegistryConnector('alpha');
        $second = makeRegistryConnector('beta');

        $registry->register($first);
        $registry->register($second);

        expect($registry->all())->toHaveCount(2)
            ->and($registry->getForType('alpha'))->toBe($first)
            ->and($registry->getForType('beta'))->toBe($second);
    });

    it('returns null for an unregistered source type', function () {
        expect((new SyncRegistry)->getForType('unknown'))->toBeNull();
    });
});
