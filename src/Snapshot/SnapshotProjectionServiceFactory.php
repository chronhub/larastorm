<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Snapshot;

use Chronhub\Larastorm\Support\Contracts\AggregateRepositoryManager;
use Chronhub\Larastorm\Support\Contracts\SnapshotStoreManager as StoreManager;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepositoryWithSnapshotting;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Storm\Contracts\Projector\ProjectorFactory;
use Chronhub\Storm\Contracts\Projector\ProjectorServiceManager;
use Chronhub\Storm\Contracts\Projector\ReadModelCasterInterface;
use Chronhub\Storm\Reporter\DomainEvent;
use Chronhub\Storm\Snapshot\RestoreAggregateSnapshot;
use Chronhub\Storm\Snapshot\SnapshotReadModel;
use Chronhub\Storm\Snapshot\VersioningSnapshotProvider;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

class SnapshotProjectionServiceFactory
{
    public function __construct(
        protected ProjectorServiceManager $projectorServiceManager,
        protected AggregateRepositoryManager $aggregateRepositoryManager,
        protected StoreManager $snapshotStoreManager,
        protected Container $container,
    ) {
    }

    public function create(string $streamName, string $projectorName, int $versioning = 1000): ProjectorFactory
    {
        $repository = $this->aggregateRepositoryManager->create($streamName);

        if (! $repository instanceof AggregateRepositoryWithSnapshotting) {
            throw new RuntimeException('Aggregate repository must implement '.AggregateRepositoryWithSnapshotting::class);
        }

        $snapshotStore = $this->snapshotStoreManager->create($projectorName);

        // todo fetch query scope from snapshot store as it aware of connection too

        $provider = new VersioningSnapshotProvider(
            $snapshotStore,
            new RestoreAggregateSnapshot($repository, $this->container[ConnectionSnapshotQueryScope::class]),
            $this->container[SystemClock::class],
            $versioning
        );

        // todo auto fetch aggregate type with lineage from repository config
        $readModel = new SnapshotReadModel($provider, []);

        $projector = $this->projectorServiceManager->create($streamName);

        return $this->projectorServiceManager
            ->create($streamName)
            ->readModel($streamName.'_snapshot', $readModel)
            ->withQueryFilter($projector->queryScope()->fromIncludedPosition()) // todo bring limit
            ->fromStreams($streamName)
            ->whenAny(function (DomainEvent $event): void {
                /** @var ReadModelCasterInterface $this */
                $this->readModel()->stack('snapshot ...', $event);
            });
    }
}
