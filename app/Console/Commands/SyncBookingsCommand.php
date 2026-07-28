<?php

namespace App\Console\Commands;

use App\Sync\SyncRegistry;
use App\Sync\SyncRunner;
use Illuminate\Console\Command;

class SyncBookingsCommand extends Command
{
    protected $signature = 'bokit:sync
                            {--dry-run : Preview sync actions without writing to the database}';

    protected $description = 'Sync all unit sources (Beds24, iCal, HBook, Multipass, …), grouped by property and unit';

    /**
     * Prints what {@see SyncRunner} does; the walk itself lives there, shared with the automatic
     * sync and with the calendar's targeted resync.
     */
    public function handle(SyncRegistry $registry, SyncRunner $runner): int
    {
        if (empty($registry->all())) {
            $this->warn('No sync handlers registered.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->line('<fg=yellow>DRY RUN — no changes will be saved.</>');
            $this->newLine();
        }

        $this->info('🏖️  Starting Bokit calendar synchronization...');
        $this->newLine();

        $result = $runner->run(dryRun: $dryRun, report: function (string $event, array $payload): void {
            match ($event) {
                'property' => $this->line("Property: <info>{$payload['property']->name}</info>"),
                'unit' => $this->line("  Unit: <fg=cyan>{$payload['unit']->name}</>"),
                'source' => $this->reportSource($payload['result']),
                'push' => $this->reportPush($payload['result']),
                default => null,
            };
        });

        $this->newLine();

        if (!empty($result['failures'])) {
            $this->line('Failures:');
            foreach ($result['failures'] as $failure) {
                $this->line("  <error>✗ {$failure}</error>");
            }
            $this->newLine();
        }

        $this->info('✅ Synchronization complete!');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function reportSource(array $result): void
    {
        if ($result['success']) {
            $this->line('    ✓ ' . $this->formatStats($result));

            return;
        }

        $this->line("    <error>✗ {$result['label']}: {$result['error']}</error>");
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function reportPush(array $result): void
    {
        if (!$result['created'] && !$result['updated'] && !$result['failed']) {
            return;
        }

        $line = "    ↑ {$result['label']}: Created: {$result['created']}, Updated: {$result['updated']}, Failed: {$result['failed']}";

        $this->line($result['success'] ? $line : "<error>{$line} — {$result['error']}</error>");
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private function formatStats(array $r): string
    {
        $parts = [
            "Total: {$r['total']}",
            ($r['new'] > 0 ? '<fg=green>' : '') . "New: {$r['new']}" . ($r['new'] > 0 ? '</>' : ''),
            ($r['updated'] > 0 ? '<fg=yellow>' : '') . "Updated: {$r['updated']}" . ($r['updated'] > 0 ? '</>' : ''),
            ($r['deleted'] > 0 ? '<fg=red>' : '') . "Deleted: {$r['deleted']}" . ($r['deleted'] > 0 ? '</>' : ''),
            ($r['vanished'] > 0 ? '<fg=magenta>' : '')
                . "Vanished: {$r['vanished']}"
                . ($r['vanished'] > 0 ? '</>' : ''),
        ];

        return "{$r['label']}: " . implode(', ', $parts);
    }
}
