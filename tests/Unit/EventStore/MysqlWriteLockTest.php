<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Larastorm\EventStore\WriteLock\MysqlWriteLock;

final class MysqlWriteLockTest extends UnitTestCase
{
    /**
     * @test
     *
     * @dataProvider provideTableName
     */
    public function it_always_acquire_lock(string $tableName): void
    {
        $writeLock = new MysqlWriteLock();

        $this->assertTrue($writeLock->acquireLock($tableName));
    }

    /**
     * @test
     *
     * @dataProvider provideTableName
     */
    public function it_always_release_lock(string $tableName): void
    {
        $writeLock = new MysqlWriteLock();

        $this->assertTrue($writeLock->releaseLock($tableName));
    }

    public function provideTableName(): Generator
    {
        yield [''];
        yield ['customer'];
        yield ['transaction'];
        yield ['transaction-add'];
    }
}
