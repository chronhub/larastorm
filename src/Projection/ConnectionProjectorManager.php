<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Throwable;
use Illuminate\Database\QueryException;
use Illuminate\Contracts\Events\Dispatcher;
use Chronhub\Storm\Projector\Scheme\Context;
use Chronhub\Storm\Contracts\Projector\Store;
use Chronhub\Storm\Projector\ProjectionStatus;
use Chronhub\Storm\Contracts\Projector\ReadModel;
use Chronhub\Storm\Projector\AbstractProjectorManager;
use Chronhub\Storm\Projector\Repository\StandaloneStore;
use Chronhub\Storm\Contracts\Projector\ProjectorRepository;
use Chronhub\Storm\Projector\Exceptions\ProjectionNotFound;
use Chronhub\Larastorm\Exceptions\ConnectionProjectionFailed;
use Chronhub\Storm\Projector\Repository\ReadModelProjectorRepository;
use Chronhub\Storm\Projector\Repository\PersistentProjectorRepository;

final class ConnectionProjectorManager extends AbstractProjectorManager
{
    private ?Dispatcher $eventDispatcher = null;

    protected function createStore(Context $context, string $streamName): Store
    {
        $store = new StandaloneStore(
            $context,
            $this->projectionProvider,
            $this->createLock($context->option),
            $this->jsonSerializer,
            $streamName
        );

        if ($this->eventDispatcher) {
            $store = new StandaloneAwareStore($store, $this->eventDispatcher);
        }

        return $store;
    }

    protected function createRepository(Context $context, Store $store, ?ReadModel $readModel): ProjectorRepository
    {
        $store = new ConnectionStore($store);

        if ($readModel) {
            return new ReadModelProjectorRepository($context, $store, $readModel);
        }

        return new PersistentProjectorRepository($context, $store, $this->chronicler);
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function stop(string $streamName): void
    {
        $this->updateProjectionStatus($streamName, ProjectionStatus::STOPPING);
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function reset(string $streamName): void
    {
        $this->updateProjectionStatus($streamName, ProjectionStatus::RESETTING);
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function delete(string $streamName, bool $withEmittedEvents): void
    {
        $deleteProjectionStatus = $withEmittedEvents
            ? ProjectionStatus::DELETING_WITH_EMITTED_EVENTS
            : ProjectionStatus::DELETING;

        $this->updateProjectionStatus($streamName, $deleteProjectionStatus);
    }

    public function setEventDispatcher(Dispatcher $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws ConnectionProjectionFailed
     * @throws ProjectionNotFound
     */
    protected function updateProjectionStatus(string $streamName, ProjectionStatus $projectionStatus): void
    {
        try {
            $success = $this->projectionProvider->updateProjection(
                $streamName,
                ['status' => $projectionStatus->value]
            );
        } catch (QueryException $exception) {
            throw ConnectionProjectionFailed::fromQueryException($exception);
        } catch (Throwable $exception) {
            throw ConnectionProjectionFailed::failedOnUpdateStatus($streamName, $projectionStatus, $exception);
        }

        if (! $success) {
            $this->assertProjectionExists($streamName);
        }
    }
}
