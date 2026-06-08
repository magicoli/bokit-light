<?php

namespace Tests\Support;

use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\SkippedSubscriber;
use PHPUnit\Event\TestRunner\Finished;
use PHPUnit\Event\TestRunner\FinishedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use stdClass;

/**
 * Prints a concise failure/skipped summary after the test run,
 * regardless of how tests were launched (artisan, pest, bash).
 */
final class FailureSummaryExtension implements Extension
{
    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters,
    ): void {
        $entries = new stdClass;
        $entries->failures = [];
        $entries->skipped = [];

        $facade->registerSubscriber(new class($entries) implements FailedSubscriber {
            public function __construct(
                private stdClass $entries,
            ) {}

            public function notify(Failed $event): void
            {
                $this->entries->failures[] = FailureSummaryExtension::prettifyName(
                    $event->test()->name(),
                );
            }
        });

        $facade->registerSubscriber(new class($entries) implements ErroredSubscriber {
            public function __construct(
                private stdClass $entries,
            ) {}

            public function notify(Errored $event): void
            {
                $this->entries->failures[] = FailureSummaryExtension::prettifyName(
                    $event->test()->name(),
                );
            }
        });

        $facade->registerSubscriber(new class($entries) implements SkippedSubscriber {
            public function __construct(
                private stdClass $entries,
            ) {}

            public function notify(Skipped $event): void
            {
                $this->entries->skipped[] = [
                    'name' => FailureSummaryExtension::prettifyName($event->test()->name()),
                    'message' => $event->message(),
                ];
            }
        });

        $facade->registerSubscriber(new class($entries) implements FinishedSubscriber {
            public function __construct(
                private stdClass $entries,
            ) {}

            public function notify(Finished $event): void
            {
                if (empty($this->entries->failures) && empty($this->entries->skipped)) {
                    return;
                }

                $out = 'Failed/Skipped Summary'.PHP_EOL;

                if (! empty($this->entries->failures)) {
                    $out .= PHP_EOL.'  Failed:'.PHP_EOL;
                    foreach ($this->entries->failures as $name) {
                        $out .= "  \u{2717} {$name}".PHP_EOL;
                    }
                }

                if (! empty($this->entries->skipped)) {
                    $out .= PHP_EOL.'  Skipped:'.PHP_EOL;
                    foreach ($this->entries->skipped as ['name' => $name, 'message' => $msg]) {
                        $cleaned = $msg === '__TODO__' ? 'todo' : $msg;
                        $suffix = $cleaned ? " \u{2192} ".mb_strimwidth($cleaned, 0, 60, "\u{2026}") : '';
                        $out .= "  \u{2013} {$name}{$suffix}".PHP_EOL;
                    }
                }

                fwrite(STDERR, $out);
            }
        });
    }

    public static function prettifyName(string $name): string
    {
        if (! str_starts_with($name, '__pest_evaluable__')) {
            return $name;
        }

        $name = substr($name, strlen('__pest_evaluable__'));

        // Pest encodes Unicode chars as literal \u{XXXX} in PHP method names.
        // Decode them back to actual characters before further processing.
        $name = preg_replace_callback(
            '/\\\\u\{([0-9a-fA-F]+)\}/',
            fn ($m) => mb_chr(hexdec($m[1])),
            $name,
        );

        $name = str_replace("__\u{2192}_", " \u{2192} ", $name);
        $name = str_replace('_', ' ', $name);

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }
}
