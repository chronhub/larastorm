<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Loader;

use Generator;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\Query\Builder;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoaderConnection;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoader as EventLoader;

final readonly class CursorQueryLoader implements StreamEventLoaderConnection
{
    public function __construct(private EventLoader $eventLoader)
    {
    }

    public function query(Builder $builder, StreamName $streamName): Generator
    {
        $streamEvents = ($this->eventLoader)($builder->cursor(), $streamName);

        yield from $streamEvents;

        return $streamEvents->getReturn();
    }
}
