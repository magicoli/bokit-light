<?php

namespace App\Backup\Console\Commands;

use App\Backup\Support\BackupConfig;
use App\Backup\Support\ForgetsPreviousOutput;
use Spatie\Backup\Commands\BackupCommand as SpatieBackupCommand;
use Spatie\Backup\Config\Config;
use Symfony\Component\Console\Input\InputOption;

/**
 * The one backup procedure: what a deploy runs, what a page load runs, and what an administrator
 * runs by hand are the same archive, in the same place, under the same retention.
 *
 * It replaces the package's `backup:run` instead of sitting next to it, and answers to
 * `bokit:backup` as well. Two names for one behaviour is predictable; two commands doing different
 * things under names that read alike is not.
 *
 * Two sizes, because they are not needed at the same rhythm. The database, the per-record settings
 * and the key weigh almost nothing and belong in every archive; the uploaded files can grow, and
 * change far less often. Complete stays the default: a backup that leaves things out is something
 * one asks for.
 */
class BackupCommand extends SpatieBackupCommand
{
    use ForgetsPreviousOutput;

    public function __construct(Config $config)
    {
        parent::__construct($config);

        $this->setAliases(['bokit:backup']);
        $this->setDescription(
            'Back up the database, the settings and the key, plus the uploaded files unless --essentials',
        );

        // Added to the inherited definition rather than by redeclaring the signature, so the
        // package's own ten options stay whatever the package says they are, upgrade after upgrade.
        $this->getDefinition()->addOption(
            new InputOption(
                'essentials',
                null,
                InputOption::VALUE_NONE,
                'Leave out the uploaded files: database, settings and key only',
            ),
        );
    }

    public function handle(): int
    {
        // Choosing between the two configurations is the whole point of this class. An explicit
        // --config is left alone; anything else is decided here rather than inherited from the
        // previous run of this same, shared, command instance.
        if (!$this->option('config')) {
            $this->input->setOption(
                'config',
                $this->option('essentials') ? BackupConfig::essentials() : BackupConfig::FULL,
            );
        }

        return parent::handle();
    }
}
