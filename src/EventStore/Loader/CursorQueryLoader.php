<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Loader;

use Chronhub\Larastorm\Support\Contracts\StreamEventLoaderConnection;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoader;
use Chronhub\Storm\Stream\StreamName;
use Generator;
use Illuminate\Database\Query\Builder;

final readonly class CursorQueryLoader implements StreamEventLoaderConnection
{
    public function __construct(private StreamEventLoader $eventLoader)
    {
    }

    public function query(Builder $builder, StreamName $streamName): Generator
    {
        $streamEvents = ($this->eventLoader)($builder->cursor(), $streamName);

        yield from $streamEvents;

        return $streamEvents->getReturn();
    }
}
