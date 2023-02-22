<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Contracts;

use stdClass;
use Generator;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\Query\Builder;
use Chronhub\Storm\Reporter\DomainEvent;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoader;

interface StreamEventLoaderConnection extends StreamEventLoader
{
    /**
     * @return Generator{DomainEvent|stdClass|array}
     */
    public function query(Builder $builder, StreamName $streamName): Generator;
}
