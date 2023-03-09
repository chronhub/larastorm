<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Console;

use InvalidArgumentException;
use Illuminate\Console\Command;
use Chronhub\Storm\Stream\StreamName;
use Chronhub\Larastorm\Support\Facade\Project;
use Symfony\Component\Console\Attribute\AsCommand;
use Chronhub\Storm\Contracts\Projector\ProjectorManager;
use Chronhub\Storm\Projector\Exceptions\ProjectionNotFound;
use function in_array;

#[AsCommand(name: 'projector:write', description: 'write operation on projection by stream name')]
final class WriteProjectionCommand extends Command
{
    protected $signature = 'projector:write 
                            { operation : available stop, reset, delete, deleteIncl } 
                            { stream    : projection name } 
                            { projector : projector name }';

    protected array $operationAvailable = ['stop', 'reset', 'delete', 'deleteIncl'];

    private ProjectorManager $projector;

    public function handle(): void
    {
        $streamName = new StreamName($this->argument('stream'));

        $this->projector = Project::create($this->argument('projector'));

        $operation = $this->operationArgument();

        if (! $this->confirmOperation($streamName, $operation)) {
            return;
        }

        $this->processProjection($streamName->name, $operation);

        $this->info("Operation {$this->operationArgument()} on $streamName projection successful");
    }

    private function processProjection(string $streamName, string $operation): void
    {
        match ($operation) {
            'stop' => $this->projector->stop($streamName),
            'reset' => $this->projector->reset($streamName),
            'delete' => $this->projector->delete($streamName, false),
            'deleteIncl' => $this->projector->delete($streamName, true),
        };
    }

    private function confirmOperation(StreamName $streamName, string $operation): bool
    {
        try {
            $projectionStatus = $this->projector->statusOf($streamName->name);
        } catch (ProjectionNotFound) {
            $this->error("Projection not found with stream $streamName");

            return false;
        }

        $this->warn("Status of $streamName projection is $projectionStatus");

        if (! $this->confirm("Are you sure you want to $operation stream $streamName")) {
            $this->warn("Operation $operation on stream $streamName aborted");

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
