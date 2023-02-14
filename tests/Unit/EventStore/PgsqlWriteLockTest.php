<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Illuminate\Database\ConnectionInterface;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Larastorm\EventStore\WriteLock\PgsqlWriteLock;

final class PgsqlWriteLockTest extends ProphecyTestCase
{
    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_acquire_lock(bool $isLocked): void
    {
        $tableName = 'operation';
        $lockName = '_'.$tableName.'_write_lock';

        $connection = $this->prophesize(ConnectionInterface::class);

        $lock = new PgsqlWriteLock($connection->reveal());

        $connection
            ->statement('select pg_advisory_lock( hashtext(\''.$lockName.'\') )')
            ->shouldBeCalledOnce()
            ->willReturn($isLocked);

        $this->assertSame($isLocked, $lock->acquireLock($tableName));
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_release_lock(bool $isReleased): void
    {
        $tableName = 'operation';
        $lockName = '_'.$tableName.'_write_lock';

        $connection = $this->prophesize(ConnectionInterface::class);

        $lock = new PgsqlWriteLock($connection->reveal());

        $connection
            ->statement('select pg_advisory_unlock( hashtext(\''.$lockName.'\') )')
            ->shouldBeCalledOnce()
            ->willReturn($isReleased);

        $this->assertSame($isReleased, $lock->releaseLock($tableName));
    }

    public function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }
}
