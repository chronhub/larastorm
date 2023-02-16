<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Throwable;
use Illuminate\Database\QueryException;
use Chronhub\Storm\Projector\ProjectionStatus;
use Chronhub\Storm\Projector\AbstractProjectorManager;
use Chronhub\Storm\Projector\Exceptions\ProjectionNotFound;
use Chronhub\Larastorm\Exceptions\ConnectionProjectionFailed;

final class ConnectionProjectorManager extends AbstractProjectorManager
{
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

    /**
     * @throws ConnectionProjectionFailed
     * @throws ProjectionNotFound
     */
    protected function updateProjectionStatus(string $streamName, ProjectionStatus $projectionStatus): void
    {
        try {
            $success = $this->factory->projectionProvider->updateProjection(
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
