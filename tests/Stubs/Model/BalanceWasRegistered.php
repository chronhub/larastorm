<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Stubs\Model;

use Chronhub\Storm\Message\HasConstructableContent;
use Chronhub\Storm\Reporter\DomainEvent;

class BalanceWasRegistered extends DomainEvent
{
    use HasConstructableContent;

    public static function withBalance(BalanceId $balanceId): self
    {
        return new self([
            'balance_id' => $balanceId->toString(),
            'balance' => 0,
        ]);
    }

    public function balanceId(): BalanceId
    {
        return BalanceId::fromString($this->content['balance_id']);
    }

    public function balance(): int
    {
        return $this->content['balance'];
    }
}
