<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Cqrs;

use Illuminate\Support\Arr;
use Chronhub\Storm\Routing\Group;
use Chronhub\Storm\Routing\FindRoute;
use Chronhub\Storm\Reporter\DomainType;
use Chronhub\Storm\Routing\HandleRoute;
use Chronhub\Storm\Reporter\ReportEvent;
use Chronhub\Storm\Reporter\ReportQuery;
use Chronhub\Storm\Tracker\TrackMessage;
use Chronhub\Storm\Reporter\ReportCommand;
use Chronhub\Storm\Message\DecorateMessage;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Contracts\Reporter\Reporter;
use Chronhub\Storm\Contracts\Routing\Registrar;
use Chronhub\Storm\Message\ChainMessageDecorator;
use Chronhub\Storm\Contracts\Message\MessageAlias;
use Chronhub\Storm\Contracts\Producer\ProducerUnity;
use Chronhub\Storm\Producer\ProducerMessageDecorator;
use Chronhub\Storm\Contracts\Message\MessageDecorator;
use Chronhub\Storm\Contracts\Reporter\ReporterManager;
use Chronhub\Storm\Contracts\Tracker\MessageSubscriber;
use Chronhub\Storm\Routing\Exceptions\RoutingViolation;
use Chronhub\Storm\Reporter\Subscribers\NameReporterService;
use function array_map;
use function is_string;

final readonly class CqrsManager implements ReporterManager
{
    private Container $container;

    private Registrar $registrar;

    private MessageProducerFactory $producerFactory;

    public function __construct(callable $container)
    {
        $this->container = $container();
        $this->registrar = $this->container[Registrar::class];
        $this->producerFactory = new MessageProducerFactory($container);
    }

    public function create(string $type, string $name): Reporter
    {
        $group = $this->registrar->get(DomainType::from($type), $name);

        if (! $group instanceof Group) {
            throw new RoutingViolation("Group with type $type and name $name not defined");
        }

        return $this->resolve($group);
    }

    public function command(string $name = 'default'): Reporter
    {
        return $this->create(DomainType::COMMAND->value, $name);
    }

    public function event(string $name = 'default'): Reporter
    {
        return $this->create(DomainType::EVENT->value, $name);
    }

    public function query(string $name = 'default'): Reporter
    {
        return $this->create(DomainType::QUERY->value, $name);
    }

    private function resolve(Group $group): Reporter
    {
        $reporter = $this->newReporterInstance($group);

        $messageSubscribers = $this->makeMessageSubscribers($group, $reporter::class);

        $reporter->subscribe(...$messageSubscribers);

        return $reporter;
    }

    private function newReporterInstance(Group $group): Reporter
    {
        $concrete = $group->reporterConcrete();

        if (null === $concrete) {
            $concrete = match ($group->getType()) {
                DomainType::COMMAND => ReportCommand::class,
                DomainType::EVENT => ReportEvent::class,
                DomainType::QUERY => ReportQuery::class,
            };
        }

        $tracker = $group->trackerId();

        if (is_string($tracker)) {
            $tracker = $this->container[$tracker];
        }

        return new $concrete($tracker ?? new TrackMessage());
    }

    /**
     * @return array<MessageSubscriber>
     */
    private function makeMessageSubscribers(Group $group, string $reporterClass): array
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

    private function makeRouteSubscriber(Group $group): MessageSubscriber
    {
        $routeLocator = new FindRoute($group, $this->container[MessageAlias::class], $this->container);

        $messageProducer = $this->producerFactory->createMessageProducer($group);

        return new HandleRoute($routeLocator, $messageProducer, $this->container[ProducerUnity::class]);
    }

    private function chainMessageDecorators(Group $group): MessageSubscriber
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
     * Resolve services
     *
     * @param  array<int, string|MessageSubscriber|MessageDecorator>  ...$services
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
