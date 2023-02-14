<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\UniqueId;

use Stringable;
use Symfony\Component\Uid\Uuid;

// need at least generate as contract
final class UniqueId implements Stringable
{
    public static function create(): Uuid
    {
        return Uuid::v4();
    }

    public function generate(): string
    {
        return self::create()->jsonSerialize();
    }

    public function __toString(): string
    {
        return $this->generate();
    }
}
