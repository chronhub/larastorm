<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Stubs\Model;

use Symfony\Component\Uid\Uuid;
use Chronhub\Storm\Aggregate\HasAggregateIdentity;
use Chronhub\Storm\Contracts\Aggregate\AggregateIdentity;

final class BalanceId implements AggregateIdentity
{
    use HasAggregateIdentity;

    public static function create(): self
    {
        return new self(Uuid::v4());
    }
}
