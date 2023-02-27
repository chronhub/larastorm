<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Supervisor;

use Closure;
use RuntimeException;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;
use function sleep;
use function usleep;

/**
 * Dummy supervisor to monitor multiple projections.
 * Only meant for dev and rad
 *
 * @todo extends with projector manager
 */
class Supervisor
{
    protected bool $firstCheck = false;

    protected bool $isWorking = false;

    protected Collection $processes;

    public string $signature = 'projector:supervisor-start';

    public function __construct(private readonly Collection $commands,
                                public readonly string $namespace = 'project')
    {
        if ($this->commands->isEmpty()) {
            throw new RuntimeException('No commands given.');
        }

        $this->assertUniqueSupervisor();

        $this->processes = new Collection();
    }

    public function monitor(): void
    {
        foreach ($this->commands as $command => $name) {
            $this->processes->put($name, $this->createProcess($command));
        }

        $this->start();
    }

    public function stop(): void
    {
        $this->processes->each(
            function (SupervisorProcess $supervised): void {
                if ($supervised->process->isStarted()) {
                    $supervised->stop();
                }
            }
        );

        // help check with output to display final status
        while ($this->atLeastOneRunning()) {
            usleep(100);
        }

        $this->isWorking = false;
    }

    public function check(?Closure $output = null, int $timeout = 10): void
    {
        if (! $this->isWorking) {
            return;
        }

        if ($this->firstCheck) {
            sleep($timeout);
        }

        $this->processes
            ->when($output !== null)
            ->each(
                function (SupervisorProcess $supervised, string $name) use ($output): void {
                    $line = ! $supervised->process->isRunning() ? 'stopped' : 'running';

                    $output->__invoke(Process::OUT, "Projection $name is $line. ".PHP_EOL);
                });

        $this->firstCheck = true;
    }

    public function atLeastOneRunning(): bool
    {
        return $this->processes
            ->skipUntil(fn (SupervisorProcess $supervised) => $supervised->process->isRunning())
            ->isNotEmpty();
    }

    public function isSupervisorRunning(): bool
    {
        return $this->countSupervisorProcess() === 1;
    }

    public function getNames(): array
    {
        return $this->commands->values()->all();
    }

    protected function start(): void
    {
        $this->processes->each(
            function (SupervisorProcess $supervised): void {
                if (! $supervised->process->isStarted()) {
                    $supervised->start();
                }
            }
        );

        $this->isWorking = true;
    }

    protected function createProcess(string $signature): SupervisorProcess
    {
        $command = MakeCommandAsString::toCommandString($this->namespace, $signature);

        $process = Process::fromShellCommandline($command, base_path())
            ->setTimeout(null)
            ->disableOutput();

        return new SupervisorProcess($process);
    }

    protected function countSupervisorProcess(): int
    {
        $command = 'ps aux | grep '.$this->signature.' | grep -v grep | wc -l';

        $processes = Process::fromShellCommandline($command)->mustRun()->getOutput();

        return (int) $processes;
    }

    protected function assertUniqueSupervisor(): void
    {
        if ($this->countSupervisorProcess() > 1) {
            throw new RuntimeException('There is already a supervisor running.');
        }
    }
}
