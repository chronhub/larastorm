<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Contracts;

use Chronhub\Storm\Contracts\Projector\ProjectionQueryFilter;
use Chronhub\Storm\Contracts\Projector\ProjectionQueryScope;

interface ProjectionQueryScopeConnection extends ProjectionQueryScope
{
    public function fromIncludedPositionWithLimit(int $limit = 1000): ProjectionQueryFilter;
}
