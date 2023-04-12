<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Chronhub\Storm\Contracts\Projector\ProjectionOption;
use Chronhub\Storm\Contracts\Projector\ProjectionRepositoryInterface;
use Chronhub\Storm\Projector\AbstractSubscriptionFactory;
use Illuminate\Contracts\Events\Dispatcher;

final class ConnectionSubscriptionFactory extends AbstractSubscriptionFactory
{
    protected ?Dispatcher $eventDispatcher = null;

    public function createSubscriptionManagement(string $streamName, ProjectionOption $options): ProjectionRepositoryInterface
    {
        $repository = $this->createRepository($streamName, $options);

        $adapter = new ConnectionRepository($repository);

        if ($this->eventDispatcher) {
            $adapter = new DispatcherAwareRepository($adapter, $this->eventDispatcher);
        }

        return $adapter;
    }

    public function setEventDispatcher(Dispatcher $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }
}
