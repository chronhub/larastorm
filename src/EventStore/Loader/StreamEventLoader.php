<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Loader;

use Generator;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\QueryException;
use Chronhub\Storm\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Larastorm\Exceptions\ConnectionQueryFailure;
use Chronhub\Storm\Contracts\Serializer\StreamEventConverter;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoader as EventLoader;

final class StreamEventLoader implements EventLoader
{
    public function __construct(private readonly StreamEventConverter $eventConverter)
    {
    }

    /**
     * @param  iterable  $streamEvents
     * @param  StreamName  $streamName
     * @return Generator
     *
     * @throws StreamNotFound
     * @throws ConnectionQueryFailure
     */
    public function __invoke(iterable $streamEvents, StreamName $streamName): Generator
    {
        try {
            $count = 0;

            foreach ($streamEvents as $streamEvent) {
                yield $this->eventConverter->toDomainEvent($streamEvent);

                $count++;
            }

            if (0 === $count) {
                throw StreamNotFound::withStreamName($streamName);
            }

            return $count;
        } catch (QueryException $queryException) {
            if ('00000' !== $queryException->getCode()) {
                throw StreamNotFound::withStreamName($streamName);
            }

            throw ConnectionQueryFailure::fromQueryException($queryException);
        }
    }
}
