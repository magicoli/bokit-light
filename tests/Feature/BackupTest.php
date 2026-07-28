<?php

use App\Backup\Console\Commands\BackupCommand;
use App\Backup\Console\Commands\CleanBackupsCommand;
use App\Backup\Console\Commands\ListBackupsCommand;
use App\Backup\Jobs\RunBackupJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

/**
 * What the one archive on the (faked) backup disk actually contains.
 *
 * @return list<string>
 */
function archivedPaths(): array
{
    $archive = collect(Storage::disk('backups')->allFiles())
        ->first(fn(string $path): bool => str_ends_with($path, '.zip'));

    expect($archive)->not->toBeNull();

    $zip = new ZipArchive();
    $zip->open(Storage::disk('backups')->path($archive));

    $paths = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $paths[] = (string) $zip->getNameIndex($i);
    }

    $zip->close();

    return $paths;
}

it('backs up the database the application actually uses', function () {
    // The published Spatie default falls back to mysql where config/database.php falls back to
    // sqlite. An installation that never sets DB_CONNECTION would back up nothing, without saying
    // so, which is the worst way for a backup to fail.
    expect(config('backup.backup.source.databases'))->toBe([config('database.default')]);
});

it('archives what no deploy could rebuild', function () {
    // Per-record options are JSON files, not rows: the properties' Beds24 keys and WordPress
    // passwords live there. And without the key, the encrypted values in the dump are unreadable.
    expect(config('backup.essentials.include'))->toContain(config('options.path'))->toContain(base_path('.env'));

    expect(config('backup.backup.source.files.include'))->toContain(storage_path('app'));

    // The code is in git, and vendor/ is most of what a full copy would weigh.
    expect(config('backup.backup.source.files.exclude'))
        ->toContain(base_path('vendor'))
        ->toContain(storage_path('logs'));
});

it('puts the settings and the key in every archive, the uploads only in a full one', function () {
    Storage::fake('backups');

    $this->artisan('backup:run', ['--essentials' => true])->assertSuccessful();

    $paths = archivedPaths();

    expect($paths)
        ->toContain('db-dumps/sqlite-sqlite-database.sql')
        ->and(collect($paths)->filter(fn(string $p): bool => str_contains($p, 'storage/options/')))
        ->not->toBeEmpty()->and(collect($paths)->filter(fn(string $p): bool => str_ends_with($p, '.env')))
        ->not->toBeEmpty()
        // The point of the small backup: it is small.
        ->and(collect($paths)->filter(fn(string $p): bool => str_contains($p, 'storage/app')))->toBeEmpty();
})->skip(fn(): bool => !class_exists(ZipArchive::class), 'ext-zip is required to read the archive');

it('archives the uploaded files unless told to leave them out', function () {
    Storage::fake('backups');

    $this->artisan('backup:run')->assertSuccessful();

    expect(collect(archivedPaths())->filter(fn(string $p): bool => str_contains($p, 'storage/app')))->not->toBeEmpty();
})->skip(fn(): bool => !class_exists(ZipArchive::class), 'ext-zip is required to read the archive');

it('answers to the package name and to ours with one and the same command', function () {
    // Whichever name comes to mind — the package's, from its documentation, or the application's,
    // from `php artisan list` — must do the same thing. Two commands that read alike and behave
    // differently is how a backup ends up somewhere nobody looks for it.
    $commands = Artisan::all();

    expect($commands['backup:run'])
        ->toBeInstanceOf(BackupCommand::class)
        ->and($commands['backup:run']->getAliases())
        ->toContain('bokit:backup')
        ->and($commands['backup:clean'])
        ->toBeInstanceOf(CleanBackupsCommand::class)
        ->and($commands['backup:clean']->getAliases())
        ->toContain('bokit:backup-clean')
        ->and($commands['backup:list'])
        ->toBeInstanceOf(ListBackupsCommand::class)
        ->and($commands['backup:list']->getAliases())
        ->toContain('bokit:backup-list');
});

