<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Support\Supervisor;

use Chronhub\Larastorm\Support\Supervisor\Supervisor;
use Illuminate\Support\Collection;

final class SupervisorStub extends Supervisor
{
    public function getCommands(): Collection
    {
        return $this->commands;
    }

    public function isFirstCheck(): bool
    {
        return $this->firstCheck;
    }
}
