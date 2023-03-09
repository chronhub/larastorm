<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection\Events;

final readonly class ProjectionStarted
{
    public function __construct(public string $streamName)
    {
    }
}