it('lists both destinations, not only the full ones', function () {
    Storage::fake('backups');

    $this->artisan('backup:run', ['--essentials' => true])->assertSuccessful();

    // A listing that reports "no backups present" while archives sit on the disk is worse than no
    // listing at all: it is the answer someone would act on.
    $this->artisan('backup:list')->expectsOutputToContain(config('backup.essentials.name'))->assertSuccessful();
});

it('keeps the two kinds apart, so neither retention touches the other', function () {
    // Siblings, never nested: a destination lists its archives recursively, so essentials sitting
    // inside the full destination would be thinned out as if they were complete backups.
    $full = config('backup.backup.name');
    $essentials = config('backup.essentials.name');

    expect($essentials)
        ->not
        ->toBe($full)
        ->and(str_starts_with($essentials, $full . '/'))
        ->toBeFalse()
        ->and(str_starts_with($full, $essentials . '/'))
        ->toBeFalse();
});

it('writes each kind to its own destination', function () {
    Storage::fake('backups');

    $this->artisan('backup:run', ['--essentials' => true])->assertSuccessful();
    $this->artisan('backup:run')->assertSuccessful();

    expect(Storage::disk('backups')->files(config('backup.essentials.name')))
        ->toHaveCount(1)
        ->and(Storage::disk('backups')->files(config('backup.backup.name')))
        ->toHaveCount(1);
});

it('drops an essential backup once a full one covers it, and keeps the full one for years', function () {
    Storage::fake('backups');

    $old = now()->subDays(30)->format('Y-m-d-H-i-s') . '.zip';
    $recent = now()->format('Y-m-d-H-i-s') . '.zip';

    // A recent one in each destination, because no strategy ever deletes the only backup it has.
    foreach ([config('backup.essentials.name'), config('backup.backup.name')] as $destination) {
        Storage::disk('backups')->put($destination . '/' . $old, 'x');
        Storage::disk('backups')->put($destination . '/' . $recent, 'x');
    }

    $this->artisan('bokit:backup-clean')->assertSuccessful();

    expect(Storage::disk('backups')->exists(config('backup.essentials.name') . '/' . $old))
        ->toBeFalse()
        ->and(Storage::disk('backups')->exists(config('backup.backup.name') . '/' . $old))
        ->toBeTrue();
});

it('keeps every backup of the last day, then thins out', function () {
    $strategy = config('backup.cleanup.default_strategy');

    expect($strategy['keep_all_backups_for_days'])
        ->toBe(1)
        ->and($strategy['keep_daily_backups_for_days'])
        ->toBe(7)
        ->and($strategy['keep_weekly_backups_for_weeks'])
        ->toBe(4)
        ->and($strategy['keep_monthly_backups_for_months'])
        ->toBe(12);
});

it('takes a backup on the first page load past the interval', function () {
    Bus::fake();
    config(['backup.auto.enabled' => true]);

    $this->get('/')->assertSuccessful();

    Bus::assertDispatchedAfterResponse(
        RunBackupJob::class,
        // Nothing has been backed up yet, so all of it is due — and the full backup stands in for
        // the essential one rather than being taken alongside it.
        fn(RunBackupJob $job): bool => $job->tasks === ['full', 'cleanup'],
    );
});

it('leaves the next visitors alone until the interval has elapsed', function () {
    Bus::fake();
    config(['backup.auto.enabled' => true]);

    $this->get('/')->assertSuccessful();
    $this->get('/')->assertSuccessful();

    // Once, not once per visitor.
    Bus::assertDispatchedAfterResponseTimes(RunBackupJob::class, 1);
});

it('takes no backup when the site is not being visited', function () {
    // Nothing to assert but the absence of a scheduler: no cron entry, no queue worker, no
    // background timer — a day without visits is a day without changes.
    expect(app(Schedule::class)->events())->toBeEmpty();
});

it('runs the same command an operator would', function () {
    Storage::fake('backups');

    (new RunBackupJob(['essential', 'cleanup']))->handle();

    expect(collect(Storage::disk('backups')->allFiles())
        ->filter(fn(string $path): bool => str_ends_with($path, '.zip')))->toHaveCount(1);
});
