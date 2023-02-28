<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Supervisor\Command;

use Illuminate\Console\Command;
use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Larastorm\Support\Supervisor\Supervisor;

class CheckSupervisedProjectionStatusCommand extends Command
{
    protected $signature = 'projector:check-supervised
                            { name : The name of the projector }';

    protected $description = 'Check real projection status which are supervised';

    public function handle(Supervisor $supervisor): void
    {
        $projectorManager = Project::create($this->argument('name'));

        if (! $supervisor->isSupervisorRunning()) {
            $this->error('Projector Supervisor is not running');

            return;
        }

        $projections = $supervisor->getNames();

        $rows = tap([], function (array &$rows) use ($projectorManager, $projections): void {
            foreach ($projections as $stream) {
                $status = $projectorManager->statusOf($stream);

                $rows[] = [$stream, $status];
            }
        });

        $this->table(['Projection', 'Status'], $rows);
    }
}
