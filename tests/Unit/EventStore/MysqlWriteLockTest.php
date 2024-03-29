<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Larastorm\EventStore\WriteLock\MysqlWriteLock;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(MysqlWriteLock::class)]
final class MysqlWriteLockTest extends UnitTestCase
{
    #[DataProvider('provideTableName')]
    public function testAlwaysAcquireLock(string $tableName): void
    {
        $writeLock = new MysqlWriteLock();

        $this->assertTrue($writeLock->acquireLock($tableName));
    }

    #[DataProvider('provideTableName')]
    public function testAlwaysReleaseLock(string $tableName): void
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
