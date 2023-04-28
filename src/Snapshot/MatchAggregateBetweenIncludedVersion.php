<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Snapshot;

use Chronhub\Storm\Contracts\Chronicler\QueryFilter;
use Illuminate\Database\Query\Builder;

final readonly class MatchAggregateBetweenIncludedVersion implements QueryFilter
{
    public function __construct(
        private string $aggregateId,
        private int $fromVersion,
        private int $toVersion
    ) {
    }

    public function apply(): callable
    {
        return function (Builder $query): void {
            $query
                ->where('aggregate_id', $this->aggregateId)
                ->whereBetween('aggregate_version', [$this->fromVersion, $this->toVersion])
                ->orderBy('aggregate_version', 'ASC');
        };
    }
}
