<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Loader;

use stdClass;
use Generator;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\QueryException;
use Chronhub\Storm\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Larastorm\Exceptions\ConnectionQueryFailure;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoader;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;

final readonly class EventLoader implements StreamEventLoader
{
    public function __construct(private StreamEventSerializer $serializer)
    {
    }

    /**
     * @throws StreamNotFound
     * @throws ConnectionQueryFailure
     */
    public function __invoke(iterable $streamEvents, StreamName $streamName): Generator
    {
        try {
            $count = 0;

            foreach ($streamEvents as $streamEvent) {
                if ($streamEvent instanceof stdClass) {
                    $streamEvent = (array) $streamEvent;
                }

                yield $this->serializer->unserializeContent($streamEvent)->current();

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
