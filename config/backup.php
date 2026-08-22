<?php

use Illuminate\Support\Str;
use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

$failureChannels = filled(env('BACKUP_NOTIFICATION_EMAIL')) ? ['mail'] : [];

/*
 * The vital, weightless part, alongside the database dump in EVERY archive: the per-record options
 * — the properties' Beds24 keys, WordPress passwords and the install state are JSON files, not
 * database rows (see config/options.php) — and the application key, without which the encrypted
 * values in the dump cannot be read.
 */
$essentials = [env('OPTIONS_PATH', storage_path('options')), base_path('.env')];

/*
 * Two sibling destinations under one roof, never nested: a destination lists its archives
 * recursively, so an 'essentials' folder inside the full one would have its archives counted, and
 * thinned out, as if they were complete.
 */
$name = Str::slug(config('app.name', 'Bokit'));

return [
    'backup' => [
        /*
         * The name of this application. You can use this name to monitor
         * the backups.
         */
        'name' => $name.'/full',

        'source' => [
            'files' => [
                /*
                 * The list of directories and files that will be included in the backup.
                 */
                // NOT the whole application: the code is in git and a deploy rebuilds it. This is
                // the full list; `backup:run --essentials` narrows it to $essentials, leaving out
                // the only entry that can grow heavy.
                'include' => [
                    ...$essentials,
                    storage_path('app'),
                ],

                /*
                 * These directories and files will be excluded from the backup.
                 *
                 * Directories used by the backup process will automatically be excluded.
                 */
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    // Regenerated on demand, and the logs alone once reached 800 MB.
                    storage_path('framework'),
                    storage_path('logs'),
                ],

                /*
                 * Determines if symlinks should be followed.
                 */
                'follow_links' => false,

                /*
                 * Determines if it should avoid unreadable folders.
                 */
                'ignore_unreadable_directories' => false,

                /*
                 * This path is used to make directories in resulting zip-file relative
                 * Set to `null` to include complete absolute path
                 * Example: base_path()
                 */
                'relative_path' => null,
            ],

            /*
             * The names of the connections to the databases that should be backed up
             * MySQL, PostgreSQL, SQLite and Mongo databases are supported.
             *
             * The content of the database dump may be customized for each connection
             * by adding a 'dump' key to the connection settings in config/database.php.
             * E.g.
             * 'mysql' => [
             *       ...
             *      'dump' => [
             *           'exclude_tables' => [
             *                'table_to_exclude_from_backup',
             *                'another_table_to_exclude'
             *            ]
             *       ],
             * ],
             *
             * If you are using only InnoDB tables on a MySQL server, you can
             * also supply the useSingleTransaction option to avoid table locking.
             *
             * E.g.
             * 'mysql' => [
             *       ...
             *      'dump' => [
             *           'useSingleTransaction' => true,
             *       ],
             * ],
             *
             * For a complete list of available customization options, see https://github.com/spatie/db-dumper
             */
            // Same variable AND same fallback as config/database.php: the published default falls
            // back to mysql, which would silently back up the wrong database — or nothing at all —
            // on an installation that never set DB_CONNECTION. (config('database.default') cannot
            // be used here: a config file is evaluated before the later ones are loaded.)
            'databases' => [
                // WARNING: make sure to keep the same default as in database.php
                env('DB_CONNECTION', 'sqlite'),
            ],
        ],

        /*
         * The database dump can be compressed to decrease disk space usage.
         *
         * Out of the box Laravel-backup supplies
         * Spatie\DbDumper\Compressors\GzipCompressor::class.
         *
         * You can also create custom compressor. More info on that here:
         * https://github.com/spatie/db-dumper#using-compression
         *
         * If you do not want any compressor at all, set it to null.
         */
        'database_dump_compressor' => null,

        /*
         * If specified, the database dumped file name will contain a timestamp (e.g.: 'Y-m-d-H-i-s').
         */
        'database_dump_file_timestamp_format' => null,

        /*
         * The base of the dump filename, either 'database' or 'connection'
         *
         * If 'database' (default), the dumped filename will contain the database name.
         * If 'connection', the dumped filename will contain the connection name.
         */
        'database_dump_filename_base' => 'database',

        /*
         * The file extension used for the database dump files.
         *
         * If not specified, the file extension will be .archive for MongoDB and .sql for all other databases
         * The file extension should be specified without a leading .
         */
        'database_dump_file_extension' => '',

        'destination' => [
            /*
             * The compression algorithm to be used for creating the zip archive.
             *
             * If backing up only database, you may choose gzip compression for db dump and no compression at zip.
             *
             * Some common algorithms are listed below:
             * ZipArchive::CM_STORE (no compression at all; set 0 as compression level)
             * ZipArchive::CM_DEFAULT
             * ZipArchive::CM_DEFLATE
             * ZipArchive::CM_BZIP2
             * ZipArchive::CM_XZ
             *
             * For more check https://www.php.net/manual/zip.constants.php and confirm it's supported by your system.
             */
            'compression_method' => ZipArchive::CM_DEFAULT,

            /*
             * The compression level corresponding to the used algorithm; an integer between 0 and 9.
             *
             * Check supported levels for the chosen algorithm, usually 1 means the fastest and weakest compression,
             * while 9 the slowest and strongest one.
             *
             * Setting of 0 for some algorithms may switch to the strongest compression.
             */
            'compression_level' => 9,

            /*
             * The filename prefix used for the backup zip file.
             */
            'filename_prefix' => '',

            /*
             * The disk names on which the backups will be stored.
             */
            'disks' => explode(',', (string) env('BACKUP_DISKS', 'backups')),

            /*
             * Determines whether to allow backups to continue when some targets fail instead of failing completely.
             */
            'continue_on_failure' => false,
        ],

        /*
         * The directory where the temporary files will be stored.
         */
        // Out of storage/app, which is one of the directories being archived.
        'temporary_directory' => storage_path('backup-temp'),

        /*
         * The password to be used for archive encryption.
         * Set to `null` to disable encryption.
         */
        // Unset by default: on the local disk the archive protects nothing the server does not
        // already hold in the clear. Set it as soon as the destination leaves the machine — the
        // archive carries .env.
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        /*
         * The encryption algorithm to be used for archive encryption.
         * Set to 'none' to disable encryption.
         *
         * Supported: 'none', 'default', 'aes128', 'aes192', 'aes256'
         *
         * When set to 'default', we'll use AES-256 if available on your system.
         */
        'encryption' => 'default',

        /*
         * After creating the zip, verify it can be opened and contains files.
         * Recommended for critical backups but adds a small overhead.
         */
        'verify_backup' => false,

        /*
         * The number of attempts, in case the backup command encounters an exception
         */
        'tries' => 1,

        /*
         * The number of seconds to wait before attempting a new backup if the previous try failed
         * Set to `0` for none
         */
        'retry_delay' => 0,
    ],

    /*
     * You can get notified when specific events occur. Out of the box you can use 'mail' and 'slack'.
     * For Slack you need to install laravel/slack-notification-channel.
     *
     * You can also use your own notification classes, just make sure the class is named after one of
     * the `Spatie\Backup\Notifications\Notifications` classes.
     */
    'notifications' => [
        // Only the bad news, and only once there is somewhere to send it: a backup that mails a
        // daily "all good" is a backup nobody reads, and BACKUP_NOTIFICATION_EMAIL unset means the
        // failures are still in the log.
        'notifications' => [
            BackupHasFailedNotification::class => $failureChannels,
            UnhealthyBackupWasFoundNotification::class => $failureChannels,
            CleanupHasFailedNotification::class => $failureChannels,
            BackupWasSuccessfulNotification::class => [],
            HealthyBackupWasFoundNotification::class => [],
            CleanupWasSuccessfulNotification::class => [],
        ],

        /*
         * Here you can specify the notifiable to which the notifications should be sent. The default
         * notifiable will use the variables specified in this config file.
         */
        'notifiable' => Notifiable::class,

        'mail' => [
            // Read even when every mail channel is empty, and rejected if it is not an address,
            // hence the fallback.
            'to' => env('BACKUP_NOTIFICATION_EMAIL') ?: env('MAIL_FROM_ADDRESS', 'backup@example.com'),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',

            /*
             * If this is set to null the default channel of the webhook will be used.
             */
            'channel' => null,

            'username' => null,

            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',

            /*
             * If this is an empty string, the name field on the webhook will be used.
             */
            'username' => '',

            /*
             * If this is an empty string, the avatar on the webhook will be used.
             */
            'avatar_url' => '',
        ],

        /*
         * A generic webhook channel that POSTs JSON to a URL.
         * Useful for Mattermost, Microsoft Teams, or custom integrations.
         */
        'webhook' => [
            'url' => '',
        ],
    ],

    /*
     * The log channel used for backup activity messages.
     *
     * Set to a channel name defined in config/logging.php to use that channel.
     * Set to false to disable backup logging entirely.
     * Set to null to use the default log channel.
     */
    // Silent by default: the package narrates eight lines for one backup — starting, dumping,
    // determining, zipping, created, copying, copied, completed — and the job that runs it already
    // says in one line whether it worked. On failure the job logs the whole output, so nothing is
    // lost where it matters. Name a channel here to get the running commentary back.
    'log_channel' => env('BACKUP_LOG_CHANNEL', false),

    /*
     * NOT part of the published Spatie configuration — added by Bokit, and to be restored if this
     * file is ever republished.
     *
     * Backups are taken while the site is being used, like the synchronisation: no system task to
     * set up, and nothing to back up on a day nobody came. Intervals in seconds, 0 to disable that
     * kind — 'essential' is the database, the settings and the key, 'full' adds the uploaded files.
     * See App\Backup\Http\Middleware\AutoBackup.
     *
     * A backup is still taken before every deploy and before an automatic migration, whatever is
     * set here.
     */
    'essentials' => [
        'name' => $name.'/essentials',
        'include' => $essentials,
        // Kept for a day and no longer: past that there is necessarily a full backup covering the
        // same ground, and a complete archive is what one wants to find a month later.
        'keep_for_days' => env('BACKUP_KEEP_ESSENTIALS_DAYS', 1),
    ],

    'auto' => [
        'enabled' => env('BACKUP_AUTO', true),
        'essential' => env('BACKUP_ESSENTIAL_INTERVAL', 3600),
        'full' => env('BACKUP_FULL_INTERVAL', 86400),
        'cleanup' => env('BACKUP_CLEANUP_INTERVAL', 86400),
    ],

    /*
     * Here you can specify which backups should be monitored.
     * If a backup does not meet the specified requirements the
     * UnHealthyBackupWasFound event will be fired.
     */
    'monitor_backups' => [
        [
            'name' => $name.'/full',
            'disks' => explode(',', (string) env('BACKUP_DISKS', 'backups')),
            'health_checks' => [
                MaximumAgeInDays::class => 1,
                MaximumStorageInMegabytes::class => 5000,
            ],
        ],
        /*
         * [
         * 'name' => 'name of the second app',
         * 'disks' => ['local', 's3'],
         * 'health_checks' => [
         * \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
         * \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
         * ],
         * ],
         */
    ],

    'cleanup' => [
        /*
         * The strategy that will be used to cleanup old backups. The default strategy
         * will keep all backups for a certain amount of days. After that period only
         * a daily backup will be kept. After that period only weekly backups will
         * be kept and so on.
         *
         * No matter how you configure it the default strategy will never
         * delete the newest backup.
         */
        'strategy' => DefaultStrategy::class,

        'default_strategy' => [
            /*
             * The number of days for which backups must be kept.
             */
            'keep_all_backups_for_days' => env('BACKUP_KEEP_ALL_DAYS', 1),

            /*
             * After the "keep_all_backups_for_days" period is over, the most recent backup
             * of that day will be kept. Older backups within the same day will be removed.
             * If you create backups only once a day, no backups will be removed yet.
             */
            'keep_daily_backups_for_days' => env('BACKUP_KEEP_DAILY_DAYS', 7),

            /*
             * After the "keep_daily_backups_for_days" period is over, the most recent backup
             * of that week will be kept. Older backups within the same week will be removed.
             * If you create backups only once a week, no backups will be removed yet.
             */
            'keep_weekly_backups_for_weeks' => env('BACKUP_KEEP_WEEKLY_WEEKS', 4),

            /*
             * After the "keep_weekly_backups_for_weeks" period is over, the most recent backup
             * of that month will be kept. Older backups within the same month will be removed.
             */
            'keep_monthly_backups_for_months' => env('BACKUP_KEEP_MONTHLY_MONTHS', 12),

            /*
             * After the "keep_monthly_backups_for_months" period is over, the most recent backup
             * of that year will be kept. Older backups within the same year will be removed.
             */
            // As close to "one a year, kept for good" as this option gets.
            'keep_yearly_backups_for_years' => env('BACKUP_KEEP_YEARLY_YEARS', 100),

            /*
             * After cleaning up the backups remove the oldest backup until
             * this amount of megabytes has been reached.
             * Set null for unlimited size.
             */
            // No cap by default: the rules above already bound the NUMBER of archives, and a size
            // cap deletes the oldest ones — the yearly archives — first.
            'delete_oldest_backups_when_using_more_megabytes_than' => env('BACKUP_MAX_MEGABYTES'),
        ],

        /*
         * The number of attempts, in case the cleanup command encounters an exception
         */
        'tries' => 1,

        /*
         * The number of seconds to wait before attempting a new cleanup if the previous try failed
         * Set to `0` for none
         */
        'retry_delay' => 0,
    ],
];
