<?php

use App\Contracts\SyncHandler;
use App\Services\SyncRegistry;
use Symfony\Component\Console\Output\OutputInterface;

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

    it('calls handle on every registered handler', function () {
        $called = [];

        $handler = new class($called) implements SyncHandler
        {
            public function __construct(private array &$called) {}

            public function label(): string
            {
                return 'Test handler';
            }

            public function handle(OutputInterface $output, bool $dryRun = false): void
            {
                $this->called[] = $this->label();
            }
        };

        app(SyncRegistry::class)->register($handler);

        $this->artisan('bokit:sync')->assertExitCode(0);

        expect($called)->toBe(['Test handler']);
    });

    it('passes dry-run flag to handlers', function () {
        $receivedDryRun = null;

        $handler = new class($receivedDryRun) implements SyncHandler
        {
            public function __construct(private mixed &$received) {}

            public function label(): string
            {
                return 'DryRun test';
            }

            public function handle(OutputInterface $output, bool $dryRun = false): void
            {
                $this->received = $dryRun;
            }
        };

        app(SyncRegistry::class)->register($handler);

        $this->artisan('bokit:sync', ['--dry-run' => true])->assertExitCode(0);

        expect($receivedDryRun)->toBeTrue();
    });

    it('shows the handler label in output', function () {
        $handler = new class implements SyncHandler
        {
            public function label(): string
            {
                return 'My Module';
            }

            public function handle(OutputInterface $output, bool $dryRun = false): void {}
        };

        app(SyncRegistry::class)->register($handler);

        $this->artisan('bokit:sync')->expectsOutputToContain('My Module')->assertExitCode(0);
    });
});
