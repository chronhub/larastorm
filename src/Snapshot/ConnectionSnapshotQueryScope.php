<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Snapshot;

use Chronhub\Storm\Contracts\Aggregate\AggregateIdentity;
use Chronhub\Storm\Contracts\Chronicler\QueryFilter;
use Chronhub\Storm\Contracts\Snapshot\SnapshotQueryScope;

final class ConnectionSnapshotQueryScope implements SnapshotQueryScope
{
    public function matchAggregateGreaterThanVersion(AggregateIdentity $aggregateId, string $aggregateType, int $aggregateVersion): QueryFilter
    {
        return new MatchAggregateGreaterThanVersion($aggregateType, $aggregateId->toString(), $aggregateVersion);
    }

    public function matchAggregateBetweenIncludedVersion(AggregateIdentity $aggregateId, int $fromVersion, int $toVersion): QueryFilter
    {
        return new MatchAggregateBetweenIncludedVersion($aggregateId->toString(), $fromVersion, $toVersion);
    }
}
