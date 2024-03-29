<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Cqrs;

use Chronhub\Storm\Contracts\Message\MessageAlias;
use Chronhub\Storm\Contracts\Message\MessageDecorator;
use Chronhub\Storm\Contracts\Producer\ProducerUnity;
use Chronhub\Storm\Contracts\Tracker\MessageSubscriber;
use Chronhub\Storm\Message\ChainMessageDecorator;
use Chronhub\Storm\Message\DecorateMessage;
use Chronhub\Storm\Producer\ProducerMessageDecorator;
use Chronhub\Storm\Reporter\Subscribers\NameReporterService;
use Chronhub\Storm\Routing\FindRoute;
use Chronhub\Storm\Routing\Group;
use Chronhub\Storm\Routing\HandleRoute;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use function array_map;
use function is_string;

class MessageSubscribersFactory
{
    protected Container $container;

    private MessageProducerFactory $producerFactory;

    public function __construct(
        Closure $container,
        MessageProducerFactory $producerFactory
    ) {
        $this->container = $container();
        $this->producerFactory = $producerFactory;
    }

    /**
     * @return array<MessageSubscriber>
     */
    public function createMessageSubscribers(Group $group, string $reporterClass): array
    {
        $reporterId = $group->reporterId() ?? $group->reporterConcrete() ?? $reporterClass;

        return $this->resolveServices([
            new NameReporterService($reporterId),
            $this->container['config']->get('messager.subscribers', []),
            $group->subscribers(),
            $this->chainMessageDecorators($group),
            $this->makeRouteSubscriber($group),
        ]);
    }

    protected function makeRouteSubscriber(Group $group): MessageSubscriber
    {
        $routeLocator = new FindRoute($group, $this->container[MessageAlias::class], $this->container);

        $messageProducer = $this->producerFactory->createMessageProducer($group);

        return new HandleRoute($routeLocator, $messageProducer, $this->container[ProducerUnity::class]);
    }

    protected function chainMessageDecorators(Group $group): MessageSubscriber
    {
        $messageDecorators = [
            new ProducerMessageDecorator($group->strategy()),
            $this->container['config']->get('messager.decorators', []),
            $group->decorators(),
        ];

        return new DecorateMessage(
            new ChainMessageDecorator(...$this->resolveServices($messageDecorators))
        );
    }

    /**
     * @param  array<int, string|MessageSubscriber|MessageDecorator> ...$services
     * @return array<int, MessageSubscriber|MessageDecorator>
     */
    private function resolveServices(array ...$services): array
    {
        return array_map(
            fn ($service) => is_string($service) ? $this->container[$service] : $service,
            Arr::flatten($services)
        );
    }
}
