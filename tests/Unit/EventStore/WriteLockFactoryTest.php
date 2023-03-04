<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function it_return_fake_write_lock_on_null(): void
    {
        $factory = new LockFactory($this->container);

        $lock = $factory->createLock($this->connection, null);

        $this->assertInstanceOf(FakeWriteLock::class, $lock);
    }

    #[Test]
    public function it_return_fake_write_lock_on_false(): void
    {
        $factory = new LockFactory($this->container);

        $lock = $factory->createLock($this->connection, false);

        $this->assertInstanceOf(FakeWriteLock::class, $lock);
    }

    #[Test]
    public function it_return_pgsql_write_lock_on_true_write_lock_key_and_connection_driver(): void
    {
        $factory = new LockFactory($this->container);

        $this->connection->expects($this->once())
            ->method('getDriverName')
            ->willReturn('pgsql');

        $lock = $factory->createLock($this->connection, true);

        $this->assertInstanceOf(PgsqlWriteLock::class, $lock);
    }

    #[Test]
    public function it_return_mysql_write_lock_on_true_write_lock_key_and_connection_driver(): void
    {
        $factory = new LockFactory($this->container);

        $this->connection->expects($this->once())
            ->method('getDriverName')
            ->willReturn('mysql');

        $lock = $factory->createLock($this->connection, true);

        $this->assertInstanceOf(MysqlWriteLock::class, $lock);
    }

    #[Test]
    public function it_resolve_string_write_lock_from_container(): void
    {
        $this->container->bind('foo', fn () => new FakeWriteLock());

        $factory = new LockFactory($this->container);

        $this->connection->expects($this->once())
            ->method('getDriverName')
            ->willReturn('not_used');

        $lock = $factory->createLock($this->connection, 'foo');

        $this->assertInstanceOf(FakeWriteLock::class, $lock);
    }

    #[Test]
    public function it_raise_exception_on_true_write_lock_key_and_unsupported_connection_driver(): void
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
