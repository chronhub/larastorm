<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Console;

use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Storm\Contracts\Projector\ProjectorManagerInterface;
use Chronhub\Storm\Projector\Exceptions\ProjectionNotFound;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use function in_array;

#[AsCommand(name: 'projector:write', description: 'write operation on projection by stream name')]
final class WriteProjectionCommand extends Command
{
    protected $signature = 'projector:write 
                            { operation : available stop, reset, delete, deleteIncl } 
                            { stream    : projection name } 
                            { projector : projector name }';

    protected array $operationAvailable = ['stop', 'reset', 'delete', 'deleteIncl'];

    private ProjectorManagerInterface $projector;

    public function handle(): int
    {
        $streamName = new StreamName($this->argument('stream'));

        $this->projector = Project::create($this->argument('projector'));

        $operation = $this->operationArgument();

        if (! $this->confirmOperation($streamName, $operation)) {
            return self::FAILURE;
        }

        $this->processProjection($streamName->name, $operation);

        $this->info("Operation {$this->operationArgument()} on $streamName projection successful");

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
