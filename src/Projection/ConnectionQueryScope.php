<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Chronhub\Larastorm\Projection\Query\FromIncludedPosition;
use Chronhub\Storm\Contracts\Projector\ProjectionQueryFilter;
use Chronhub\Larastorm\Projection\Query\FromIncludedPositionWithLimit;
use Chronhub\Larastorm\Support\Contracts\ProjectionQueryScopeConnection;

class ConnectionQueryScope implements ProjectionQueryScopeConnection
{
    public function fromIncludedPosition(): ProjectionQueryFilter
    {
        return new FromIncludedPosition();
    }

    public function fromIncludedPositionWithLimit(int $limit = 1000): ProjectionQueryFilter
    {
        return new FromIncludedPositionWithLimit($limit);
    }
}
