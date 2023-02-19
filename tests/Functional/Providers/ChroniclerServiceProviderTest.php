<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use Chronhub\Storm\Chronicler\TrackStream;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Storm\Serializer\ConvertStreamEvent;
use Chronhub\Storm\Stream\DetermineStreamCategory;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Larastorm\EventStore\EventStoreManager;
use Chronhub\Storm\Serializer\DomainEventSerializer;
use Chronhub\Storm\Publisher\EventPublisherSubscriber;
use Chronhub\Storm\Chronicler\TrackTransactionalStream;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Aggregate\AggregateRepositoryManager;
use Chronhub\Storm\Contracts\Serializer\StreamEventConverter;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Larastorm\EventStore\ConnectionChroniclerProvider;
use Chronhub\Larastorm\Support\Console\CreateEventStreamCommand;
use Chronhub\Storm\Chronicler\InMemory\InMemoryChroniclerProvider;
use Chronhub\Larastorm\EventStore\Persistence\PgsqlSingleStreamPersistence;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepositoryManager as RepositoryManager;

final class ChroniclerServiceProviderTest extends OrchestraTestCase
{
    /**
     * @test
     */
    public function it_fix_chronicler_configuration(): void
    {
        $this->assertEquals([
            'event_serializer' => [
                'concrete' => DomainEventSerializer::class,
                'normalizers' => [
                    UidNormalizer::class,
                    'serializer.normalizer.event_time',
                ],
            ],
            'defaults' => [
                'provider' => 'connection',
                'providers' => [
                    'connection' => ConnectionChroniclerProvider::class,
                    'in_memory' => InMemoryChroniclerProvider::class,
                ],
            ],
            'providers' => [
                'connection' => [
                    'write' => [
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

            /*
            |--------------------------------------------------------------------------
            | Migration and command
            |--------------------------------------------------------------------------
            |
            */

            'console' => [
                'load_migration' => true,

                'commands' => [
                    CreateEventStreamCommand::class,
                ],
            ],
        ], config('chronicler'));
    }

    /**
     * @test
     */
    public function it_assert_bindings(): void
    {
        $this->assertTrue($this->app->bound(StreamEventSerializer::class));
        $this->assertInstanceOf(DomainEventSerializer::class, $this->app[StreamEventSerializer::class]);

        $this->assertTrue($this->app->bound(StreamEventConverter::class));
        $this->assertInstanceOf(ConvertStreamEvent::class, $this->app[StreamEventConverter::class]);

        $this->assertTrue($this->app->bound(StreamCategory::class));
        $this->assertInstanceOf(DetermineStreamCategory::class, $this->app[StreamCategory::class]);

        $this->assertTrue($this->app->bound(ChroniclerManager::class));
        $this->assertInstanceOf(EventStoreManager::class, $this->app[ChroniclerManager::class]);
        $this->assertTrue($this->app->bound(Chronicle::SERVICE_ID));

        $this->assertTrue($this->app->bound(RepositoryManager::class));
        $this->assertInstanceOf(AggregateRepositoryManager::class, $this->app[RepositoryManager::class]);

        $this->assertTrue($this->app->bound(InMemoryChroniclerProvider::class));
        $this->assertTrue($this->app->bound(ConnectionChroniclerProvider::class));
    }

    /**
     * @test
     */
    public function it_assert_provides(): void
    {
        $provider = $this->app->getProvider(ChroniclerServiceProvider::class);

        $this->assertEquals([
            StreamEventSerializer::class,
            StreamCategory::class,
            StreamEventConverter::class,
            ChroniclerManager::class,
            Chronicle::SERVICE_ID,
            RepositoryManager::class,
            InMemoryChroniclerProvider::class,
            ConnectionChroniclerProvider::class,
        ], $provider->provides());
    }

    protected function getPackageProviders($app): array
    {
        return [
            MessagerServiceProvider::class,
            ChroniclerServiceProvider::class,
        ];
    }
}
