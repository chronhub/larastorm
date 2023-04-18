<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Console;

use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Storm\Projector\Exceptions\ProjectionNotFound;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use function count;
use function json_encode;

#[AsCommand(name: 'projector:read', description: 'read state/positions/status of projection by projection name')]
final class ReadProjectionCommand extends Command
{
    protected $signature = 'projector:read 
                            { field      : available state,status,positions }
                            { projection : projection name }
                            { projector  : projector name }';

    protected $description = 'read state/positions/status of projection by stream name';

    public function handle(): int
    {
        $projectionName = $this->argument('projection');

        $result = $this->processProjection($projectionName);

        $this->displayResult($projectionName, $result);

        return self::SUCCESS;
    }

    private function processProjection(string $projectionName): array
    {
        $projector = Project::create($this->argument('projector'));

        if (! $projector->exists($projectionName)) {
            throw ProjectionNotFound::withName($projectionName);
        }

        return match ($this->fieldArgument()) {
            'state' => $projector->stateOf($projectionName),
            'status' => [$projector->statusOf($projectionName)],
            'positions' => $projector->streamPositionsOf($projectionName),
            default => throw new InvalidArgumentException('Invalid field '.$this->fieldArgument())
        };
    }

    private function displayResult(string $projectionName, array $result): void
    {
        $displayResult = count($result) === 0 ? 'Empty' : json_encode($result, JSON_THROW_ON_ERROR);

        $this->info("{$this->fieldArgument()} of $projectionName projection is: $displayResult");
    }

    private function fieldArgument(): string
    {
        return $this->argument('field');
    }
}
