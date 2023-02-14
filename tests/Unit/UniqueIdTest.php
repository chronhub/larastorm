<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit;

use Stringable;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Larastorm\Support\UniqueId\UniqueId;

final class UniqueIdTest extends UnitTestCase
{
    /**
     * @test
     */
    public function it_create_unique_id_instance(): void
    {
        $this->assertInstanceOf(UuidV4::class, UniqueId::create());
    }

    /**
     * @test
     */
    public function it_generate_unique_id_as_string(): void
    {
        $instance = new UniqueId();

        $uuid = $instance->generate();

        $this->assertTrue(Uuid::isValid($uuid));

        $this->assertInstanceOf(UuidV4::class, Uuid::fromString($uuid));
    }

    /**
     * @test
     */
    public function it_generate_unique_id_as_string_2(): void
    {
        $instance = new UniqueId();

        $this->assertInstanceOf(Stringable::class, $instance);

        $uuid = (string) $instance;

        $this->assertTrue(Uuid::isValid($uuid));

        $this->assertInstanceOf(UuidV4::class, Uuid::fromString($uuid));
    }
}
