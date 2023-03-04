<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Support\Supervisor;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Chronhub\Larastorm\Support\Supervisor\Supervisor
 */
final class SupervisorTest extends UnitTestCase
{
    #[Test]
    public function it_assert_supervisor(): void
    {
        $commands = collect(['foo' => 'bar']);
        $supervisor = new SupervisorStub($commands);

        $this->assertSame($commands, $supervisor->getCommands());
        $this->assertEquals(['bar'], $supervisor->getNames());
        $this->assertFalse($supervisor->isWorking());
        $this->assertTrue($supervisor->isFirstCheck());
        $this->assertFalse($supervisor->isSupervisorRunning());
        $this->assertEquals('projector:supervisor-start', $supervisor->signature);
        $this->assertEquals('project', $supervisor->namespace);
    }

    #[Test]
    public function it_raise_exception_when_commands_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No commands given.');

        new SupervisorStub(collect());
    }
}
