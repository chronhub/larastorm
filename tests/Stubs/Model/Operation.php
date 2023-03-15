<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Stubs\Model;

use function abs;

final readonly class Operation
{
    final public const DEBIT = 'debit';

    final public const CREDIT = 'credit';

    public function __construct(public string $type, public int $amount)
    {
    }

    public static function debit(int $amount): self
    {
        return new self(self::DEBIT, abs($amount));
    }

    public static function credit(int $amount): self
    {
        return new self(self::CREDIT, abs($amount));
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAbsoluteAmount(): int
    {
        return $this->amount;
    }
}
