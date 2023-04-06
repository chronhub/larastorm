<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Larastorm\EventStore\WriteLock\PgsqlWriteLock;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Generator;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(PgsqlWriteLock::class)]
final class PgsqlWriteLockTest extends UnitTestCase
{
    #[DataProvider('provideBoolean')]
    public function testAcquireLock(bool $isLocked): void
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
    public function testReleaseLock(bool $isReleased): void
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
