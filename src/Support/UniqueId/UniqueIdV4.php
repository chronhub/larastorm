<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\UniqueId;

use Symfony\Component\Uid\Uuid;
use Chronhub\Storm\Contracts\Message\UniqueId;

final class UniqueIdV4 implements UniqueId
{
    public function generate(): string
    {
        return Uuid::v4()->jsonSerialize();
    }

    public function __toString(): string
    {
        return $this->generate();
    }
}
