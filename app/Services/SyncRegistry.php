<?php

namespace App\Services;

use App\Contracts\SourceConnector;

/**
 * Collects source connectors registered by modules, keyed by source type.
 *
 * Registered as a singleton. Modules call register() in ServiceProvider::boot().
 * bokit:sync calls getForType() to find the connector for each unit source,
 * then hands it to SyncEngine.
 */
class SyncRegistry
{
    /** @var array<string, SourceConnector>  sourceType → connector */
    private array $connectors = [];

    public function register(SourceConnector $connector): void
    {
        $this->connectors[$connector->sourceType()] = $connector;
    }

    public function getForType(string $type): ?SourceConnector
    {
        return $this->connectors[$type] ?? null;
    }

    /**
     * @return array<string, SourceConnector>
     */
    public function all(): array
    {
        return $this->connectors;
    }
}
