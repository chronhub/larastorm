<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore;

use Chronhub\Larastorm\Exceptions\ConnectionConcurrencyException;
use Chronhub\Larastorm\Exceptions\ConnectionQueryFailure;
use Chronhub\Storm\Chronicler\Exceptions\StreamAlreadyExists;
use Chronhub\Storm\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\QueryException;

class PgsqlEventStore extends EventStoreConnection
{
    protected function handleException(QueryException $exception, StreamName $streamName): void
    {
        if ($this->isDuringCreation()) {
            match ($exception->getCode()) {
                '23000', '23505' => throw StreamAlreadyExists::withStreamName($streamName),
                default => throw ConnectionQueryFailure::fromQueryException($exception)
            };
        }

        match ($exception->getCode()) {
            '42P01' => throw StreamNotFound::withStreamName($streamName),
            '23000', '23505' => throw ConnectionConcurrencyException::fromUnlockStreamFailure($exception),
            default => throw ConnectionQueryFailure::fromQueryException($exception)
        };
    }
}
