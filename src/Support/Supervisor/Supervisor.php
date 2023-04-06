<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Supervisor;

use Closure;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;
use function sleep;
use function usleep;

/**
 * Dummy supervisor to monitor multiple projections.
 * Only meant for dev and rad
 *
 * we should be able to fetch real status (resetting, delete, deleteInc) from the projector manager
 * but, we have to considered the type of projector (read model or persistent projection) as we can not
 * restart automatically
 *
 * deleteIncl can be restarted but put here an option
 * delete only should stop the projector only
 * reset RM should restart itself
 * reset a non RM should stop the projector
 *
 * @todo extends with projector manager
 */
class Supervisor
{
    public string $signature = 'projector:supervisor-start';

    protected bool $firstCheck = true;

    protected bool $isWorking = false;

    protected Collection $processes;

    public function __construct(protected readonly Collection $commands,
                                public readonly string $namespace = 'project')
    {
        if ($this->commands->isEmpty()) {
            throw new InvalidArgumentException('No commands given.');
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

        if (! $this->firstCheck) {
            sleep($timeout);
        }

        $this->processes
            ->when($output !== null)
            ->each(
                function (SupervisorProcess $supervised, string $name) use ($output): void {
                    $line = ! $supervised->process->isRunning() ? 'stopped' : 'running';

                    $output->__invoke(Process::OUT, "Projection $name is $line. ".PHP_EOL);
                });

        $this->firstCheck = false;
    }

    public function atLeastOneRunning(): bool
    {
        return $this->processes
            ->skipUntil(fn (SupervisorProcess $supervised) => $supervised->process->isRunning())
            ->isNotEmpty();
    }

    public function isSupervisorRunning(): bool
    {
        return $this->countMasterProcess() === 1;
    }

    public function isWorking(): bool
    {
        return $this->isWorking;
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

    protected function countMasterProcess(): int
    {
        $command = 'ps aux | grep '.$this->signature.' | grep -v grep | wc -l';

        $processes = Process::fromShellCommandline($command)->mustRun()->getOutput();

        return (int) $processes;
    }

    protected function assertUniqueSupervisor(): void
    {
        if ($this->countMasterProcess() > 1) {
            throw new RuntimeException('There is already a supervisor running.');
        }
    }

    public function __destruct()
    {
    }
}
