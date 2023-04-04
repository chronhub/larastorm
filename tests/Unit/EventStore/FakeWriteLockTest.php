<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\EventStore\WriteLock\FakeWriteLock;

#[CoversClass(FakeWriteLock::class)]
final class FakeWriteLockTest extends UnitTestCase
{
    #[DataProvider('provideTableName')]
    public function testAlwaysAcquireLock(string $tableName): void
    {
        $writeLock = new FakeWriteLock();

        $this->assertTrue($writeLock->acquireLock($tableName));
    }

    #[DataProvider('provideTableName')]
    public function testAlwaysReleaseLock(string $tableName): void
    {
        $writeLock = new FakeWriteLock();

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
