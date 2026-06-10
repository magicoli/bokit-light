<?php

use App\Contracts\SyncHandler;
use App\Models\Unit;
use App\Services\SyncRegistry;

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

    it('registers a handler keyed by its source type', function () {
        $registry = new SyncRegistry;

        $handler = new class implements SyncHandler
        {
            public function sourceType(): string
            {
                return 'test';
            }

            public function label(): string
            {
                return 'Test handler';
            }

            public function syncSource(Unit $unit, array $sourceConfig, bool $dryRun = false): array
            {
                return [];
            }
        };

        $registry->register($handler);

        expect($registry->all())->toHaveCount(1)
            ->and($registry->getForType('test'))->toBe($handler);
    });

    it('accumulates multiple handlers', function () {
        $registry = new SyncRegistry;

        $first = new class implements SyncHandler
        {
            public function sourceType(): string
            {
                return 'alpha';
            }

            public function label(): string
            {
                return 'Alpha';
            }

            public function syncSource(Unit $unit, array $sourceConfig, bool $dryRun = false): array
            {
                return [];
            }
        };
        $second = new class implements SyncHandler
        {
            public function sourceType(): string
            {
                return 'beta';
            }

            public function label(): string
            {
                return 'Beta';
            }

            public function syncSource(Unit $unit, array $sourceConfig, bool $dryRun = false): array
            {
                return [];
            }
        };

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
