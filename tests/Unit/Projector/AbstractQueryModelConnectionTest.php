<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use Chronhub\Larastorm\Tests\Stubs\QueryModelConnectionStub;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use PHPUnit\Framework\MockObject\MockObject;

final class AbstractQueryModelConnectionTest extends UnitTestCase
{
    private QueryModelConnectionStub $stub;

    private Connection|MockObject $connection;

    private Builder|MockObject $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->createMock(Connection::class);
        $this->builder = $this->createMock(Builder::class);
        $this->stub = new QueryModelConnectionStub($this->connection);
    }

    public function testInsert(): void
    {
        $this->assertConnection();

        $this->builder->expects($this->once())->method('insert')->with(['foo' => 'bar']);

        $this->stub->call(fn ($stub) => $stub->insert(['foo' => 'bar']));
    }

    public function testUpdate(): void
    {
        $this->assertConnection();

        $this->builder->expects($this->once())->method('where')->with('id')->willReturn($this->builder);
        $this->builder->expects($this->once())->method('update')->with(['foo' => 'bar'])->willReturn($this->builder);

        $this->stub->call(fn ($stub) => $stub->update('id', ['foo' => 'bar']));
    }

    public function testDelete(): void
    {
        $this->assertConnection();

        $this->builder->expects($this->once())->method('where')->with('id')->willReturn($this->builder);
        $this->builder->expects($this->once())->method('delete')->willReturn($this->builder);

        $this->stub->call(fn ($stub) => $stub->delete('id'));
    }

    public function testIncrement(): void
    {
        $this->assertConnection();

        $this->builder->expects($this->once())->method('where')->with('id')->willReturn($this->builder);
        $this->builder->expects($this->once())->method('increment')->with('foo', 1, ['bar' => 'baz'])->willReturn($this->builder);

        $this->stub->call(fn ($stub) => $stub->increment('id', 'foo', 1, ['bar' => 'baz']));
    }

    public function testDecrement(): void
    {
        $this->assertConnection();

        $this->builder->expects($this->once())->method('where')->with('id')->willReturn($this->builder);
        $this->builder->expects($this->once())->method('decrement')->with('foo', 1, ['bar' => 'baz'])->willReturn($this->builder);

        $this->stub->call(fn ($stub) => $stub->decrement('id', 'foo', 1, ['bar' => 'baz']));
    }

    public function testDecrementEach(): void
    {
        $this->assertConnection();

        $this->builder->expects($this->once())->method('where')->with('id')->willReturn($this->builder);
        $this->builder->expects($this->once())->method('decrementEach')->with([
            'bar' => 1,
            'foo' => 2,
        ], ['bar' => 'baz'])->willReturn($this->builder);

        $this->stub->call(fn ($stub) => $stub->decrementEach('id', ['bar' => 1, 'foo' => 2], ['bar' => 'baz']));
    }

    public function testIncrementEach(): void
    {
        $this->assertConnection();

        $this->builder->expects($this->once())->method('where')->with('id')->willReturn($this->builder);
        $this->builder->expects($this->once())->method('incrementEach')->with([
            'bar' => 1,
            'foo' => 2,
        ], ['bar' => 'baz'])->willReturn($this->builder);

        $this->stub->call(fn ($stub) => $stub->incrementEach('id', ['bar' => 1, 'foo' => 2], ['bar' => 'baz']));
    }

    public function testDecrementWillAbsoluteAmount(): void
    {
        $this->assertConnection();

        $this->builder->expects($this->once())->method('where')->with('id')->willReturn($this->builder);
        $this->builder->expects($this->once())->method('decrement')->with('foo', 1, ['bar' => 'baz'])->willReturn($this->builder);

        $this->stub->call(fn ($stub) => $stub->decrement('id', 'foo', -1, ['bar' => 'baz']));
    }

    public function testGetKey(): void
    {
        $this->assertSame('id', $this->stub->call(fn ($stub) => $stub->getKey()));
    }

    public function testUp(): void
    {
        $callback = $this->stub->call(fn ($stub) => $stub->up());

        $this->assertTrue($callback());
    }

    private function assertConnection(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('table')
            ->with('read_foo')
            ->willReturn($this->builder);
    }
}
