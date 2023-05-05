<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Loader;

use Chronhub\Storm\Chronicler\Exceptions\NoStreamEventReturn;
use Chronhub\Storm\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoader;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Storm\Stream\StreamName;
use Generator;
use Illuminate\Database\QueryException;
use stdClass;

final readonly class EventLoader implements StreamEventLoader
{
    public function __construct(private StreamEventSerializer $serializer)
    {
    }

    /**
     * @throws StreamNotFound
     */
    public function __invoke(iterable $streamEvents, StreamName $streamName): Generator
    {
        try {
            $count = 0;

            foreach ($streamEvents as $streamEvent) {
                if ($streamEvent instanceof stdClass) {
                    $streamEvent = (array) $streamEvent;
                }

                yield $this->serializer->deserializePayload($streamEvent);

                $count++;
            }

            if ($count === 0) {
                throw NoStreamEventReturn::withStreamName($streamName);
            }

            return $count;
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '00000') {
                throw StreamNotFound::withStreamName($streamName);
            }
        }
    }
}
