<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Facade;

use DateInterval;
use DateTimeImmutable;
use Illuminate\Support\Facades\Facade;

/**
 * @method static DateTimeImmutable now()
 * @method static string format(string|DateTimeImmutable $pointInTime)
 * @method static string toDateTimeImmutable(string|DateTimeImmutable $pointInTime): DateTimeImmutable
 * @method static string getFormat()
 * @method static void sleep(float|int $seconds)
 * @method static bool isGreaterThan(DateTimeImmutable|string $pointInTime, DateTimeImmutable|string $anotherPointInTime)
 * @method static bool isGreaterThanNow(string|DateTimeImmutable $pointInTime)
 * @method static bool isNowSubGreaterThan(string|DateInterval $interval, string|DateTimeImmutable $pointInTime)
 */
final class Clock extends Facade
{
    public const SERVICE_ID = 'clock.system';

    protected static function getFacadeAccessor(): string
    {
        return self::SERVICE_ID;
    }
}
