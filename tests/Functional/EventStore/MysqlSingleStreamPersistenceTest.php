<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use Generator;
use Symfony\Component\Uid\Uuid;
use Prophecy\PhpUnit\ProphecyTrait;
use Chronhub\Storm\Clock\PointInTime;
use Chronhub\Storm\Stream\StreamName;
use Prophecy\Prophecy\ObjectProphecy;
use Illuminate\Support\Facades\Schema;
use Chronhub\Storm\Contracts\Message\Header;
use Chronhub\Storm\Serializer\JsonSerializer;
use Chronhub\Larastorm\Tests\Double\SomeEvent;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Message\EventHeader;
use Chronhub\Storm\Serializer\DomainEventSerializer;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootStub;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Storm\Contracts\Stream\StreamPersistenceWithQueryHint;
use Chronhub\Larastorm\EventStore\Persistence\MysqlSingleStreamPersistence;
use function array_keys;

final class MysqlSingleStreamPersistenceTest extends OrchestraTestCase
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

        $streamPersistence = $this->newStreamPersistence();

        $tableName = $streamPersistence->tableName(new StreamName($streamName));

        $this->assertEquals($expectedTableName, $tableName);
    }

    /**
     * @test
     */
    public function it_return_query_index(): void
    {
        $this->assertEquals('ix_query_aggregate', MysqlSingleStreamPersistence::QUERY_INDEX);

        $streamPersistence = $this->newStreamPersistence();

        $this->assertEquals('_foo_bar_ix_query_aggregate', $streamPersistence->indexName('_foo_bar'));
    }

    /**
     * @test
     */
    public function it_up_stream_table(): void
    {
        $tableName = '_'.'foo_bar';

        $streamPersistence = $this->newStreamPersistence();

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

        $this->assertArrayHasKey('_foo_bar_event_id_unique', $indexes);
        $this->assertArrayHasKey('_foo_bar_ix_unique_event', $indexes);
        $this->assertArrayHasKey($streamPersistence->indexName($tableName), $indexes);
    }

    /**
     * @test
     */
    public function it_serialize_domain_event(): void
    {
        $streamSerializer = new DomainEventSerializer(null);

        $streamPersistence = $this->newStreamPersistence($streamSerializer);

        $headers = [
            Header::EVENT_ID => Uuid::v4()->jsonSerialize(),
            Header::EVENT_TYPE => SomeEvent::class,
            Header::EVENT_TIME => (new PointInTime())->now()->format(PointInTime::DATE_TIME_FORMAT),
            EventHeader::AGGREGATE_VERSION => 1,
            EventHeader::AGGREGATE_ID => Uuid::v4()->jsonSerialize(),
            EventHeader::AGGREGATE_ID_TYPE => 'some_aggregate_type',
            EventHeader::AGGREGATE_TYPE => AggregateRootStub::class,
        ];

        $content = ['email' => 'chronhubgit@gmail.com'];
        $someEvent = (new SomeEvent($content))->withHeaders($headers);

        $serializedEvent = $streamPersistence->serialize($someEvent);

        $this->assertEquals([
            'event_id',
            'event_type',
            'aggregate_id',
            'aggregate_type',
            'aggregate_version',
            'headers',
            'content',
            'created_at',
        ], array_keys($serializedEvent));

        $jsonSerializer = new JsonSerializer();

        $this->assertIsString($serializedEvent['headers']);
        $this->assertEquals($jsonSerializer->encode($headers), $serializedEvent['headers']);

        $this->assertIsString($serializedEvent['content']);
        $this->assertEquals($jsonSerializer->encode($content), $serializedEvent['content']);
    }

    /**
     * @test
     */
    public function it_assert_is_auto_incremented(): void
    {
        $this->assertTrue($this->newStreamPersistence()->isAutoIncremented());
    }

    private function newStreamPersistence(?StreamEventSerializer $serializer = null): MysqlSingleStreamPersistence
    {
        $instance = new MysqlSingleStreamPersistence($serializer ?? $this->serializer->reveal());

        $this->assertInstanceOf(StreamPersistenceWithQueryHint::class, $instance);

        return $instance;
    }

    public function provideStreamName(): Generator
    {
        yield ['foo'];
        yield ['foo_bar'];
    }
}