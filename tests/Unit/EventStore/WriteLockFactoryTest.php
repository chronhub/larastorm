<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Larastorm\EventStore\WriteLock\FakeWriteLock;
use Chronhub\Larastorm\EventStore\WriteLock\MysqlWriteLock;
use Chronhub\Larastorm\EventStore\WriteLock\PgsqlWriteLock;
use Chronhub\Larastorm\EventStore\WriteLock\WriteLockFactory;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Illuminate\Contracts\Container\Container as ContainerContract;

final class WriteLockFactoryTest extends ProphecyTestCase
{
    private ContainerContract $container;

    private Connection|ObjectProphecy $connection;

    protected function setUp(): void
    {
        $this->container = Container::getInstance();
        $this->connection = $this->prophesize(Connection::class);
    }

    /**
     * @test
     */
    public function it_raise_exception_when_write_lock_key_is_null_in_configuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Write lock is not defined');

        $factory = new WriteLockFactory($this->container);

        $factory($this->connection->reveal(), []);
    }

    /**
     * @test
     */
    public function it_return_fake_write_lock_on_false_write_lock_key(): void
    {
        $factory = new WriteLockFactory($this->container);

        $writeLock = $factory($this->connection->reveal(), [
            'write_lock' => false,
        ]);

        $this->assertInstanceOf(FakeWriteLock::class, $writeLock);
    }

    /**
     * @test
     */
    public function it_return_pgsql_write_lock_on_true_write_lock_key_and_connection_driver(): void
    {
        $factory = new WriteLockFactory($this->container);

        $this->connection->getDriverName()->willReturn('pgsql')->shouldBeCalledOnce();

        $writeLock = $factory($this->connection->reveal(), [
            'write_lock' => true,
        ]);

        $this->assertInstanceOf(PgsqlWriteLock::class, $writeLock);
    }

    /**
     * @test
     */
    public function it_return_mysql_write_lock_on_true_write_lock_key_and_connection_driver(): void
    {
        $factory = new WriteLockFactory($this->container);

        $this->connection->getDriverName()->willReturn('mysql')->shouldBeCalledOnce();

        $writeLock = $factory($this->connection->reveal(), [
            'write_lock' => true,
        ]);

        $this->assertInstanceOf(MysqlWriteLock::class, $writeLock);
    }

    /**
     * @test
     */
    public function it_resolve_string_write_lock_from_container(): void
    {
        $this->container->bind('foo', fn () => new FakeWriteLock());

        $factory = new WriteLockFactory($this->container);

        $this->connection->getDriverName()->willReturn('nope')->shouldBeCalledOnce();

        $writeLock = $factory($this->connection->reveal(), [
            'write_lock' => 'foo',
        ]);

        $this->assertInstanceOf(FakeWriteLock::class, $writeLock);
    }

    /**
     * @test
     */
    public function it_raise_exception_on_true_write_lock_key_and_unsupported_connection_driver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unavailable write lock strategy for driver mongo');

        $factory = new WriteLockFactory($this->container);

        $this->connection->getDriverName()->willReturn('mongo')->shouldBeCalledOnce();

        $factory($this->connection->reveal(), ['write_lock' => true]);
    }
}
