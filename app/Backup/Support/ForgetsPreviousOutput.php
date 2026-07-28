<?php

namespace App\Backup\Support;

use Spatie\Backup\Support\BackupLogger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The backup package attaches a console listener to a shared logger every time one of its commands
 * runs, and never detaches it. A single process running two of them — the deploy, the periodic job
 * — would print everything twice, then three times.
 */
trait ForgetsPreviousOutput
{
    public function run(InputInterface $input, OutputInterface $output): int
    {
        app(BackupLogger::class)->clearListeners();

        return parent::run($input, $output);
    }
}
