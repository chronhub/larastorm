<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Database\Connection;
use Psr\Container\ContainerInterface;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Tracker\StreamTracker;
use Chronhub\Storm\Chronicler\AbstractChroniclerProvider;
use Chronhub\Storm\Contracts\Chronicler\EventableChronicler;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerConnection;
use Chronhub\Storm\Contracts\Tracker\TransactionalStreamTracker;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use function ucfirst;
use function method_exists;

final class EventStoreConnectionProvider extends AbstractChroniclerProvider
{
    protected ContainerInterface|Container $container;

    public function __construct(Closure $container,
                                private readonly EventStoreProviderFactory $providerFactory)
    {
        parent::__construct($container);

        /** @phpstan-ignore-next-line  */
        $this->providerFactory->setContainer($this->container);
    }

    public function resolve(string $name, array $config): Chronicler
    {
        $chronicler = $this->make($name, $config);

        if ($chronicler instanceof EventableChronicler) {
            $this->attachStreamSubscribers($chronicler, $config['tracking']['subscribers'] ?? []);
        }

        return $chronicler;
    }

    private function make(string $name, array $config): Chronicler
    {
        [$streamTracker, $driver, $isTransactional] = $this->determineStorage($name, $config);

        $driverMethod = 'create'.ucfirst(Str::camel($driver.'Driver'));

        /**
         * @covers createMysqlDriver
         * @covers createPgsqlDriver
         */
        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($name, $config, $isTransactional, $streamTracker);
        }

        throw new InvalidArgumentException("Connection $name with provider $driver is not defined.");
    }

    private function createPgsqlDriver(string $name,
                                       array $config,
                                       bool $isTransactional,
                                       ?StreamTracker $streamTracker): ChroniclerConnection|EventableChronicler
    {
        /** @var Connection $connection */
        $connection = $this->container['db']->connection('pgsql');

        $standalone = ($this->providerFactory)($connection, $name, $config, $isTransactional);

        $chronicler = $isTransactional ? new PgsqlTransactionalEventStore($standalone) : new PgsqlEventStore($standalone);

        return $streamTracker ? $this->decorateChronicler($chronicler, $streamTracker) : $chronicler;
    }

    private function createMysqlDriver(string $name,
                                       array $config,
                                       bool $isTransactional,
                                       ?StreamTracker $streamTracker): ChroniclerConnection|EventableChronicler
    {
        /** @var Connection $connection */
        $connection = $this->container['db']->connection('mysql');

        $standalone = ($this->providerFactory)($connection, $name, $config, $isTransactional);

        $chronicler = $isTransactional
            ? new MysqlTransactionalEventStore($standalone)
            : new MysqlEventStore($standalone);

        return $streamTracker ? $this->decorateChronicler($chronicler, $streamTracker) : $chronicler;
    }

    private function determineStorage(string $name, array $config): array
    {
        $streamTracker = $this->resolveStreamTracker($config);

        $isTransactional = $config['is_transactional'] ?? null;

        if (null === $isTransactional && ! $streamTracker instanceof StreamTracker) {
            throw new InvalidArgumentException("Unable to resolve chronicler name $name, missing is_transactional key in config");
        }

        $isTransactional = $streamTracker instanceof TransactionalStreamTracker;

        return [$streamTracker, $config['store'], $isTransactional];
    }
}
