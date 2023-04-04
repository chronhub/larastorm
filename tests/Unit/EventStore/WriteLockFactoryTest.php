<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\EventStore\WriteLock\LockFactory;
use Chronhub\Larastorm\EventStore\WriteLock\FakeWriteLock;
use Chronhub\Larastorm\EventStore\WriteLock\MysqlWriteLock;
use Chronhub\Larastorm\EventStore\WriteLock\PgsqlWriteLock;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Illuminate\Contracts\Container\Container as ContainerContract;

#[CoversClass(LockFactory::class)]
final class WriteLockFactoryTest extends UnitTestCase
{
    private ContainerContract $container;

    private Connection|MockObject $connection;

    protected function setUp(): void
    {
        $this->container = Container::getInstance();
        $this->connection = $this->createMock(Connection::class);
    }

    public function testReturnFakeWriteLockOnNullConfig(): void
    {
        $factory = new LockFactory($this->container);

        $lock = $factory->createLock($this->connection, null);

        $this->assertInstanceOf(FakeWriteLock::class, $lock);
    }

    public function testReturnFakeWriteLockOnFalseConfig(): void
    {
        $factory = new LockFactory($this->container);

        $lock = $factory->createLock($this->connection, false);

        $this->assertInstanceOf(FakeWriteLock::class, $lock);
    }

    public function testReturnPgsqlWriteLock(): void
    {
        $factory = new LockFactory($this->container);

        $this->connection->expects($this->once())
            ->method('getDriverName')
            ->willReturn('pgsql');

        $lock = $factory->createLock($this->connection, true);

        $this->assertInstanceOf(PgsqlWriteLock::class, $lock);
    }

    public function testReturnMysqlWriteLock(): void
    {
        $factory = new LockFactory($this->container);

        $this->connection->expects($this->once())
            ->method('getDriverName')
            ->willReturn('mysql');

        $lock = $factory->createLock($this->connection, true);

        $this->assertInstanceOf(MysqlWriteLock::class, $lock);
    }

    public function testResolveWriteLockServiceIdFromIoc(): void
    {
        $this->container->bind('foo', fn () => new FakeWriteLock());

        $factory = new LockFactory($this->container);

        $this->connection->expects($this->once())
            ->method('getDriverName')
            ->willReturn('not_used');

        $lock = $factory->createLock($this->connection, 'foo');

        $this->assertInstanceOf(FakeWriteLock::class, $lock);
    }

    public function testExceptionRaisedOnTrueWriteLockConfigAndUnsupportedConnectionDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unavailable write lock strategy for driver mongo');

        $factory = new LockFactory($this->container);

        $this->connection->expects($this->once())
            ->method('getDriverName')
            ->willReturn('mongo');

        $factory->createLock($this->connection, true);
    }
}
