<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Cqrs;

use Chronhub\Storm\Contracts\Reporter\Reporter;
use Chronhub\Storm\Contracts\Reporter\ReporterManager;
use Chronhub\Storm\Contracts\Routing\Registrar;
use Chronhub\Storm\Reporter\DomainType;
use Chronhub\Storm\Reporter\ReportCommand;
use Chronhub\Storm\Reporter\ReportEvent;
use Chronhub\Storm\Reporter\ReportQuery;
use Chronhub\Storm\Routing\Exceptions\RoutingViolation;
use Chronhub\Storm\Routing\Group;
use Chronhub\Storm\Tracker\TrackMessage;
use Illuminate\Contracts\Container\Container;
use function is_string;
use function sprintf;

final readonly class CqrsManager implements ReporterManager
{
    private Container $container;

    private Registrar $registrar;

    private MessageSubscribersFactory $factory;

    public function __construct(callable $container)
    {
        $this->container = $container();
        $this->registrar = $this->container[Registrar::class];
        $this->factory = new MessageSubscribersFactory($container, new MessageProducerFactory($container));
    }

    public function create(string $type, string $name): Reporter
    {
        $groupType = DomainType::tryFrom($type);

        if (! $groupType instanceof DomainType) {
            throw new RoutingViolation(sprintf('Group type %s is invalid', $type));
        }

        $group = $this->registrar->get($groupType, $name);

        if (! $group instanceof Group) {
            throw new RoutingViolation(sprintf('Group with type %s and name %s not defined', $type, $name));
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
        $reporter = $this->createReporter($group);

        $messageSubscribers = $this->factory->createMessageSubscribers($group, $reporter::class);

        $reporter->subscribe(...$messageSubscribers);

        return $reporter;
    }

    private function createReporter(Group $group): Reporter
    {
        $concrete = $group->reporterConcrete();

        if (null === $concrete) {
            $concrete = match ($group->getType()) {
                DomainType::COMMAND => ReportCommand::class,
                DomainType::EVENT => ReportEvent::class,
                DomainType::QUERY => ReportQuery::class,
            };
        }

        if (is_string($tracker = $group->trackerId())) {
            $tracker = $this->container[$tracker];
        }

        return new $concrete($tracker ?? new TrackMessage());
    }
}
