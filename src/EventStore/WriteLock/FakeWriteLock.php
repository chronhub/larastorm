<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\WriteLock;

use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;

final class FakeWriteLock implements WriteLockStrategy
{
    public function acquireLock(string $tableName): bool
    {
        return true;
    }

    public function releaseLock(string $tableName): bool
    {
        return true;
    }
}
