<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Stubs;

use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\QueryException;
use Chronhub\Larastorm\EventStore\EventStoreConnection;

class EventStoreConnectionStub extends EventStoreConnection
{
    private ?QueryException $exception = null;

    protected function handleException(QueryException $exception, StreamName $streamName): void
    {
        $this->exception = $exception;
    }

    public function getRaisedException(): ?QueryException
    {
        return $this->exception;
    }
}
