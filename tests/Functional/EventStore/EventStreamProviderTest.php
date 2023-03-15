<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Chronhub\Storm\Contracts\Chronicler\EventStreamModel;
use Chronhub\Larastorm\EventStore\Persistence\EventStream;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\EventStore\Persistence\EventStreamProvider;

#[CoversClass(EventStreamProvider::class)]
final class EventStreamProviderTest extends OrchestraTestCase
{
    use RefreshDatabase;

    private string $tableName = 'customer';

    private EventStreamProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new EventStreamProvider($this->app['db']->connection());
        $this->assertEquals('event_streams', $this->provider::TABLE_NAME);
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

        $this->assertFalse($this->provider->hasRealStreamName($streamName));

        $this->provider->createStream($streamName, $this->tableName, $category);

        $this->assertTrue($this->provider->hasRealStreamName($streamName));
    }

    #[Test]
    public function it_create_event_stream_with_category(): void
    {
        $streamName = 'add';

        $category = 'transaction';

        $this->assertFalse($this->provider->hasRealStreamName($streamName));

        $this->provider->createStream($streamName, $this->tableName, $category);

        $this->assertTrue($this->provider->hasRealStreamName($streamName));

        $model = $this->retrieveByStreamName($streamName);

        $this->assertEquals($this->tableName, $model->tableName());
        $this->assertEquals('add', $model->realStreamName());
        $this->assertEquals('transaction', $model->category());
    }

    #[Test]
    public function it_delete_event_stream_by_stream_name(): void
    {
        $streamName = 'transaction';

        $category = null;

        $this->assertFalse($this->provider->hasRealStreamName($streamName));

        $this->provider->createStream($streamName, $this->tableName, $category);

        $this->assertTrue($this->provider->hasRealStreamName($streamName));

        $deleted = $this->provider->deleteStream($streamName);

        $this->assertTrue($deleted);
        $this->assertFalse($this->provider->hasRealStreamName($streamName));
    }

    #[Test]
    public function it_filter_and_order_by_stream_names(): void
    {
        $category = null;
        $streamNames = ['transaction_add', 'transaction_subtract', 'transaction_divide'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->provider->hasRealStreamName($streamName));

            $this->provider->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->provider->hasRealStreamName($streamName));
        }

        $expectedStreamNames = ['transaction_add', 'transaction_divide', 'transaction_subtract'];

        $this->assertEquals($expectedStreamNames, $this->provider->filterByStreams($streamNames));
    }

    #[Test]
    public function it_filter_and_order_by_stream_names_2(): void
    {
        $category = null;
        $streamNames = ['transaction_add', 'transaction_subtract', 'transaction_divide'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->provider->hasRealStreamName($streamName));

            $this->provider->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->provider->hasRealStreamName($streamName));
        }

        $streamNames[] = 'foo';
        $streamNames[] = 'bar';

        $expectedStreamNames = ['transaction_add', 'transaction_divide', 'transaction_subtract'];

        $this->assertEquals($expectedStreamNames, $this->provider->filterByStreams($streamNames));
    }

    #[Test]
    public function it_filter_and_order_by_stream_names_3(): void
    {
        $category = null;
        $streamNames = ['transaction_add', 'transaction_subtract', 'transaction_divide'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->provider->hasRealStreamName($streamName));

            $this->provider->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->provider->hasRealStreamName($streamName));
        }

        $this->provider->createStream('foo', 'foo_table', $category);
        $this->provider->createStream('bar', 'bar_table', $category);

        $expectedStreamNames = ['transaction_add', 'transaction_divide', 'transaction_subtract'];

        $this->assertEquals($expectedStreamNames, $this->provider->filterByStreams($streamNames));
    }

    #[Test]
    public function it_filter_by_categories(): void
    {
        $category = 'transaction';
        $streamNames = ['add', 'subtract', 'divide'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->provider->hasRealStreamName($streamName));

            $this->provider->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->provider->hasRealStreamName($streamName));
        }

        $this->provider->createStream('operation', $this->tableName);

        $expectedCategories = ['add', 'divide', 'subtract'];

        $this->assertEquals($expectedCategories, $this->provider->filterByCategories(['transaction']));
    }

    #[Test]
    public function it_fetch_all_stream_without_internal_stream_beginning_with_dollar_sign(): void
    {
        $category = 'transaction';
        $streamNames = ['add', 'divide', 'subtract'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->provider->hasRealStreamName($streamName));

            $this->provider->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->provider->hasRealStreamName($streamName));
        }

        $this->provider->createStream('operation', $this->tableName);
        $this->provider->createStream('$all', $this->tableName);

        $expectedCategories = ['add', 'divide', 'subtract'];

        $this->assertEquals($expectedCategories, $this->provider->filterByCategories(['transaction']));
        $this->assertEquals($streamNames, $this->provider->filterByStreams($streamNames));

        $this->assertEquals([
            'add', 'divide', 'operation', 'subtract',
        ], $this->provider->allWithoutInternal());
    }

    private function retrieveByStreamName(string $streamName): EventStreamModel
    {
        $connection = $this->app['db']->connection();

        $result = $connection->table($this->provider::TABLE_NAME)->where('real_stream_name', $streamName)->first();

        return new EventStream($result->real_stream_name, $result->stream_name, $result->category);
    }
}
