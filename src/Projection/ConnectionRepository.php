<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Chronhub\Larastorm\Exceptions\ConnectionProjectionFailed;
use Chronhub\Storm\Contracts\Projector\ProjectionRepositoryInterface;
use Chronhub\Storm\Projector\ProjectionStatus;
use Illuminate\Database\QueryException;

final readonly class ConnectionRepository implements ProjectionRepositoryInterface
{
    public function __construct(private ProjectionRepositoryInterface $repository)
    {
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function create(ProjectionStatus $status): bool
    {
        try {
            $created = $this->repository->create($status);
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
    public function stop(array $streamPositions, array $state): bool
    {
        try {
            $stopped = $this->repository->stop($streamPositions, $state);
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
    public function persist(array $streamPositions, array $state): bool
    {
        try {
            $persisted = $this->repository->persist($streamPositions, $state);
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
    public function reset(array $streamPositions, array $state, ProjectionStatus $currentStatus): bool
    {
        try {
            $reset = $this->repository->reset($streamPositions, $state, $currentStatus);
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
    public function delete(): bool
    {
        try {
            $deleted = $this->repository->delete();
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
    public function updateLock(array $streamPositions): bool
    {
        try {
            $updated = $this->repository->updateLock($streamPositions);
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

    public function loadState(): array
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
