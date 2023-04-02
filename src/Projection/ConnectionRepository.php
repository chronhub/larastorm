<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Illuminate\Database\QueryException;
use Chronhub\Storm\Projector\ProjectionStatus;
use Chronhub\Larastorm\Exceptions\ConnectionProjectionFailed;
use Chronhub\Storm\Contracts\Projector\ProjectionRepositoryInterface;

final readonly class ConnectionRepository implements ProjectionRepositoryInterface
{
    public function __construct(private ProjectionRepositoryInterface $repository)
    {
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function create(): bool
    {
        try {
            $created = $this->repository->create();
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromProjectionException($queryException);
        }

        if (! $created) {
            throw ConnectionProjectionFailed::failedOnCreate($this->projectionName());
        }

        return true;
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function stop(): bool
    {
        try {
            $stopped = $this->repository->stop();
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromProjectionException($queryException);
        }

        if (! $stopped) {
            throw ConnectionProjectionFailed::failedOnStop($this->projectionName());
        }

        return true;
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function startAgain(): bool
    {
        try {
            $restarted = $this->repository->startAgain();
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromProjectionException($queryException);
        }

        if (! $restarted) {
            throw ConnectionProjectionFailed::failedOnStartAgain($this->projectionName());
        }

        return true;
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function persist(): bool
    {
        try {
            $persisted = $this->repository->persist();
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromProjectionException($queryException);
        }

        if (! $persisted) {
            throw ConnectionProjectionFailed::failedOnPersist($this->projectionName());
        }

        return true;
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function reset(): bool
    {
        try {
            $reset = $this->repository->reset();
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromProjectionException($queryException);
        }

        if (! $reset) {
            throw ConnectionProjectionFailed::failedOnReset($this->projectionName());
        }

        return true;
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function delete(bool $withEmittedEvents): bool
    {
        try {
            $deleted = $this->repository->delete($withEmittedEvents);
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromProjectionException($queryException);
        }

        if (! $deleted) {
            throw ConnectionProjectionFailed::failedOnDelete($this->projectionName());
        }

        return true;
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function acquireLock(): bool
    {
        // checkMe dead catch
        try {
            $locked = $this->repository->acquireLock();
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromQueryException($queryException);
        }

        if (! $locked) {
            throw ConnectionProjectionFailed::failedOnAcquireLock($this->projectionName());
        }

        return true;
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function updateLock(): bool
    {
        try {
            $updated = $this->repository->updateLock();
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromProjectionException($queryException);
        }

        if (! $updated) {
            throw ConnectionProjectionFailed::failedOnUpdateLock($this->projectionName());
        }

        return true;
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function releaseLock(): bool
    {
        try {
            $released = $this->repository->releaseLock();
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromProjectionException($queryException);
        }

        if (! $released) {
            throw ConnectionProjectionFailed::failedOnReleaseLock($this->projectionName());
        }

        return true;
    }

    public function loadState(): bool
    {
        return $this->repository->loadState();
    }

    public function loadStatus(): ProjectionStatus
    {
        return $this->repository->loadStatus();
    }

    public function exists(): bool
    {
        return $this->repository->exists();
    }

    public function projectionName(): string
    {
        return $this->repository->projectionName();
    }
}
