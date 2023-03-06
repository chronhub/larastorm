<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Supervisor\Command;

use Closure;
use Illuminate\Console\Command;
use Chronhub\Larastorm\Support\Supervisor\Supervisor;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use function max;
use function pcntl_async_signals;

class SuperviseProjectionCommand extends Command implements SignalableCommandInterface
{
    final public const MIN_CHECK_EVERY = 10;

    protected $signature = 'projector:supervisor-start
                            { --output=1 : enable output }
                            { --check-every=30 : check projection status every x seconds, min is 10 }';

    protected Supervisor $supervisor;

    public function handle(Supervisor $supervisor): void
    {
        $this->supervisor = $supervisor;

        pcntl_async_signals(true);

        $this->supervisor->monitor();

        $this->loop();
    }

    public function getSubscribedSignals(): array
    {
        return [SIGINT];
    }

    public function handleSignal(int $signal): int
    {
        $this->warn('Stopping projections...');

        $this->supervisor->stop();

        return self::SUCCESS;
    }

    protected function loop(): void
    {
        while ($this->supervisor->atLeastOneRunning()) {
            $this->supervisor->check(
                $this->usingOutput(),
                max((int) $this->option('check-every'), self::MIN_CHECK_EVERY)
            );
        }
    }

    protected function usingOutput(): ?Closure
    {
        if ($this->option('output') !== '1') {
            return null;
        }

        return function ($type, $line): void {
            $this->output->write($line);
        };
    }
}
