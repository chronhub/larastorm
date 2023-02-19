<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Facade;

use DateTimeImmutable;
use Illuminate\Support\Facades\Facade;
use function is_string;

/**
 * @method static DateTimeImmutable now()
 * @method static string getFormat()
 */
final class Clock extends Facade
{
    public const SERVICE_ID = 'clock.system';

    public static function format(string|DateTimeImmutable $dateTime): string
    {
        if (is_string($dateTime)) {
            return $dateTime;
        }

        $format = self::getFormat();

        return $dateTime->format($format);
    }

    protected static function getFacadeAccessor(): string
    {
        return self::SERVICE_ID;
    }
}
