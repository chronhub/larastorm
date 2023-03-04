<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit;

use Stringable;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Larastorm\Support\UniqueId\UniqueIdV4;

/**
 * @coversDefaultClass \Chronhub\Larastorm\Support\UniqueId\UniqueIdV4
 */
final class UniqueIdV4Test extends UnitTestCase
{
    #[Test]
    public function it_generate_unique_id_as_string(): void
    {
        $instance = new UniqueIdV4();

        $uuid = $instance->generate();

        $this->assertTrue(Uuid::isValid($uuid));

        $this->assertInstanceOf(UuidV4::class, Uuid::fromString($uuid));
    }

    #[Test]
    public function it_cast_unique_id_to_string(): void
    {
        $instance = new UniqueIdV4();

        $this->assertInstanceOf(Stringable::class, $instance);

        $uuid = (string) $instance;

        $this->assertTrue(Uuid::isValid($uuid));

        $this->assertInstanceOf(UuidV4::class, Uuid::fromString($uuid));
    }
}
