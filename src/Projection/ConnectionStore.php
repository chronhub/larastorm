<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Illuminate\Database\QueryException;
use Chronhub\Storm\Contracts\Projector\Store;
use Chronhub\Storm\Projector\ProjectionStatus;
use Chronhub\Larastorm\Exceptions\ConnectionProjectionFailed;

final readonly class ConnectionStore implements Store
{
    public function __construct(private Store $store)
    {
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function create(): bool
    {
        try {
            $created = $this->store->create();
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromProjectionException($queryException);
        }

        if (! $created) {
            throw ConnectionProjectionFailed::failedOnCreate($this->currentStreamName());
        }

        return true;
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function stop(): bool
    {
        try {
            $stopped = $this->store->stop();
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromProjectionException($queryException);
        }

        if (! $stopped) {
            throw ConnectionProjectionFailed::failedOnStop($this->currentStreamName());
        }

        return true;
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function startAgain(): bool
    {
        try {
            $restarted = $this->store->startAgain();
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromProjectionException($queryException);
        }

        if (! $restarted) {
            throw ConnectionProjectionFailed::failedOnStartAgain($this->currentStreamName());
        }

        return true;
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function persist(): bool
    {
        try {
            $persisted = $this->store->persist();
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromProjectionException($queryException);
        }

        if (! $persisted) {
            throw ConnectionProjectionFailed::failedOnPersist($this->currentStreamName());
        }

        return true;
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function reset(): bool
    {
        try {
            $reset = $this->store->reset();
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromProjectionException($queryException);
        }

        if (! $reset) {
            throw ConnectionProjectionFailed::failedOnReset($this->currentStreamName());
        }

        return true;
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function delete(bool $withEmittedEvents): bool
    {
        try {
            $deleted = $this->store->delete($withEmittedEvents);
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromProjectionException($queryException);
        }

        if (! $deleted) {
            throw ConnectionProjectionFailed::failedOnDelete($this->currentStreamName());
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
            $locked = $this->store->acquireLock();
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromQueryException($queryException);
        }

        if (! $locked) {
            throw ConnectionProjectionFailed::failedOnAcquireLock($this->currentStreamName());
        }

        return true;
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function updateLock(): bool
    {
        try {
            $updated = $this->store->updateLock();
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromProjectionException($queryException);
        }

        if (! $updated) {
            throw ConnectionProjectionFailed::failedOnUpdateLock($this->currentStreamName());
        }

        return true;
    }

    /**
     * @throws ConnectionProjectionFailed
     */
    public function releaseLock(): bool
    {
        try {
            $released = $this->store->releaseLock();
        } catch (QueryException $queryException) {
            throw ConnectionProjectionFailed::fromProjectionException($queryException);
        }

        if (! $released) {
            throw ConnectionProjectionFailed::failedOnReleaseLock($this->currentStreamName());
        }

        return true;
    }

    public function loadState(): bool
    {
        return $this->store->loadState();
    }

    public function loadStatus(): ProjectionStatus
    {
        return $this->store->loadStatus();
    }

    public function exists(): bool
    {
        return $this->store->exists();
    }

    public function currentStreamName(): string
    {
        return $this->store->currentStreamName();
    }
}
