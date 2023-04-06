<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Contracts;

use Chronhub\Storm\Contracts\Chronicler\StreamEventLoader;
use Chronhub\Storm\Reporter\DomainEvent;
use Chronhub\Storm\Stream\StreamName;
use Generator;
use Illuminate\Database\Query\Builder;
use stdClass;

interface StreamEventLoaderConnection extends StreamEventLoader
{
    /**
     * @return Generator{DomainEvent|stdClass|array}
     */
    public function query(Builder $builder, StreamName $streamName): Generator;
}
