<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Supervisor;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ExceptionInterface;

class SupervisorProcess
{
    public function __construct(public readonly Process $process)
    {
    }

    public function start(): self
    {
        $this->process->start();

        return $this;
    }

    public function stop(): void
    {
        try {
            $this->process->signal(SIGINT);
        } catch (ExceptionInterface $e) {
            if ($this->process->isRunning()) {
                throw $e;
            }
        }
    }
}
