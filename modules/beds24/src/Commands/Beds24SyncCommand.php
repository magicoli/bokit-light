<?php

namespace Modules\Beds24\Commands;

use Illuminate\Console\Command;
use Modules\Beds24\Services\Beds24SyncHandler;

/**
 * Targeted Beds24 sync — thin wrapper around Beds24SyncHandler.
 *
 * Use this command when you need to sync a specific property or date range.
 * For the scheduled full sync, bokit:sync delegates to Beds24SyncHandler
 * via the SyncRegistry.
 */
class Beds24SyncCommand extends Command
{
    protected $signature = 'beds24:sync
                            {--property=      : Property slug or ID (all configured properties if omitted)}
                            {--from=          : Arrival from date (YYYY-MM-DD). Defaults to 2020-01-01.}
                            {--to=            : Arrival to date (YYYY-MM-DD). Defaults to 5 years from now.}
                            {--modified-since= : Only fetch bookings modified since this date (YYYY-MM-DD)}
                            {--dry-run        : Preview without saving}';

    protected $description = 'Sync Beds24 bookings into Bokit (targeted run)';

    public function handle(): int
    {
        $handler = new Beds24SyncHandler(
            propertyFilter: $this->option('property'),
            from: $this->option('from') ?? '2020-01-01',
            to: $this->option('to'),
            modifiedSince: $this->option('modified-since'),
        );

        $handler->handle($this->output, (bool) $this->option('dry-run'));

        return self::SUCCESS;
    }
}
