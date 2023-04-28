<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Contracts;

use Chronhub\Storm\Contracts\Snapshot\SnapshotStore;
use Illuminate\Contracts\Container\Container;

interface SnapshotStoreManager
{
    public function create(string $name): SnapshotStore;

    /**
     * @param callable(Container, non-empty-string, array): SnapshotStore $snapshotStore
     */
    public function extend(string $name, callable $snapshotStore): void;
}
