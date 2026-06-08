<?php

namespace App\Contracts;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Implemented by each module that wants to plug into bokit:sync.
 *
 * Modules register an instance via SyncRegistry::register() in their
 * ServiceProvider::boot(). bokit:sync iterates all registered handlers
 * without knowing anything about specific modules.
 */
interface SyncHandler
{
    /**
     * A short human-readable label shown in sync output (e.g. "Beds24 API").
     */
    public function label(): string;

    /**
     * Execute the sync.
     *
     * @param  OutputInterface  $output  Console output for progress messages.
     * @param  bool             $dryRun  When true, report changes without writing.
     */
    public function handle(OutputInterface $output, bool $dryRun = false): void;
}
