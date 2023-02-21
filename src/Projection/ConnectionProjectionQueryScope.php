<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Illuminate\Contracts\Database\Query\Builder;
use Chronhub\Storm\Contracts\Projector\ProjectionQueryScope;
use Chronhub\Storm\Contracts\Projector\ProjectionQueryFilter;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException;

class ConnectionProjectionQueryScope implements ProjectionQueryScope
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
}
