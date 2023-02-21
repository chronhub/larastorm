<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Console;

use Illuminate\Console\Command;
use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Storm\Contracts\Projector\ReadModel;
use Chronhub\Storm\Contracts\Projector\ProjectorBuilder;
use Chronhub\Storm\Contracts\Projector\PersistentProjector;
use Chronhub\Storm\Contracts\Projector\ProjectionQueryFilter;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use function is_string;
use function pcntl_async_signals;

abstract class CreatePersistentProjectionCommand extends Command implements SignalableCommandInterface
{
    protected ProjectorBuilder|PersistentProjector $projector;

    protected bool $dispatchSignal = false;

    protected function project(string $streamName,
                               null|string|ReadModel $readModel,
                               array $options = [],
                               ?ProjectionQueryFilter $queryFilter = null): ProjectorBuilder
    {
        if ($this->shouldDispatchSignal()) {
            pcntl_async_signals(true);
        }

        $projector = Project::create($this->projectorName());

        if (is_string($readModel)) {
            $readModel = $this->laravel[$readModel];
        }

        $queryFilter ??= $projector->queryScope()->fromIncludedPosition();

        if ($readModel instanceof ReadModel) {
            return $projector
                ->projectReadModel($streamName, $readModel, $options)
                ->withQueryFilter($queryFilter);
        }

        return $projector
            ->projectProjection($streamName, $options)
            ->withQueryFilter($queryFilter);
    }

    public function getSubscribedSignals(): array
    {
        return [SIGINT];
    }

    public function handleSignal(int $signal): void
    {
        if ($this->shouldDispatchSignal()) {
            $this->projector->stop();
        }
    }

    protected function shouldDispatchSignal(): bool
    {
        if ($this->hasOption('signal')) {
            return (int) $this->option('signal') === 1;
        }

        return $this->dispatchSignal;
    }

    protected function shouldRunInBackground(): bool
    {
        if ($this->hasOption('in_background')) {
            return (int) $this->option('in_background') === 1;
        }

        return false;
    }

    protected function projectorName(): string
    {
        return $this->argument('projector');
    }
}
