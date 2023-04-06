<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Stubs\Model;

use Chronhub\Storm\Aggregate\HasAggregateBehaviour;
use Chronhub\Storm\Contracts\Aggregate\AggregateIdentity;
use Chronhub\Storm\Contracts\Aggregate\AggregateRoot;
use function abs;

final class Balance implements AggregateRoot
{
    use HasAggregateBehaviour;

    private int $amount = 0;

    public function register(BalanceId $balanceId): self
    {
        $self = new self($balanceId);

        $self->recordThat(BalanceWasRegistered::withBalance($balanceId));

        return $self;
    }

    public function credit(int $amount): void
    {
        $this->recordThat(BalanceWasCredited::withAmount($this->balanceId(), abs($amount)));
    }

    public function debit(int $amount): void
    {
        $this->recordThat(BalanceWasDebited::withAmount($this->balanceId(), abs($amount)));
    }

    public function balanceId(): BalanceId|AggregateIdentity
    {
        return $this->aggregateId();
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function applyBalanceWasRegistered(BalanceWasRegistered $event): void
    {
        $this->amount = $event->balance();
    }

    public function applyBalanceWasCredited(BalanceWasCredited $event): void
    {
        $this->amount += $event->amount();
    }

    public function applyBalanceWasDebited(BalanceWasDebited $event): void
    {
        $this->amount -= $event->amount();
    }
}
