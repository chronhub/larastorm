<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Chronhub\Storm\Contracts\Projector\EmitterSubscriptionInterface;
use Chronhub\Storm\Contracts\Projector\ProjectionManagement;
use Chronhub\Storm\Contracts\Projector\ReadModel;
use Chronhub\Storm\Contracts\Projector\ReadModelSubscriptionInterface;
use Chronhub\Storm\Projector\AbstractSubscriptionFactory;
use Chronhub\Storm\Projector\Repository\EmitterManager;
use Chronhub\Storm\Projector\Repository\ReadModelManager;
use Illuminate\Contracts\Events\Dispatcher;

final class ConnectionSubscriptionFactory extends AbstractSubscriptionFactory
{
    protected ?Dispatcher $eventDispatcher = null;

    public function createSubscriptionManagement(
        EmitterSubscriptionInterface|ReadModelSubscriptionInterface $subscription,
        string $streamName,
        ?ReadModel $readModel): ProjectionManagement
    {
        $repository = $this->createRepository($subscription, $streamName);

        $adapter = new ConnectionRepository($repository);

        if ($this->eventDispatcher) {
            $adapter = new DispatcherAwareRepository($adapter, $this->eventDispatcher);
        }

        if ($readModel) {
            return new ReadModelManager($subscription, $adapter, $readModel);
        }

        return new EmitterManager($subscription, $adapter, $this->chronicler);
    }

    public function setEventDispatcher(Dispatcher $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }
}
