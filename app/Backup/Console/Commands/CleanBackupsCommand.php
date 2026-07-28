<?php

namespace App\Backup\Console\Commands;

use App\Backup\Support\BackupConfig;
use App\Backup\Support\ForgetsPreviousOutput;
use Spatie\Backup\Commands\CleanupCommand as SpatieCleanupCommand;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Tasks\Cleanup\CleanupStrategy;

/**
 * Applies the retention to both destinations.
 *
 * The package's `backup:clean` works through one configuration and would only ever see one of the
 * two, leaving the other to pile up forever — so it is replaced here rather than shadowed, under
 * its own name and under `bokit:backup-clean`.
 */
class CleanBackupsCommand extends SpatieCleanupCommand
{
    use ForgetsPreviousOutput;

    public function __construct(CleanupStrategy $strategy, Config $config)
    {
        parent::__construct($strategy, $config);

        $this->setAliases(['bokit:backup-clean']);
        $this->setDescription('Apply the retention policy to the full and the essential backups');
    }

    public function handle(): int
    {
        // An explicit --config means someone is cleaning one destination on purpose.
        if ($this->option('config')) {
            return parent::handle();
        }

        $this->input->setOption('config', BackupConfig::FULL);
        $status = parent::handle();

        $this->input->setOption('config', BackupConfig::essentials());

        return max($status, parent::handle());
    }
}
