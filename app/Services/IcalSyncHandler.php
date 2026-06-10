<?php

namespace App\Services;

use App\Contracts\SyncHandler;
use App\Models\IcalSource;
use App\Models\Unit;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Syncs all enabled iCal feeds (stored in unit.options.sources) into Bokit bookings.
 *
 * Registered in AppServiceProvider::boot() so bokit:sync can iterate it
 * without knowing anything about iCal sources.
 *
 * iCal sources are managed via the unit edit page in the admin panel
 * (unit.options.sources with type = 'ical').
 */
class IcalSyncHandler implements SyncHandler
{
    public function __construct(private readonly BookingSyncIcal $parser) {}

    public function label(): string
    {
        return 'iCal feeds';
    }

    public function handle(OutputInterface $output, bool $dryRun = false): void
    {
        /** @var array<array{unit: Unit, config: array{type: string, url: string, label: string, enabled: bool}}> $activeSources */
        $activeSources = [];

        Unit::with('property')
            ->get()
            ->each(function (Unit $unit) use (&$activeSources): void {
                $sources = collect($unit->options['sources'] ?? [])
                    ->filter(fn ($s) => ($s['type'] ?? '') === 'ical' && ($s['enabled'] ?? true));

                foreach ($sources as $config) {
                    $activeSources[] = ['unit' => $unit, 'config' => $config];
                }
            });

        if (empty($activeSources)) {
            $output->writeln('<comment>No active iCal sources found.</comment>');

            return;
        }

        $count = count($activeSources);
        $output->writeln("Found {$count} iCal source(s)");
        $output->writeln('');

        $totalStats = ['total' => 0, 'new' => 0, 'updated' => 0, 'deleted' => 0, 'vanished' => 0];
        $errors = 0;

        foreach ($activeSources as ['unit' => $unit, 'config' => $config]) {
            $label = $config['label'] ?? $config['url'];
            $sourceName = "{$unit->property->name} / {$unit->name} / {$label}";
            $output->writeln("  Syncing: <fg=cyan>{$sourceName}</>");

            try {
                // Build an in-memory IcalSource so BookingSyncIcal needs no changes.
                $icalSource = new IcalSource([
                    'unit_id' => $unit->id,
                    'name' => $label,
                    'url' => $config['url'],
                    'sync_enabled' => $config['enabled'] ?? true,
                ]);
                $icalSource->setRelation('unit', $unit);

                $stats = $this->parser->syncSource($icalSource);

                if (! ($stats['success'] ?? false)) {
                    throw new \RuntimeException($stats['error'] ?? 'Unknown error');
                }

                $parts = [
                    "Total: {$stats['total']}",
                    ($stats['new'] > 0 ? '<fg=green>' : '')."New: {$stats['new']}".($stats['new'] > 0 ? '</>' : ''),
                    ($stats['updated'] > 0 ? '<fg=yellow>' : '')."Updated: {$stats['updated']}".($stats['updated'] > 0 ? '</>' : ''),
                    ($stats['deleted'] > 0 ? '<fg=red>' : '')."Deleted: {$stats['deleted']}".($stats['deleted'] > 0 ? '</>' : ''),
                    ($stats['vanished'] > 0 ? '<fg=magenta>' : '')."Vanished: {$stats['vanished']}".($stats['vanished'] > 0 ? '</>' : ''),
                ];
                $output->writeln('  ✓ '.implode(', ', $parts));

                foreach (['total', 'new', 'updated', 'deleted', 'vanished'] as $key) {
                    $totalStats[$key] += $stats[$key] ?? 0;
                }
            } catch (\Exception $e) {
                $output->writeln("<error>  ✗ Failed: {$e->getMessage()}</error>");
                $errors++;
            }

            $output->writeln('');
        }

        $output->writeln('<info>iCal summary:</info>');
        $output->writeln("  Total bookings: <fg=cyan>{$totalStats['total']}</>");
        $output->writeln("  New: <fg=green>{$totalStats['new']}</>");
        $output->writeln("  Updated: <fg=yellow>{$totalStats['updated']}</>");
        $output->writeln("  Deleted: <fg=red>{$totalStats['deleted']}</>");
        $output->writeln("  Vanished: <fg=magenta>{$totalStats['vanished']}</>");

        if ($errors > 0) {
            $output->writeln("  Errors: <fg=red>{$errors}</>");
        }
    }
}
