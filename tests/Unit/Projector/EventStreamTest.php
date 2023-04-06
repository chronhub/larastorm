<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use Chronhub\Larastorm\EventStore\Persistence\EventStream;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(EventStream::class)]
class EventStreamTest extends UnitTestCase
{
    #[Test]
    public function it_assert_default_instance(): void
    {
        $model = new EventStream('stream_name', 'table_name');

        $this->assertEquals('stream_name', $model->realStreamName());
        $this->assertEquals('table_name', $model->tableName());
        $this->assertNull($model->category());
    }

    #[Test]
    public function it_assert_instance(): void
    {
        $model = new EventStream('stream_name', 'table_name', 'category');

        $this->assertEquals('stream_name', $model->realStreamName());
        $this->assertEquals('table_name', $model->tableName());
        $this->assertEquals('category', $model->category());
    }

    #[Test]
    public function it_can_be_serialized(): void
    {
        $model = new EventStream('stream_name', 'table_name', 'category');

        $this->assertEquals([
            'real_stream_name' => 'stream_name',
            'stream_name' => 'table_name',
            'category' => 'category',
        ], $model->jsonSerialize());
    }
}
