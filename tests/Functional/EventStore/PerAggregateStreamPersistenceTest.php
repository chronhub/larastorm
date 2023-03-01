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
use Chronhub\Larastorm\Tests\Double\SomeEvent;
use Chronhub\Storm\Serializer\SerializeToJson;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Message\EventHeader;
use Chronhub\Storm\Serializer\JsonSerializerFactory;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootStub;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Storm\Contracts\Stream\StreamPersistenceWithQueryHint;
use Chronhub\Larastorm\EventStore\Persistence\PerAggregateStreamPersistence;
use function array_keys;

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
    public function it_serialize_domain_event_with_no(): void
    {
        $factory = new JsonSerializerFactory(fn () => $this->app);
        $streamSerializer = $factory->createStreamSerializer();

        $streamPersistence = $this->newInstance($streamSerializer);

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
            'no',
        ], array_keys($serializedEvent));

        $this->assertArrayHasKey('no', $serializedEvent);
        $this->assertEquals(1, $serializedEvent['no']);

        $jsonSerializer = new SerializeToJson();

        $this->assertIsString($serializedEvent['headers']);
        $this->assertEquals($jsonSerializer->encode($headers), $serializedEvent['headers']);

        $this->assertIsString($serializedEvent['content']);
        $this->assertEquals($jsonSerializer->encode($content), $serializedEvent['content']);
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

    protected function getPackageProviders($app): array
    {
        // required for system clock in serializer factory
        return [MessagerServiceProvider::class];
    }
}
