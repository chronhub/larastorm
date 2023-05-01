<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Snapshot;

use Chronhub\Storm\Contracts\Chronicler\QueryFilter;
use Illuminate\Database\Query\Builder;

final readonly class MatchAggregateGreaterThanVersion implements QueryFilter
{
    public function __construct(
        private string $aggregateType,
        private string $aggregateId,
        private int $aggregateVersion
    ) {
    }

    public function apply(): callable
    {
        return function (Builder $query): void {
            $query
                ->where('aggregate_id', $this->aggregateId)
                ->where('aggregate_type', $this->aggregateType)
                ->where('aggregate_version', '>', $this->aggregateVersion)
                ->orderBy('aggregate_version', 'ASC');
        };
    }
}
