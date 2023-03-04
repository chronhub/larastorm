<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Chronhub\Larastorm\EventStore\Persistence\EventStream;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;

#[CoversClass(EventStream::class)]
final class EventStreamTest extends OrchestraTestCase
{
    use RefreshDatabase;

    private string $tableName = 'customer';

    private EventStream $eventStream;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventStream = new EventStream();
    }

    protected function getPackageProviders($app): array
    {
        return [ChroniclerServiceProvider::class];
    }

    #[Test]
    public function it_create_event_stream(): void
    {
        $streamName = 'transaction';

        $category = null;

        $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

        $this->eventStream->createStream($streamName, $this->tableName, $category);

        $this->assertTrue($this->eventStream->hasRealStreamName($streamName));

        /** @var EventStream $model */
        $model = $this->eventStream->newQuery()->find(1);

        $this->assertEquals($this->tableName, $model->tableName());

        $this->assertEquals('transaction', $model->realStreamName());

        $this->assertNull($model->category());

        /** @var EventStream $model */
        $model = $this->eventStream->newQuery()->find(1);

        $this->assertFalse($model->timestamps);

        $this->assertEquals('event_streams', $model->getTable());
    }

    #[Test]
    public function it_create_event_stream_with_category(): void
    {
        $streamName = 'add';

        $category = 'transaction';

        $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

        $this->eventStream->createStream($streamName, $this->tableName, $category);

        $this->assertTrue($this->eventStream->hasRealStreamName($streamName));

        /** @var EventStream $model */
        $model = $this->eventStream->newQuery()->find(1);

        $this->assertEquals($this->tableName, $model->tableName());
        $this->assertEquals('add', $model->realStreamName());
        $this->assertEquals('transaction', $model->category());
    }

    #[Test]
    public function it_delete_event_stream_by_stream_name(): void
    {
        $streamName = 'transaction';

        $category = null;

        $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

        $this->eventStream->createStream($streamName, $this->tableName, $category);

        $this->assertTrue($this->eventStream->hasRealStreamName($streamName));

        $deleted = $this->eventStream->deleteStream($streamName);

        $this->assertTrue($deleted);
        $this->assertFalse($this->eventStream->hasRealStreamName($streamName));
    }

    #[Test]
    public function it_filter_and_order_by_stream_names(): void
    {
        $category = null;
        $streamNames = ['transaction_add', 'transaction_subtract', 'transaction_divide'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

            $this->eventStream->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->eventStream->hasRealStreamName($streamName));
        }

        $expectedStreamNames = ['transaction_add', 'transaction_divide', 'transaction_subtract'];

        $this->assertEquals($expectedStreamNames, $this->eventStream->filterByStreams($streamNames));
    }

    #[Test]
    public function it_filter_and_order_by_stream_names_2(): void
    {
        $category = null;
        $streamNames = ['transaction_add', 'transaction_subtract', 'transaction_divide'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

            $this->eventStream->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->eventStream->hasRealStreamName($streamName));
        }

        $streamNames[] = 'foo';
        $streamNames[] = 'bar';

        $expectedStreamNames = ['transaction_add', 'transaction_divide', 'transaction_subtract'];

        $this->assertEquals($expectedStreamNames, $this->eventStream->filterByStreams($streamNames));
    }

    #[Test]
    public function it_filter_and_order_by_stream_names_3(): void
    {
        $category = null;
        $streamNames = ['transaction_add', 'transaction_subtract', 'transaction_divide'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

            $this->eventStream->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->eventStream->hasRealStreamName($streamName));
        }

        $this->eventStream->createStream('foo', 'foo_table', $category);
        $this->eventStream->createStream('bar', 'bar_table', $category);

        $expectedStreamNames = ['transaction_add', 'transaction_divide', 'transaction_subtract'];

        $this->assertEquals($expectedStreamNames, $this->eventStream->filterByStreams($streamNames));
    }

    #[Test]
    public function it_filter_by_categories(): void
    {
        $category = 'transaction';
        $streamNames = ['add', 'subtract', 'divide'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

            $this->eventStream->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->eventStream->hasRealStreamName($streamName));
        }

        $this->eventStream->createStream('operation', $this->tableName);

        $expectedCategories = ['add', 'divide', 'subtract'];

        $this->assertEquals($expectedCategories, $this->eventStream->filterByCategories(['transaction']));
    }

    #[Test]
    public function it_fetch_all_stream_without_internal_stream_beginning_with_dollar_sign(): void
    {
        $category = 'transaction';
        $streamNames = ['add', 'divide', 'subtract'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->eventStream->hasRealStreamName($streamName));

            $this->eventStream->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->eventStream->hasRealStreamName($streamName));
        }

        $this->eventStream->createStream('operation', $this->tableName);

        $this->eventStream->createStream('$all', $this->tableName);

        $expectedCategories = ['add', 'divide', 'subtract'];

        $this->assertEquals($expectedCategories, $this->eventStream->filterByCategories(['transaction']));

        $this->assertEquals($streamNames, $this->eventStream->filterByStreams($streamNames));

        $this->assertEquals([
            'add', 'divide', 'operation', 'subtract',
        ], $this->eventStream->allWithoutInternal());
    }
}
