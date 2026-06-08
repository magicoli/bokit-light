<?php

use App\Contracts\SyncHandler;
use App\Services\SyncRegistry;
use Symfony\Component\Console\Output\NullOutput;

describe('SyncRegistry', function () {
    it('resolves as a singleton from the container', function () {
        $a = app(SyncRegistry::class);
        $b = app(SyncRegistry::class);

        expect($a)->toBeInstanceOf(SyncRegistry::class)
            ->and($a)->toBe($b);
    });

    it('starts empty', function () {
        // Fresh instance (not the singleton) to isolate from other tests.
        $registry = new SyncRegistry;

        expect($registry->all())->toBeArray()->toBeEmpty();
    });

    it('returns registered handlers', function () {
        $registry = new SyncRegistry;

        $handler = new class implements SyncHandler {
            public function label(): string { return 'Test handler'; }
            public function handle(\Symfony\Component\Console\Output\OutputInterface $output, bool $dryRun = false): void {}
        };

        $registry->register($handler);

        expect($registry->all())->toHaveCount(1)
            ->and($registry->all()[0])->toBe($handler);
    });

    it('accumulates multiple handlers in registration order', function () {
        $registry = new SyncRegistry;

        $first = new class implements SyncHandler {
            public function label(): string { return 'First'; }
            public function handle(\Symfony\Component\Console\Output\OutputInterface $output, bool $dryRun = false): void {}
        };
        $second = new class implements SyncHandler {
            public function label(): string { return 'Second'; }
            public function handle(\Symfony\Component\Console\Output\OutputInterface $output, bool $dryRun = false): void {}
        };

        $registry->register($first);
        $registry->register($second);

        $all = $registry->all();
        expect($all)->toHaveCount(2)
            ->and($all[0]->label())->toBe('First')
            ->and($all[1]->label())->toBe('Second');
    });

    it('calls handle on each registered handler when iterated', function () {
        $registry = new SyncRegistry;
        $called = [];

        foreach (['Alpha', 'Beta'] as $name) {
            $registry->register(new class($name, $called) implements SyncHandler {
                public function __construct(
                    private string $name,
                    private array &$called,
                ) {}
                public function label(): string { return $this->name; }
                public function handle(\Symfony\Component\Console\Output\OutputInterface $output, bool $dryRun = false): void
                {
                    $this->called[] = $this->name;
                }
            });
        }

        $output = new NullOutput;
        foreach ($registry->all() as $handler) {
            $handler->handle($output);
        }

        expect($called)->toBe(['Alpha', 'Beta']);
    });
});
