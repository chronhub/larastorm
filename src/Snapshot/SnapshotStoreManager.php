<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Snapshot;

use Chronhub\Larastorm\Support\Contracts\SnapshotStoreManager as Manager;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Storm\Contracts\Snapshot\SnapshotStore;
use Chronhub\Storm\Snapshot\InMemorySnapshotStore;
use Closure;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

final class SnapshotStoreManager implements Manager
{
    private Container $container;

    /**
     * @var array<non-empty-string, SnapshotStore>
     */
    private array $stores = [];

    /**
     * @var array<non-empty-string, callable(Container, non-empty-string, array): SnapshotStore>
     */
    private array $customCreators = [];

    public function __construct(Closure $container)
    {
        $this->container = $container();
    }

    public function create(string $name): SnapshotStore
    {
        $config = $this->container['config']['snapshot'] ?? [];

        if ($config === []) {
            throw new RuntimeException('Snapshot configuration not found');
        }

        return $this->stores[$name] ?? $this->stores[$name] = $this->resolve($name, $config);
    }

    public function extend(string $name, callable $snapshotStore): void
    {
        $this->customCreators[$name] = $snapshotStore;
    }

    private function resolve(string $name, array $config): SnapshotStore
    {
        if ($this->customCreators[$name] ?? false) {
            return $this->customCreators[$name]($this->container, $name, $config);
        }

        return match ($name) {
            'in_memory' => new InMemorySnapshotStore(),
            'connection' => $this->resolveConnectionSnapshotStore($config),
            default => throw new RuntimeException("Snapshot name $name unknown"),
        };
    }

    private function resolveConnectionSnapshotStore(array $config): SnapshotStore
    {
        return new ConnectionSnapshotStore(
            $this->container['db']->connection($config['default']),
            $this->container[$config['serializer']],
            $this->container[SystemClock::class],
            $config['suffix'] ?? null,
            $config['table_name'] ?? null,
            $config['mapping_tables'] ?? []
        );
    }
}
