<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Chronhub\Storm\Projector\Scheme\Context;
use Chronhub\Storm\Contracts\Projector\Store;
use Chronhub\Storm\Contracts\Projector\ReadModel;
use Chronhub\Storm\Projector\ProjectorManagerFactory;
use Chronhub\Storm\Contracts\Projector\ProjectorRepository;
use Chronhub\Storm\Projector\Repository\ReadModelProjectorRepository;
use Chronhub\Storm\Projector\Repository\PersistentProjectorRepository;

final class ConnectionProjectorFactory extends ProjectorManagerFactory
{
    public function makeRepository(Context $context, Store $store, ?ReadModel $readModel): ProjectorRepository
    {
        $store = new ConnectionStore($store);

        if ($readModel) {
            return new ReadModelProjectorRepository($context, $store, $readModel);
        }

        return new PersistentProjectorRepository($context, $store, $this->chronicler);
    }
}
