<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional;

use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Larastorm\Support\Facade\Clock;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Clock\PointInTime;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Clock::class)]
final class ClockFacadeTest extends OrchestraTestCase
{
    public function testClock(): void
    {
        $clock = Clock::getFacadeRoot();

        $this->assertEquals(PointInTime::class, $clock::class);
        $this->assertEquals('clock.system', Clock::SERVICE_ID);
    }

    public function testNow(): void
    {
        $clock = Clock::now();

        $this->assertInstanceOf(DateTimeImmutable::class, $clock);
        $this->assertEquals('UTC', $clock->getTimezone()->getName());
    }

    public function testGetFormat(): void
    {
        $clockFormat = Clock::getFormat();

        $this->assertEquals(PointInTime::DATE_TIME_FORMAT, $clockFormat);
    }

    public function testExceptionRaisedWithInvalidDatetime(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid date time format: some_datetime');

        Clock::format('some_datetime');
    }

    public function testFormatFromDateTimeImmutable(): void
    {
        $now = Clock::now();

        $nowString = $now->format(PointInTime::DATE_TIME_FORMAT);

        $clockString = Clock::format($now);

        $this->assertEquals($nowString, $clockString);
    }

    public function testNormalizeFromDateTimeImmutable(): void
    {
        $clockString = Clock::format(new DateTimeImmutable('2023-02-19T15', new DateTimeZone('UTC')));

        $this->assertEquals('2023-02-19T15:00:00.000000', $clockString);
    }

    protected function getPackageProviders($app): array
    {
        return [ClockServiceProvider::class];
    }
}
