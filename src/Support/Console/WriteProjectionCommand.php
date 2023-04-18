<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Console;

use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Storm\Contracts\Projector\ProjectorManagerInterface;
use Chronhub\Storm\Projector\Exceptions\ProjectionNotFound;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use function in_array;

#[AsCommand(name: 'projector:write', description: 'write operation on projection by projection name')]
final class WriteProjectionCommand extends Command
{
    protected $signature = 'projector:write 
                            { operation  : available stop, reset, delete, deleteIncl } 
                            { projection : projection name } 
                            { projector  : projector name }';

    protected array $operationAvailable = ['stop', 'reset', 'delete', 'deleteIncl'];

    private ProjectorManagerInterface $projector;

    public function handle(): int
    {
        $projectionName = $this->argument('projection');

        $this->projector = Project::create($this->argument('projector'));

        $operation = $this->operationArgument();

        if (! $this->confirmOperation($projectionName, $operation)) {
            return self::FAILURE;
        }

        $this->processProjection($projectionName, $operation);

        $this->info("Operation {$this->operationArgument()} on $projectionName projection successful");

        return self::SUCCESS;
    }

    private function processProjection(string $streamName, string $operation): void
    {
        /** @phpstan-ignore-next-line */
        match ($operation) {
            'stop' => $this->projector->stop($streamName),
            'reset' => $this->projector->reset($streamName),
            'delete' => $this->projector->delete($streamName, false),
            'deleteIncl' => $this->projector->delete($streamName, true),
        };
    }

    private function confirmOperation(string $projectionName, string $operation): bool
    {
        try {
            $projectionStatus = $this->projector->statusOf($projectionName);
        } catch (ProjectionNotFound) {
            $this->error("Projection not found with name $projectionName");

            return false;
        }

        $this->warn("Status of $projectionName projection is $projectionStatus");

        if (! $this->confirm("Are you sure you want to $operation projection $projectionName")) {
            $this->warn("Operation $operation on projection $projectionName aborted");

            return false;
        }

        return true;
    }

    private function operationArgument(): string
    {
        $operation = $this->argument('operation');

        if (! in_array($operation, $this->operationAvailable, true)) {
            throw new InvalidArgumentException("Invalid operation $operation");
        }

        return $operation;
    }
}
