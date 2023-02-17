<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Stubs;

use Chronhub\Storm\Aggregate\HasAggregateBehaviour;
use Chronhub\Storm\Contracts\Aggregate\AggregateRoot;
use Chronhub\Storm\Contracts\Aggregate\AggregateIdentity;

class AggregateRootStub implements AggregateRoot
{
    use HasAggregateBehaviour;

    public static function create(AggregateIdentity $aggregateId): self
    {
        return new self($aggregateId);
    }
}
