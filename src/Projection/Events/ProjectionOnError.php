<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection\Events;

use Throwable;

final readonly class ProjectionOnError
{
    public function __construct(public string $streamName,
                                public Throwable $exception)
    {
    }
}
