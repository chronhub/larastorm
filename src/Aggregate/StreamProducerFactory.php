<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Aggregate;

use Chronhub\Storm\Stream\StreamName;
use Chronhub\Storm\Stream\OneStreamPerAggregate;
use Chronhub\Storm\Contracts\Stream\StreamProducer;
use Chronhub\Storm\Stream\SingleStreamPerAggregate;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;

final class StreamProducerFactory
{
    public function createStreamProducer(string $streamName, ?string $strategy): StreamProducer
    {
        $streamName = new StreamName($streamName);

        return match ($strategy) {
            'single' => new SingleStreamPerAggregate($streamName),
            'per_aggregate' => new OneStreamPerAggregate($streamName),
            default => throw new InvalidArgumentException("Strategy given for stream name $streamName is not defined")
        };
    }
}
