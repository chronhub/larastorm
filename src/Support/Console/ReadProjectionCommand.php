<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Console;

use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Storm\Projector\Exceptions\ProjectionNotFound;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use function count;
use function json_encode;

#[AsCommand(name: 'projector:read', description: 'read state/positions/status of projection by stream name')]
final class ReadProjectionCommand extends Command
{
    protected $signature = 'projector:read 
                            { field     : available state,status,positions }
                            { stream    : stream projection name }
                            { projector : projector name }';

    protected $description = 'read state/positions/status of projection by stream name';

    public function handle(): int
    {
        $streamName = (new StreamName($this->argument('stream')))->name;

        $result = $this->processProjection($streamName);

        $this->displayResult($streamName, $result);

        return self::SUCCESS;
    }

    private function processProjection(string $streamName): array
    {
        $projector = Project::create($this->argument('projector'));

        if (! $projector->exists($streamName)) {
            throw ProjectionNotFound::withName($streamName);
        }

        return match ($this->fieldArgument()) {
            'state' => $projector->stateOf($streamName),
            'status' => [$projector->statusOf($streamName)],
            'positions' => $projector->streamPositionsOf($streamName),
            default => throw new InvalidArgumentException('Invalid field '.$this->fieldArgument())
        };
    }

    private function displayResult(string $streamName, array $result): void
    {
        $displayResult = count($result) === 0 ? 'Empty' : json_encode($result, JSON_THROW_ON_ERROR);

        $this->info("{$this->fieldArgument()} of $streamName projection is: $displayResult");
    }

    private function fieldArgument(): string
    {
        return $this->argument('field');
    }
}
