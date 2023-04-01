<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Illuminate\Contracts\Events\Dispatcher;
use Chronhub\Storm\Contracts\Projector\ReadModel;
use Chronhub\Storm\Projector\AbstractSubscriptionFactory;
use Chronhub\Storm\Contracts\Projector\ProjectionRepository;
use Chronhub\Storm\Contracts\Projector\PersistentViewSubscription;
use Chronhub\Storm\Projector\Repository\ReadModelProjectionRepository;
use Chronhub\Storm\Contracts\Projector\PersistentReadModelSubscription;
use Chronhub\Storm\Projector\Repository\PersistentProjectionRepository;

final class ConnectionSubscriptionFactory extends AbstractSubscriptionFactory
{
    protected ?Dispatcher $eventDispatcher = null;

    public function createSubscriptionManagement(
        PersistentReadModelSubscription|PersistentViewSubscription $subscription,
        string $streamName,
        ?ReadModel $readModel): ProjectionRepository
    {
        $store = $this->createStore($subscription, $streamName);

        $adapter = new ConnectionProjectionStore($store);

        if ($this->eventDispatcher) {
            $adapter = new DispatcherAwareProjectionStore($adapter, $this->eventDispatcher);
        }

        if ($readModel) {
            return new ReadModelProjectionRepository($subscription, $adapter, $readModel);
        }

        return new PersistentProjectionRepository($subscription, $adapter, $this->chronicler);
    }

    public function setEventDispatcher(Dispatcher $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }
}
