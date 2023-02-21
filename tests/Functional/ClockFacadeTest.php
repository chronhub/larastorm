<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional;

use DateTimeZone;
use DateTimeImmutable;
use Chronhub\Storm\Clock\PointInTime;
use Chronhub\Larastorm\Support\Facade\Clock;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;

final class ClockFacadeTest extends OrchestraTestCase
{
    /**
     * @test
     */
    public function it_assert_clock(): void
    {
        $clock = Clock::getFacadeRoot();

        $this->assertEquals(PointInTime::class, $clock::class);
        $this->assertEquals('clock.system', Clock::SERVICE_ID);
    }

    /**
     * @test
     */
    public function it_assert_now(): void
    {
        $clock = Clock::now();

        $this->assertInstanceOf(DateTimeImmutable::class, $clock);
        $this->assertEquals('UTC', $clock->getTimezone()->getName());
    }

    /**
     * @test
     */
    public function it_assert_get_format(): void
    {
        $clockFormat = Clock::getFormat();

        $this->assertEquals(PointInTime::DATE_TIME_FORMAT, $clockFormat);
    }

    /**
     * @test
     */
    public function it_test_format_datetime_from_string(): void
    {
        $timeString = Clock::format('some_datetime');

        $this->assertEquals('some_datetime', $timeString);
    }

    /**
     * @test
     */
    public function it_test_format_datetime_from_date_time_immutable(): void
    {
        $now = Clock::now();

        $nowString = $now->format(PointInTime::DATE_TIME_FORMAT);

        $clockString = Clock::format($now);

        $this->assertEquals($nowString, $clockString);
    }

    /**
     * @test
     */
    public function it_test_format_datetime_from_date_time_immutable_2(): void
    {
        $clockString = Clock::format(new DateTimeImmutable('2023-02-19T15:43:45.482919', new DateTimeZone('UTC')));

        $this->assertEquals('2023-02-19T15:43:45.482919', $clockString);
    }

    protected function getPackageProviders($app): array
    {
        return [MessagerServiceProvider::class];
    }
}