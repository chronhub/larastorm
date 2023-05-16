<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Facade;

use DateInterval;
use DateTimeImmutable;
use Illuminate\Support\Facades\Facade;

/**
 * @method static DateTimeImmutable now()
 * @method static string            nowToString()
 * @method static DateTimeImmutable toDateTimeImmutable(string|DateTimeImmutable $pointInTime)
 * @method static string            format(string|DateTimeImmutable $pointInTime)
 * @method static string            getFormat()
 * @method static void              sleep(float|int $seconds)
 * @method static bool              isGreaterThan(string|DateTimeImmutable $pointInTime, string|DateTimeImmutable $anotherPointInTime)
 * @method static bool              isGreaterThanNow(string|DateTimeImmutable $pointInTime)
 * @method static bool              isNowSubGreaterThan(string|DateInterval $interval, string|DateTimeImmutable $pointInTime)
 */
final class Clock extends Facade
{
    public const SERVICE_ID = 'clock.system';

    protected static function getFacadeAccessor(): string
    {
        return self::SERVICE_ID;
    }
}
