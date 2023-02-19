<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Storm\Contracts\Serializer\StreamEventConverter;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Chronhub\Larastorm\EventStore\Persistence\StreamPersistenceFactory;
use Chronhub\Larastorm\EventStore\Persistence\PgsqlSingleStreamPersistence;
use Chronhub\Larastorm\EventStore\Persistence\PerAggregateStreamPersistence;

final class StreamPersistenceFactoryTest extends ProphecyTestCase
{
    private ContainerContract $container;

    private Connection|ObjectProphecy $connection;

    protected function setUp(): void
    {
        $this->container = Container::getInstance();
        $this->container->bind(StreamEventConverter::class, function (): StreamEventConverter {
            return $this->prophesize(StreamEventConverter::class)->reveal();
        });
        $this->connection = $this->prophesize(Connection::class);
    }

    /**
     * @test
     */
    public function it_return_single_stream_persistence_instance_for_single_key(): void
    {
        $factory = new StreamPersistenceFactory($this->container);

        $instance = $factory('write', $this->connection->reveal(), 'single');

        $this->assertInstanceOf(PgsqlSingleStreamPersistence::class, $instance);
    }

    /**
     * @test
     */
    public function it_return_per_aggregate_stream_persistence_instance_for_per_aggregate_key(): void
    {
        $factory = new StreamPersistenceFactory($this->container);

        $instance = $factory('write', $this->connection->reveal(), 'per_aggregate');

        $this->assertInstanceOf(PerAggregateStreamPersistence::class, $instance);
    }

    /**
     * @test
     */
    public function it_resolve_stream_persistence_key_through_container(): void
    {
        $expectedInstance = $this->prophesize(StreamPersistence::class)->reveal();

        $this->container->instance('stream_persistence.default', $expectedInstance);

        $factory = new StreamPersistenceFactory($this->container);

        $instance = $factory('write', $this->connection->reveal(), 'stream_persistence.default');

        $this->assertSame($expectedInstance, $instance);
    }

    /**
     * @test
     */
    public function it_raise_exception_with_single_indexed_key_with_a_mysql_connection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream persistence single_indexed is only available for mysql');

        $this->connection->getDriverName()->willReturn('pgsql')->shouldBeCalledOnce();

        $factory = new StreamPersistenceFactory($this->container);

        $factory('read', $this->connection->reveal(), 'single_indexed');
    }

    /**
     * @test
     */
    public function it_raise_exception_with_null_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid persistence strategy for chronicler read');

        $factory = new StreamPersistenceFactory($this->container);

        $factory('read', $this->connection->reveal(), null);
    }
}
