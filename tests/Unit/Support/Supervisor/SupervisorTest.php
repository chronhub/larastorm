<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Support\Supervisor;

use Chronhub\Larastorm\Support\Supervisor\Supervisor;
use Chronhub\Larastorm\Tests\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Supervisor::class)]
final class SupervisorTest extends UnitTestCase
{
    public function testSupervisorInstance(): void
    {
        $commands = collect(['foo' => 'bar']);
        $supervisor = new SupervisorStub($commands);

        $this->assertSame($commands, $supervisor->getCommands());
        $this->assertEquals(['bar'], $supervisor->getNames());
        $this->assertFalse($supervisor->isWorking());
        $this->assertTrue($supervisor->isFirstCheck());
        $this->assertEquals('projector:supervisor-start', $supervisor->signature);
        $this->assertEquals('project', $supervisor->namespace);
    }

    public function testExceptionRaisedWhenCommandsAreEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No commands given.');

        new SupervisorStub(collect());
    }
}
