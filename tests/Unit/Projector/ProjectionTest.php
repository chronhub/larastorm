<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use JsonSerializable;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Larastorm\Projection\Projection;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Storm\Contracts\Projector\ProjectionModel;

#[CoversClass(Projection::class)]
class ProjectionTest extends UnitTestCase
{
    #[Test]
    public function it_create_projection(): void
    {
        $projection = new Projection('balance', 'idle', '{}', '{}', null);

        $this->assertInstanceOf(ProjectionModel::class, $projection);
        $this->assertInstanceOf(JsonSerializable::class, $projection);

        $this->assertEquals('balance', $projection->name());
        $this->assertEquals('idle', $projection->status());
        $this->assertEquals('{}', $projection->position());
        $this->assertEquals('{}', $projection->state());
        $this->assertNull($projection->lockedUntil());
    }

    #[Test]
    public function it_can_be_serialize(): void
    {
        $projection = new Projection('balance', 'running', '{"account":125}', '{"count":125}', 'datetime');

        $this->assertEquals([
            'name' => 'balance',
            'status' => 'running',
            'position' => '{"account":125}',
            'state' => '{"count":125}',
            'locked_until' => 'datetime',
        ], $projection->jsonSerialize());
    }
}
