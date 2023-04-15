<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use Chronhub\Larastorm\EventStore\Persistence\EventStream;
use Chronhub\Larastorm\EventStore\Persistence\EventStreamProvider;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Chronicler\EventStreamModel;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;

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

    public function testCreateEventStream(): void
    {
        $streamName = 'transaction';

        $category = null;

        $this->assertFalse($this->provider->hasRealStreamName($streamName));

        $this->provider->createStream($streamName, $this->tableName, $category);

        $this->assertTrue($this->provider->hasRealStreamName($streamName));
    }

    public function testCreateEventStreamWithCategory(): void
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

    public function testDeleteEventStream(): void
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

    public function testFilterStreams(): void
    {
        $category = null;
        $streamNames = ['transaction_add', 'transaction_subtract', 'transaction_divide'];

        foreach ($streamNames as $streamName) {
            $this->assertFalse($this->provider->hasRealStreamName($streamName));

            $this->provider->createStream($streamName, $this->tableName, $category);

            $this->assertTrue($this->provider->hasRealStreamName($streamName));
        }

        $expectedStreamNames = [
            new StreamName('transaction_add'),
            new StreamName('transaction_divide'),
            new StreamName('transaction_subtract'),
        ];

        $this->assertEquals($expectedStreamNames, $this->provider->filterByAscendantStreams($streamNames));
    }

    public function testFilterCategoryStreams(): void
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

        $this->assertEquals($expectedCategories, $this->provider->filterByAscendantCategories(['transaction']));
    }

    public function testFetchAllStreamsWithoutInternalBeginningWithDollarSign(): void
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

        $this->assertEquals($expectedCategories, $this->provider->filterByAscendantCategories(['transaction']));
        $this->assertEquals($streamNames, $this->provider->filterByAscendantStreams($streamNames));

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
