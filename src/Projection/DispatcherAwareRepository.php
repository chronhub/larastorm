<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Chronhub\Larastorm\Projection\Events\ProjectionDeleted;
use Chronhub\Larastorm\Projection\Events\ProjectionOnError;
use Chronhub\Larastorm\Projection\Events\ProjectionReset;
use Chronhub\Larastorm\Projection\Events\ProjectionRestarted;
use Chronhub\Larastorm\Projection\Events\ProjectionStarted;
use Chronhub\Larastorm\Projection\Events\ProjectionStopped;
use Chronhub\Storm\Contracts\Projector\ProjectionRepositoryInterface;
use Chronhub\Storm\Projector\ProjectionStatus;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

final readonly class DispatcherAwareRepository implements ProjectionRepositoryInterface
{
    public function __construct(
        private ProjectionRepositoryInterface $store,
        private Dispatcher $eventDispatcher
    ) {
    }

    public function create(): bool
    {
        try {
            $created = $this->store->create();

            $this->eventDispatcher->dispatch(new ProjectionStarted($this->store->projectionName()));

            return $created;
        } catch (Throwable $e) {
            $this->dispatchExceptionEvent($e);

            throw $e;
        }
    }

    public function stop(): bool
    {
        try {
            $stopped = $this->store->stop();

            $this->eventDispatcher->dispatch(new ProjectionStopped($this->store->projectionName()));

            return $stopped;
        } catch (Throwable $e) {
            $this->dispatchExceptionEvent($e);

            throw $e;
        }
    }

    public function startAgain(): bool
    {
        try {
            $restarted = $this->store->startAgain();

            $this->eventDispatcher->dispatch(new ProjectionRestarted($this->store->projectionName()));

            return $restarted;
        } catch (Throwable $e) {
            $this->dispatchExceptionEvent($e);

            throw $e;
        }
    }

    public function reset(): bool
    {
        try {
            $reset = $this->store->reset();

            $this->eventDispatcher->dispatch(new ProjectionReset($this->store->projectionName()));

            return $reset;
        } catch (Throwable $e) {
            $this->dispatchExceptionEvent($e);

            throw $e;
        }
    }

    public function delete(bool $withEmittedEvents): bool
    {
        try {
            $deleted = $this->store->delete($withEmittedEvents);

            $this->eventDispatcher->dispatch(new ProjectionDeleted($this->store->projectionName(), $withEmittedEvents));

            return $deleted;
        } catch (Throwable $e) {
            $this->dispatchExceptionEvent($e);

            throw $e;
        }
    }

    public function loadState(): bool
    {
        return $this->store->loadState();
    }

    public function loadStatus(): ProjectionStatus
    {
        return $this->store->loadStatus();
    }

    public function persist(): bool
    {
        return $this->store->persist();
    }

    public function exists(): bool
    {
        return $this->store->exists();
    }

    public function acquireLock(): bool
    {
        return $this->store->acquireLock();
    }

    public function updateLock(): bool
    {
        return $this->store->updateLock();
    }

    public function releaseLock(): bool
    {
        return $this->store->releaseLock();
    }

    public function projectionName(): string
    {
        return $this->store->projectionName();
    }

    private function dispatchExceptionEvent(Throwable $e): void
    {
        $this->eventDispatcher->dispatch(new ProjectionOnError($this->store->projectionName(), $e));
    }
}
