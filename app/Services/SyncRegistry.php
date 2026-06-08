<?php

namespace App\Services;

use App\Contracts\SyncHandler;

/**
 * Collects sync handlers registered by modules.
 *
 * Registered as a singleton in the service container. Modules call
 * app(SyncRegistry::class)->register($handler) in their ServiceProvider::boot().
 * bokit:sync iterates all() without knowing which modules are active.
 */
class SyncRegistry
{
    /** @var SyncHandler[] */
    private array $handlers = [];

    public function register(SyncHandler $handler): void
    {
        $this->handlers[] = $handler;
    }

    /** @return SyncHandler[] */
    public function all(): array
    {
        return $this->handlers;
    }
}
