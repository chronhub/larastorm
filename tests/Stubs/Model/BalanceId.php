<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Stubs\Model;

use Chronhub\Storm\Aggregate\HasAggregateIdentity;
use Chronhub\Storm\Contracts\Aggregate\AggregateIdentity;
use Symfony\Component\Uid\Uuid;

final class BalanceId implements AggregateIdentity
{
    use HasAggregateIdentity;

    public static function create(): self
    {
        return new self(Uuid::v4());
    }
}
