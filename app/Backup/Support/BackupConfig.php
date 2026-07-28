<?php

namespace App\Backup\Support;

/**
 * The essential backups are a configuration of their own rather than a flag on the main one.
 *
 * They hold a narrower file list AND they deserve a different fate: Spatie applies one retention
 * per configuration, so two rules mean two configurations and two destinations. The small archives
 * are worth a day — past that a full backup covers the same ground — while the complete ones are
 * kept for years.
 */
final class BackupConfig
{
    /**
     * The configuration keys handed to Spatie's `--config` option. FULL is the published one under
     * its own name — passing it explicitly is what clears the previous call's configuration from
     * the shared command instance.
     */
    public const FULL = 'backup';

    public const ESSENTIALS = 'backup_essentials';

    /**
     * Build the essentials configuration once per request, and return its key.
     */
    public static function essentials(): string
    {
        if (config(self::ESSENTIALS) !== null) {
            return self::ESSENTIALS;
        }

        $config = config('backup');

        $config['backup']['name'] = config('backup.essentials.name');
        $config['backup']['source']['files']['include'] = config('backup.essentials.include');

        // Everything from the last day, then nothing: no ladder to climb down.
        $config['cleanup']['default_strategy'] = [
            'keep_all_backups_for_days' => (int) config('backup.essentials.keep_for_days'),
            'keep_daily_backups_for_days' => 0,
            'keep_weekly_backups_for_weeks' => 0,
            'keep_monthly_backups_for_months' => 0,
            'keep_yearly_backups_for_years' => 0,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ];

        // Cleanup finds its destinations through this list, which is also what the health checks
        // read — and an essential backup missing is not news, since a full one is due daily.
        $config['monitor_backups'] = [
            [
                'name' => config('backup.essentials.name'),
                'disks' => config('backup.monitor_backups.0.disks'),
                'health_checks' => [],
            ],
        ];

        config([self::ESSENTIALS => $config]);

        return self::ESSENTIALS;
    }
}
