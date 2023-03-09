<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Illuminate\Contracts\Database\Query\Builder;
use Chronhub\Storm\Contracts\Projector\ProjectionQueryFilter;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException;
use Chronhub\Larastorm\Support\Contracts\ProjectionQueryScopeConnection;

class ConnectionQueryScope implements ProjectionQueryScopeConnection
{
    public function fromIncludedPosition(): ProjectionQueryFilter
    {
        return new class() implements ProjectionQueryFilter
        {
            private int $currentPosition = 0;

            public function setCurrentPosition(int $streamPosition): void
            {
                $this->currentPosition = $streamPosition;
            }

            public function apply(): callable
            {
                $position = $this->currentPosition;

                if ($position <= 0) {
                    throw new InvalidArgumentException("Position must be greater than 0, current is $position");
                }

                return static function (Builder $query) use ($position): void {
                    $query
                        ->where('no', '>=', $position)
                        ->orderBy('no');
                };
            }
        };
    }

    public function fromIncludedPositionWithLimit(int $limit = 1000): ProjectionQueryFilter
    {
        return new class($limit) implements ProjectionQueryFilter
        {
            private int $currentPosition = 0;

            public function __construct(private readonly int $limit)
            {
            }

            public function setCurrentPosition(int $streamPosition): void
            {
                $this->currentPosition = $streamPosition;
            }

            public function apply(): callable
            {
                $position = $this->currentPosition;

                if ($position <= 0) {
                    throw new InvalidArgumentException("Position must be greater than 0, current is $position");
                }

                return function (Builder $query) use ($position): void {
                    $query
                        ->where('no', '>=', $position)
                        ->orderBy('no')
                        ->limit($this->limit);
                };
            }
        };
    }
}
