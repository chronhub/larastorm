<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Cqrs;

use Chronhub\Storm\Routing\Group;
use Chronhub\Storm\Producer\ProduceMessage;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Chronhub\Storm\Contracts\Producer\ProducerUnity;
use Chronhub\Storm\Contracts\Producer\MessageProducer;
use Chronhub\Larastorm\Support\Producer\IlluminateQueue;
use Chronhub\Storm\Contracts\Serializer\MessageSerializer;
use function is_string;

class MessageProducerFactory
{
    private Container $container;

    public function __construct(callable $container)
    {
        $this->container = $container();
    }

    public function createMessageProducer(Group $group): MessageProducer
    {
        $producerId = $group->producerId();

        if (is_string($producerId)) {
            return $this->container[$producerId];
        }

        $messageQueue = new IlluminateQueue(
            $this->container[QueueingDispatcher::class],
            $this->container[MessageSerializer::class],
            $group->queue()
        );

        return new ProduceMessage($this->container[ProducerUnity::class], $messageQueue);
    }
}
