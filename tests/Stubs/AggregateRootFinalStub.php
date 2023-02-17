<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Stubs;

use Chronhub\Storm\Aggregate\HasAggregateBehaviour;
use Chronhub\Storm\Contracts\Aggregate\AggregateRoot;

final class AggregateRootFinalStub implements AggregateRoot
{
    use HasAggregateBehaviour;
}
