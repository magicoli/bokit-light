<?php

namespace App\Backup\Console\Commands;

use App\Backup\Support\BackupConfig;
use App\Backup\Support\ForgetsPreviousOutput;
use Spatie\Backup\Commands\ListCommand as SpatieListCommand;
use Spatie\Backup\Config\Config;

/**
 * Lists both destinations.
 *
 * The package lists the destinations of one configuration, which here means the full backups only:
 * the essential archives would exist and not show up. A listing that answers "no backups present"
 * while archives sit on the disk is worse than no listing at all.
 */
class ListBackupsCommand extends SpatieListCommand
{
    use ForgetsPreviousOutput;

    public function __construct(Config $config)
    {
        parent::__construct($config);

        $this->setAliases(['bokit:backup-list']);
        $this->setDescription('Display the full and the essential backups');
    }

    public function handle(): int
    {
        // One table with both, rather than the same command run twice: the two destinations belong
        // to one application and reading them side by side is the point.
        $merged = config(BackupConfig::FULL);
        $merged['monitor_backups'] = [
            ...$merged['monitor_backups'],
            ...config(BackupConfig::essentials() . '.monitor_backups'),
        ];

        $this->config = Config::fromArray($merged);

        return parent::handle();
    }
}
