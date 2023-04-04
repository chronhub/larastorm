<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Loader;

use Generator;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\Query\Builder;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoader;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Chronhub\Larastorm\Support\Contracts\StreamEventLoaderConnection;

final readonly class LazyQueryLoader implements StreamEventLoaderConnection
{
    public function __construct(private StreamEventLoader $eventLoader,
                                public int $chunkSize = 1000)
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('Chunk size must be greater than 0');
        }
    }

    public function query(Builder $builder, StreamName $streamName): Generator
    {
        $streamEvents = ($this->eventLoader)($builder->lazy($this->chunkSize), $streamName);

        yield from $streamEvents;

        return $streamEvents->getReturn();
    }
}
