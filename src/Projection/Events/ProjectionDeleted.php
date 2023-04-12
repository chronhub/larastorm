<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection\Events;

final readonly class ProjectionDeleted
{
    public function __construct(public string $streamName)
    {
    }
}
