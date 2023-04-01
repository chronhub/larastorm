<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Illuminate\Contracts\Events\Dispatcher;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Storm\Contracts\Projector\ReadModel;
use Chronhub\Storm\Projector\SubscriptionFactory;
use Chronhub\Storm\Contracts\Message\MessageAlias;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Serializer\JsonSerializer;
use Chronhub\Storm\Contracts\Projector\ProjectionOption;
use Chronhub\Storm\Contracts\Projector\ProjectionProvider;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Contracts\Projector\ProjectionQueryScope;
use Chronhub\Storm\Contracts\Projector\ProjectionRepository;
use Chronhub\Storm\Contracts\Projector\PersistentViewSubscription;
use Chronhub\Storm\Projector\Repository\ReadModelProjectionRepository;
use Chronhub\Storm\Contracts\Projector\PersistentReadModelSubscription;
use Chronhub\Storm\Projector\Repository\PersistentProjectionRepository;

final readonly class ConnectionSubscriptionFactory extends SubscriptionFactory
{
    public function __construct(
        public Chronicler $chronicler,
        public ProjectionProvider $projectionProvider,
        public EventStreamProvider $eventStreamProvider,
        public ProjectionQueryScope $queryScope,
        public SystemClock $clock,
        public MessageAlias $messageAlias,
        public JsonSerializer $jsonSerializer,
        public ProjectionOption|array $options = [],
        protected ?Dispatcher $eventDispatcher = null
    ) {
        parent::__construct(
            $chronicler, $projectionProvider, $eventStreamProvider,
            $queryScope, $clock, $messageAlias, $jsonSerializer, $options
        );
    }

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
}
