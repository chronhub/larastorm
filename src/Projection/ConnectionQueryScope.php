<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Chronhub\Larastorm\Projection\Query\FromIncludedPosition;
use Chronhub\Larastorm\Support\Contracts\ProjectionQueryScopeConnection;
use Chronhub\Storm\Contracts\Projector\ProjectionQueryFilter;

class ConnectionQueryScope implements ProjectionQueryScopeConnection
{
    public function fromIncludedPosition(int $limit = 500): ProjectionQueryFilter
    {
        return new FromIncludedPosition($limit);
    }
}
