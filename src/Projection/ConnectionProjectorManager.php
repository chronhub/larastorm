<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Illuminate\Contracts\Events\Dispatcher;
use Chronhub\Storm\Projector\Scheme\Context;
use Chronhub\Storm\Contracts\Projector\Store;
use Chronhub\Storm\Contracts\Projector\ReadModel;
use Chronhub\Storm\Projector\AbstractProjectorManager;
use Chronhub\Storm\Projector\Repository\StandaloneStore;
use Chronhub\Storm\Contracts\Projector\ProjectorRepository;
use Chronhub\Storm\Projector\Repository\ReadModelProjectorRepository;
use Chronhub\Storm\Projector\Repository\PersistentProjectorRepository;

final class ConnectionProjectorManager extends AbstractProjectorManager
{
    use InteractWithProjectionProvider;

    private ?Dispatcher $eventDispatcher = null;

    public function setEventDispatcher(Dispatcher $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

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
}
