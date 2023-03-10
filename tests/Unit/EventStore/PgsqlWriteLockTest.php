<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\EventStore\WriteLock\PgsqlWriteLock;

#[CoversClass(PgsqlWriteLock::class)]
final class PgsqlWriteLockTest extends UnitTestCase
{
    #[DataProvider('provideBoolean')]
    #[Test]
    public function it_acquire_lock(bool $isLocked): void
    {
        $tableName = 'operation';
        $lockName = '_'.$tableName.'_write_lock';

        $connection = $this->createMock(ConnectionInterface::class);

        $connection
            ->expects($this->once())
            ->method('statement')
            ->with('select pg_advisory_lock( hashtext(\''.$lockName.'\') )')
            ->willReturn($isLocked);

        $lock = new PgsqlWriteLock($connection);

        $this->assertSame($isLocked, $lock->acquireLock($tableName));
    }

    #[DataProvider('provideBoolean')]
    #[Test]
    public function it_release_lock(bool $isReleased): void
    {
        $tableName = 'operation';
        $lockName = '_'.$tableName.'_write_lock';

        $connection = $this->createMock(ConnectionInterface::class);
        $connection
            ->expects($this->once())
            ->method('statement')
            ->with('select pg_advisory_unlock( hashtext(\''.$lockName.'\') )')
            ->willReturn($isReleased);

        $lock = new PgsqlWriteLock($connection);

        $this->assertSame($isReleased, $lock->releaseLock($tableName));
    }

    public static function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }
}
