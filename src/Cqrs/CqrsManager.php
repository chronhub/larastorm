<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Cqrs;

use Closure;
use Illuminate\Support\Arr;
use Chronhub\Storm\Routing\Group;
use Chronhub\Storm\Routing\FindRoute;
use Chronhub\Storm\Reporter\DomainType;
use Chronhub\Storm\Routing\HandleRoute;
use Chronhub\Storm\Reporter\ReportEvent;
use Chronhub\Storm\Reporter\ReportQuery;
use Chronhub\Storm\Tracker\TrackMessage;
use Chronhub\Storm\Reporter\ReportCommand;
use Chronhub\Storm\Contracts\Reporter\Reporter;
use Chronhub\Storm\Contracts\Routing\Registrar;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Storm\Message\ChainMessageDecorator;
use Chronhub\Storm\Contracts\Message\MessageAlias;
use Chronhub\Storm\Contracts\Producer\ProducerUnity;
use Chronhub\Storm\Producer\ProducerMessageDecorator;
use Chronhub\Storm\Contracts\Message\MessageDecorator;
use Chronhub\Storm\Contracts\Reporter\ReporterManager;
use Chronhub\Storm\Contracts\Tracker\MessageSubscriber;
use Chronhub\Storm\Routing\Exceptions\RoutingViolation;
use Chronhub\Storm\Reporter\Subscribers\NameReporterService;
use Chronhub\Larastorm\Support\MessageDecorator\DecorateMessage;
use function array_map;
use function is_string;

final class CqrsManager implements ReporterManager
{
    private Application $app;

    private Registrar $registrar;

    public function __construct(Closure $app)
    {
        $this->app = $app();
        $this->registrar = $this->app[Registrar::class];
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
            $tracker = $this->app[$tracker];
        }

        return new $concrete($tracker ?? new TrackMessage());
    }

    /**
     * @return array<MessageSubscriber>
     */
    private function makeMessageSubscribers(Group $group, string $reporterClass): array
    {
        $reporterServiceId = $group->reporterServiceId() ?? $group->reporterConcrete() ?? $reporterClass;

        return $this->resolveServices([
            new NameReporterService($reporterServiceId),
            $this->app['config']->get('messager.subscribers', []),
            $group->messageSubscribers(),
            $this->chainMessageDecorators($group),
            $this->makeRouteSubscriber($group),
        ]);
    }

    private function makeRouteSubscriber(Group $group): MessageSubscriber
    {
        $routeLocator = new FindRoute($group, $this->app[MessageAlias::class], $this->app);

        $messageProducer = (new ProducerFactory($this->app))($group);

        return new HandleRoute($routeLocator, $messageProducer, $this->app[ProducerUnity::class]);
    }

    private function chainMessageDecorators(Group $group): MessageSubscriber
    {
        $strategy = $group->producerStrategy();

        $messageDecorators = [
            new ProducerMessageDecorator($strategy),
            $this->app['config']->get('messager.decorators', []),
            $group->messageDecorators(),
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
        return array_map(function ($service) {
            return is_string($service) ? $this->app[$service] : $service;
        }, Arr::flatten($services));
    }
}
