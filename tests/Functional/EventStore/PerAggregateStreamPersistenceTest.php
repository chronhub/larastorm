<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use Generator;
use Prophecy\PhpUnit\ProphecyTrait;
use Chronhub\Storm\Stream\StreamName;
use Prophecy\Prophecy\ObjectProphecy;
use Illuminate\Support\Facades\Schema;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Storm\Contracts\Stream\StreamPersistenceWithQueryHint;
use Chronhub\Larastorm\EventStore\Persistence\PerAggregateStreamPersistence;

final class PerAggregateStreamPersistenceTest extends OrchestraTestCase
{
    use ProphecyTrait;

    private StreamEventSerializer|ObjectProphecy $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = $this->prophesize(StreamEventSerializer::class);
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
        $this->assertNotInstanceOf(StreamPersistenceWithQueryHint::class, $streamPersistence);
    }

    /**
     * @test
     *
     * @dataProvider provideStreamName
     */
    public function it_up_stream_table(string $streamName): void
    {
        $tableName = '_'.$streamName;

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

        $this->assertArrayHasKey($tableName.'_aggregate_version_unique', $indexes);
    }

    /**
     * @test
     */
    public function it_assert_true_is_support_one_stream_per_aggregate(): void
    {
        $this->assertFalse($this->newInstance()->isAutoIncremented());
    }

    /**
     * @test
     */
    public function it_serialize_event(): void
    {
        $this->markTestSkipped('todo');
    }

    private function newInstance(?StreamEventSerializer $serializer = null): PerAggregateStreamPersistence
    {
        return new PerAggregateStreamPersistence($serializer ?? $this->serializer->reveal());
    }

    public function provideStreamName(): Generator
    {
        yield ['foo'];
        yield ['foo_bar'];
    }
}
