<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Aggregate;

use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Chronhub\Storm\Contracts\Stream\StreamProducer;
use Chronhub\Storm\Stream\OneStreamPerAggregate;
use Chronhub\Storm\Stream\SingleStreamPerAggregate;
use Chronhub\Storm\Stream\StreamName;

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
