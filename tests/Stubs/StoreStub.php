<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Stubs;

use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\Query\Builder;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabase;

final class StoreStub extends EventStoreDatabase
{
    public function getBuilderForWrite(StreamName $streamName): Builder
    {
        return $this->forWrite($streamName);
    }

    public function getBuilderForRead(StreamName $streamName): Builder
    {
        return $this->forRead($streamName);
    }

    public function getStreamEventsSerialized(iterable $streamEvents): array
    {
        return $this->serializeStreamEvents($streamEvents);
    }
}
