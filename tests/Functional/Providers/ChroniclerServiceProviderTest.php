<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use Chronhub\Storm\Chronicler\TrackStream;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Storm\Stream\DetermineStreamCategory;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Larastorm\EventStore\EventStoreManager;
use Chronhub\Storm\Serializer\DomainEventSerializer;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Storm\Publisher\EventPublisherSubscriber;
use Chronhub\Storm\Chronicler\TrackTransactionalStream;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\EventStore\EventStoreConnectionFactory;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Larastorm\Support\Console\CreateEventStreamCommand;
use Chronhub\Storm\Chronicler\InMemory\InMemoryChroniclerFactory;
use Chronhub\Larastorm\EventStore\Persistence\PgsqlSingleStreamPersistence;

#[CoversClass(ChroniclerServiceProvider::class)]
final class ChroniclerServiceProviderTest extends OrchestraTestCase
{
    public function testConfiguration(): void
    {
        $this->assertEquals([
            'event_serializer' => [
                'normalizers' => [
                    UidNormalizer::class,
                ],
            ],
            'defaults' => [
                'provider' => 'connection',
                'event_stream_provider' => [
                    'connection' => [
                        'name' => 'pgsql',
                        'table_name' => 'event_streams',
                    ],
                ],
                'providers' => [
                    'connection' => EventStoreConnectionFactory::class,
                    'in_memory' => InMemoryChroniclerFactory::class,
                ],
            ],
            'providers' => [
                'connection' => [
                    'publish' => [
                        'store' => 'pgsql',
                        'tracking' => [
                            'tracker_id' => TrackTransactionalStream::class,
                            'subscribers' => [
                                EventPublisherSubscriber::class,
                            ],
                        ],
                        'write_lock' => true,
                        'strategy' => PgsqlSingleStreamPersistence::class,
                        'query_loader' => 'cursor',
                    ],

                    'read' => [
                        'store' => 'pgsql',
                        'is_transactional' => false,
                        'write_lock' => false,
                        'strategy' => PgsqlSingleStreamPersistence::class,
                        'query_loader' => 'cursor',
                    ],
                ],
                'in_memory' => [
                    'standalone' => [],
                    'transactional' => [],
                    'eventable' => [
                        'tracking' => [
                            'tracker_id' => TrackStream::class,
                            'subscribers' => [],
                        ],
                    ],
                    'transactional_eventable' => [
                        'tracking' => [
                            'tracker_id' => TrackTransactionalStream::class,
                            'subscribers' => [],
                        ],
                    ],
                ],
            ],

            'console' => [
                'load_migration' => true,

                'commands' => [
                    CreateEventStreamCommand::class,
                ],
            ],
        ], config('chronicler'));
    }

    public function testBindings(): void
    {
        $this->assertTrue($this->app->bound(StreamEventSerializer::class));
        $this->assertInstanceOf(DomainEventSerializer::class, $this->app[StreamEventSerializer::class]);

        $this->assertTrue($this->app->bound(StreamCategory::class));
        $this->assertInstanceOf(DetermineStreamCategory::class, $this->app[StreamCategory::class]);

        $this->assertTrue($this->app->bound(ChroniclerManager::class));
        $this->assertInstanceOf(EventStoreManager::class, $this->app[ChroniclerManager::class]);
        $this->assertTrue($this->app->bound(Chronicle::SERVICE_ID));

        $this->assertTrue($this->app->bound(InMemoryChroniclerFactory::class));
        $this->assertTrue($this->app->bound(EventStoreConnectionFactory::class));
    }

    public function testProvides(): void
    {
        $provider = $this->app->getProvider(ChroniclerServiceProvider::class);

        $this->assertEquals([
            StreamEventSerializer::class,
            StreamCategory::class,
            ChroniclerManager::class,
            Chronicle::SERVICE_ID,
            InMemoryChroniclerFactory::class,
            EventStoreConnectionFactory::class,
        ], $provider->provides());
    }

    protected function getPackageProviders($app): array
    {
        return [
            ClockServiceProvider::class,
            ChroniclerServiceProvider::class,
        ];
    }
}
