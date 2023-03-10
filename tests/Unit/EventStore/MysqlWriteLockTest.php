<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\EventStore\WriteLock\MysqlWriteLock;

#[CoversClass(MysqlWriteLock::class)]

final class MysqlWriteLockTest extends UnitTestCase
{
    #[DataProvider('provideTableName')]
    #[Test]
    public function it_always_acquire_lock(string $tableName): void
    {
        $writeLock = new MysqlWriteLock();

        $this->assertTrue($writeLock->acquireLock($tableName));
    }

    #[DataProvider('provideTableName')]
    #[Test]
    public function it_always_release_lock(string $tableName): void
    {
        $writeLock = new MysqlWriteLock();

        $this->assertTrue($writeLock->releaseLock($tableName));
    }

    public static function provideTableName(): Generator
    {
        yield [''];
        yield ['customer'];
        yield ['transaction'];
        yield ['transaction-add'];
    }
}
