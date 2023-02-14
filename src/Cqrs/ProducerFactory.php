<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Cqrs;

use Chronhub\Storm\Routing\Group;
use Chronhub\Storm\Producer\ProduceMessage;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Storm\Contracts\Producer\ProducerUnity;
use Chronhub\Storm\Contracts\Producer\MessageProducer;
use Chronhub\Larastorm\Support\Producer\IlluminateQueue;
use Chronhub\Storm\Contracts\Serializer\MessageSerializer;
use function is_string;

class ProducerFactory
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function __invoke(Group $group): MessageProducer
    {
        $producerId = $group->producerServiceId();

        if (is_string($producerId)) {
            return $this->app[$producerId];
        }

        $messageQueue = new IlluminateQueue(
            $this->app[QueueingDispatcher::class],
            $this->app[MessageSerializer::class],
            $group->queue()
        );

        return new ProduceMessage($this->app[ProducerUnity::class], $messageQueue);
    }
}
