<?php

namespace App\Services;

use App\Contracts\SyncHandler;

/**
 * Collects sync handlers registered by modules, keyed by source type.
 *
 * Registered as a singleton. Modules call register() in ServiceProvider::boot().
 * bokit:sync calls getForType() to dispatch each unit source to the right handler.
 */
class SyncRegistry
{
    /** @var array<string, SyncHandler>  sourceType → handler */
    private array $handlers = [];

    public function register(SyncHandler $handler): void
    {
        $this->handlers[$handler->sourceType()] = $handler;
    }

    public function getForType(string $type): ?SyncHandler
    {
        return $this->handlers[$type] ?? null;
    }

    /**
     * @return array<string, SyncHandler>
     */
    public function all(): array
    {
        return $this->handlers;
    }
}
