<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use Generator;
use Symfony\Component\Uid\Uuid;
use Illuminate\Database\Connection;
use Chronhub\Storm\Clock\PointInTime;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Support\Facades\Schema;
use Chronhub\Storm\Contracts\Message\Header;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Storm\Serializer\SerializeToJson;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Message\EventHeader;
use Chronhub\Larastorm\Tests\Stubs\Double\SomeEvent;
use Chronhub\Storm\Serializer\JsonSerializerFactory;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootStub;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Storm\Contracts\Stream\StreamPersistenceWithQueryHint;
use Chronhub\Larastorm\EventStore\Persistence\AbstractStreamPersistence;
use Chronhub\Larastorm\EventStore\Persistence\PgsqlSingleStreamPersistence;
use function array_keys;

#[CoversClass(AbstractStreamPersistence::class)]
#[CoversClass(PgsqlSingleStreamPersistence::class)]
final class PgsqlSingleStreamPersistenceTest extends OrchestraTestCase
{
    private StreamEventSerializer|MockObject $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = $this->createMock(StreamEventSerializer::class);
    }

    #[DataProvider('provideStreamName')]
    public function testProduceTableNameFromStreamName(string $streamName): void
    {
        $expectedTableName = '_'.$streamName;

        $streamPersistence = $this->newInstance();

        $tableName = $streamPersistence->tableName(new StreamName($streamName));

        $this->assertEquals($expectedTableName, $tableName);
    }

    public function testUpStream(): void
    {
        $tableName = '_'.'foo_bar';

        $streamPersistence = $this->newInstance();

        $this->assertIsCallable($streamPersistence->up($tableName));

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
        $this->assertArrayHasKey('_foo_bar_aggregate_type_aggregate_id_no_index', $indexes);
        $this->assertArrayHasKey('_foo_bar_aggregate_type_aggregate_id_aggregate_version_unique', $indexes);
    }

    public function testSerializeDomainEvent(): void
    {
        $factory = new JsonSerializerFactory(fn () => $this->app);

        $streamPersistence = $this->newInstance($factory->createStreamSerializer());

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

        $jsonSerializer = new SerializeToJson();

        $this->assertIsString($serializedEvent['headers']);
        $this->assertEquals($jsonSerializer->encode($headers), $serializedEvent['headers']);

        $this->assertIsString($serializedEvent['content']);
        $this->assertEquals($jsonSerializer->encode($content), $serializedEvent['content']);
    }

    public function testAssertCallback(): void
    {
        $tableName = '_'.'foo_bar';

        $streamPersistence = $this->newInstance();

        $addConstraints = $streamPersistence->up($tableName);

        $this->assertIsCallable($addConstraints);

        $connection = $this->createMock(Connection::class);

        $connection
            ->expects($this->exactly(3))
            ->method('statement')
            ->willReturnMap(
                [
                    ['ALTER TABLE '.$tableName.' ADD CONSTRAINT event_id_not_null CHECK ( (headers->\'__event_id\') is not null )'],
                    ['ALTER TABLE '.$tableName.' ADD CONSTRAINT event_type_not_null CHECK ( (headers->\'__event_type\') is not null )'],
                    ['ALTER TABLE '.$tableName.' ADD CONSTRAINT aggregate_version_not_null CHECK ( (headers->\'__aggregate_version\') is not null )'],
                ]
            );

        $addConstraints($connection);
    }

    public function testAssertIsAutoIncremented(): void
    {
        $this->assertTrue($this->newInstance()->isAutoIncremented());
    }

    private function newInstance(?StreamEventSerializer $serializer = null): PgsqlSingleStreamPersistence
    {
        $instance = new PgsqlSingleStreamPersistence($serializer ?? $this->serializer);

        $this->assertNotInstanceOf(StreamPersistenceWithQueryHint::class, $instance);

        return $instance;
    }

    public static function provideStreamName(): Generator
    {
        yield ['foo'];
        yield ['foo_bar'];
    }

    protected function getPackageProviders($app): array
    {
        return [ClockServiceProvider::class];
    }
}
