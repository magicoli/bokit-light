<?php

use App\Contracts\SyncHandler;
use App\Models\Property;
use App\Models\Unit;
use App\Services\SyncRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('bokit:sync', function () {
    beforeEach(function () {
        // Isolate each test with a fresh empty registry.
        app()->instance(SyncRegistry::class, new SyncRegistry);
    });

    it('warns when no handlers are registered', function () {
        $this->artisan('bokit:sync')
            ->expectsOutput('No sync handlers registered.')
            ->assertExitCode(0);
    });

    it('calls syncSource for each matching unit source', function () {
        $called = [];

        $handler = new class($called) implements SyncHandler
        {
            public function __construct(private array &$called) {}

            public function sourceType(): string
            {
                return 'test-source';
            }

            public function label(): string
            {
                return 'Test handler';
            }

            public function syncSource(Unit $unit, array $sourceConfig, bool $dryRun = false): array
            {
                $this->called[] = $unit->name;

                return ['label' => 'Test', 'success' => true, 'total' => 0, 'new' => 0, 'updated' => 0, 'deleted' => 0, 'vanished' => 0, 'error' => null];
            }
        };

        app(SyncRegistry::class)->register($handler);

        $property = Property::create(['name' => 'BokitP1', 'slug' => 'bokit-p1', 'is_active' => true]);
        Unit::create([
            'property_id' => $property->id,
            'name' => 'TestUnit',
            'slug' => 'bokit-test-unit',
            'is_active' => true,
            'options' => ['sources' => [['type' => 'test-source', 'enabled' => true]]],
        ]);

        $this->artisan('bokit:sync')->assertExitCode(0);

        expect($called)->toBe(['TestUnit']);
    });

    it('passes dry-run flag to syncSource', function () {
        $receivedDryRun = null;

        $handler = new class($receivedDryRun) implements SyncHandler
        {
            public function __construct(private mixed &$received) {}

            public function sourceType(): string
            {
                return 'dry-test';
            }

            public function label(): string
            {
                return 'DryRun test';
            }

            public function syncSource(Unit $unit, array $sourceConfig, bool $dryRun = false): array
            {
                $this->received = $dryRun;

                return ['label' => 'DryRun test', 'success' => true, 'total' => 0, 'new' => 0, 'updated' => 0, 'deleted' => 0, 'vanished' => 0, 'error' => null];
            }
        };

        app(SyncRegistry::class)->register($handler);

        $property = Property::create(['name' => 'BokitP2', 'slug' => 'bokit-p2', 'is_active' => true]);
        Unit::create([
            'property_id' => $property->id,
            'name' => 'U2',
            'slug' => 'bokit-u2',
            'is_active' => true,
            'options' => ['sources' => [['type' => 'dry-test', 'enabled' => true]]],
        ]);

        $this->artisan('bokit:sync', ['--dry-run' => true])->assertExitCode(0);

        expect($receivedDryRun)->toBeTrue();
    });

    it('skips unit sources whose type has no registered handler', function () {
        $property = Property::create(['name' => 'BokitP3', 'slug' => 'bokit-p3', 'is_active' => true]);
        Unit::create([
            'property_id' => $property->id,
            'name' => 'U3',
            'slug' => 'bokit-u3',
            'is_active' => true,
            'options' => ['sources' => [['type' => 'unregistered', 'enabled' => true]]],
        ]);

        // No handler registered — should complete without error
        $this->artisan('bokit:sync')
            ->assertExitCode(0);
    });
});
