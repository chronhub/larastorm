<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use Generator;
use Prophecy\PhpUnit\ProphecyTrait;
use Chronhub\Storm\Stream\StreamName;
use Prophecy\Prophecy\ObjectProphecy;
use Illuminate\Support\Facades\Schema;
use Chronhub\Larastorm\Tests\Double\SomeEvent;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Serializer\StreamEventConverter;
use Chronhub\Larastorm\EventStore\Persistence\SingleStreamPersistence;

final class SingleStreamPersistenceTest extends OrchestraTestCase
{
    use ProphecyTrait;

    private StreamEventConverter|ObjectProphecy $eventConverter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventConverter = $this->prophesize(StreamEventConverter::class);
    }

    /**
     * @test
     *
     * @dataProvider provideStreamName
     */
    public function it_produce_table_name_from_stream_name(string $streamName): void
    {
        $expectedTableName = '_'.$streamName;

        $streamPersistence = $this->newInstance();

        $tableName = $streamPersistence->tableName(new StreamName($streamName));

        $this->assertEquals($expectedTableName, $tableName);
        $this->assertEquals('ix_query_aggregate', $streamPersistence->indexName($tableName));
    }

    /**
     * @test
     */
    public function it_return_query_index(): void
    {
        $streamPersistence = $this->newInstance();

        $tableName = $streamPersistence->tableName(new StreamName('foo'));

        $this->assertEquals('ix_query_aggregate', $streamPersistence->indexName($tableName));
    }

    /**
     * @test
     */
    public function it_up_stream_table(): void
    {
        $tableName = '_'.'foo_bar';

        $streamPersistence = $this->newInstance();

        $this->assertNull($streamPersistence->up($tableName));

        $this->assertTrue(Schema::hasTable($tableName));

        $this->assertTrue(Schema::hasColumns($tableName, [
            'no', 'event_id', 'event_type', 'content', 'headers',
            'aggregate_id', 'aggregate_type', 'aggregate_version',
            'created_at',
        ]));

        $this->assertEquals('integer', Schema::getColumnType($tableName, 'no'));
        $this->assertEquals('string', Schema::getColumnType($tableName, 'event_id'));
        $this->assertEquals('text', Schema::getColumnType($tableName, 'content'));
        $this->assertEquals('text', Schema::getColumnType($tableName, 'headers'));
        $this->assertEquals('string', Schema::getColumnType($tableName, 'aggregate_id'));
        $this->assertEquals('string', Schema::getColumnType($tableName, 'aggregate_type'));
        $this->assertEquals('integer', Schema::getColumnType($tableName, 'aggregate_version'));
        $this->assertEquals('datetime', Schema::getColumnType($tableName, 'created_at'));

        $doctrine = Schema::getConnection()->getDoctrineSchemaManager();

        $indexes = $doctrine->listTableIndexes($tableName);

        $this->assertArrayHasKey($tableName.'_event_id_unique', $indexes);
        $this->assertArrayHasKey($tableName.'_ix_unique_event', $indexes);
    }

    /**
     * @test
     */
    public function it_assert_is_auto_incremented(): void
    {
        $this->assertTrue($this->newInstance()->isAutoIncremented());
    }

    /**
     * @test
     */
    public function it_serialize_event(): void
    {
        $event = SomeEvent::fromContent(['foo' => 'bar']);
        $convertedEvent = ['headers' => [], 'content' => ['foo' => 'bar']];

        $this->eventConverter->toArray($event, true)->willReturn($convertedEvent)->shouldBeCalledOnce();

        $this->assertEquals($convertedEvent, $this->newInstance($this->eventConverter->reveal())->serializeEvent($event));
    }

    private function newInstance(?StreamEventConverter $eventConverter = null): SingleStreamPersistence
    {
        return new SingleStreamPersistence($eventConverter ?? $this->eventConverter->reveal());
    }

    public function provideStreamName(): Generator
    {
        yield ['foo'];
        yield ['foo_bar'];
    }
}
