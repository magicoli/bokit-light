<?php

namespace App\Services;

use App\Contracts\SyncHandler;
use App\Models\IcalSource;
use App\Models\Unit;

/**
 * Handles iCal feed sources (unit.options.sources type = 'ical').
 *
 * Registered in AppServiceProvider::boot(). bokit:sync calls syncSource()
 * for each iCal entry in a unit's options.sources, in definition order.
 */
class IcalSyncHandler implements SyncHandler
{
    public function __construct(private readonly BookingSyncIcal $parser) {}

    public function sourceType(): string
    {
        return 'ical';
    }

    public function label(): string
    {
        return 'iCal';
    }

    public function syncSource(Unit $unit, array $sourceConfig, bool $dryRun = false): array
    {
        $url = $sourceConfig['url'] ?? '';
        $configLabel = $sourceConfig['label'] ?? ($url ? (parse_url($url, PHP_URL_HOST) ?: $url) : 'iCal');
        $displayLabel = "iCal {$configLabel}";

        if (! $url) {
            return $this->failure($displayLabel, 'No URL configured');
        }

        if ($dryRun) {
            return $this->stats($displayLabel, 0, 0, 0, 0, 0);
        }

        try {
            // Build an in-memory IcalSource so BookingSyncIcal needs no changes.
            $icalSource = new IcalSource([
                'unit_id' => $unit->id,
                'name' => $configLabel,
                'url' => $url,
                'sync_enabled' => true,
            ]);
            $icalSource->setRelation('unit', $unit);

            $result = $this->parser->syncSource($icalSource);

            if (! ($result['success'] ?? false)) {
                return $this->failure($displayLabel, $result['error'] ?? 'Unknown error');
            }

            return $this->stats(
                $displayLabel,
                $result['total'] ?? 0,
                $result['new'] ?? 0,
                $result['updated'] ?? 0,
                $result['deleted'] ?? 0,
                $result['vanished'] ?? 0,
            );
        } catch (\Exception $e) {
            return $this->failure($displayLabel, $e->getMessage());
        }
    }

    /** @return array{label: string, success: true, total: int, new: int, updated: int, deleted: int, vanished: int, error: null} */
    private function stats(string $label, int $total, int $new, int $updated, int $deleted, int $vanished): array
    {
        return [
            'label' => $label,
            'success' => true,
            'total' => $total,
            'new' => $new,
            'updated' => $updated,
            'deleted' => $deleted,
            'vanished' => $vanished,
            'error' => null,
        ];
    }

    /** @return array{label: string, success: false, total: 0, new: 0, updated: 0, deleted: 0, vanished: 0, error: string} */
    private function failure(string $label, string $error): array
    {
        return [
            'label' => $label,
            'success' => false,
            'total' => 0,
            'new' => 0,
            'updated' => 0,
            'deleted' => 0,
            'vanished' => 0,
            'error' => $error,
        ];
    }
}
