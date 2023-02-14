<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Util;

use ReflectionClass;

final class ReflectionProperty
{
    public static function getProperty($object, $property)
    {
        $reflectedClass = new ReflectionClass($object);

        $reflection = $reflectedClass->getProperty($property);

        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }
}
