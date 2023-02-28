<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Contracts;

use Chronhub\Storm\Contracts\Chronicler\Chronicler;

interface ChroniclerDB extends Chronicler
{
    public function isDuringCreation(): bool;
}
