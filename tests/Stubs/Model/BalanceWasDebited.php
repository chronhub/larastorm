<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Stubs\Model;

use Chronhub\Storm\Reporter\DomainEvent;
use Chronhub\Storm\Message\HasConstructableContent;

final class BalanceWasDebited extends DomainEvent
{
    use HasConstructableContent;

    public static function withAmount(BalanceId $balanceId, int $amount): self
    {
        return new self([
            'balance_id' => $balanceId->toString(),
            'amount' => $amount,
        ]);
    }

    public function balanceId(): BalanceId
    {
        return BalanceId::fromString($this->content['balance_id']);
    }

    public function amount(): int
    {
        return $this->content['amount'];
    }
}
