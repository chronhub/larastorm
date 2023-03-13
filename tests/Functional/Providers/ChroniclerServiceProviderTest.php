<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use PHPUnit\Framework\Attributes\Test;
use Chronhub\Storm\Chronicler\TrackStream;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Support\Facade\Chronicle;
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
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Larastorm\EventStore\ConnectionChroniclerProvider;
use Chronhub\Larastorm\Support\Console\CreateEventStreamCommand;
use Chronhub\Storm\Chronicler\InMemory\InMemoryChroniclerProvider;
use Chronhub\Larastorm\EventStore\Persistence\PgsqlSingleStreamPersistence;

#[CoversClass(ChroniclerServiceProvider::class)]
final class ChroniclerServiceProviderTest extends OrchestraTestCase
{
    #[Test]
    public function it_fix_chronicler_configuration(): void
    {
        $this->assertEquals([
            'event_serializer' => [
                'normalizers' => [
                    UidNormalizer::class,
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

            'console' => [
                'load_migration' => true,

                'commands' => [
                    CreateEventStreamCommand::class,
                ],
            ],
        ], config('chronicler'));
    }

    #[Test]
    public function it_assert_bindings(): void
    {
        $this->assertTrue($this->app->bound(StreamEventSerializer::class));
        $this->assertInstanceOf(DomainEventSerializer::class, $this->app[StreamEventSerializer::class]);

        $this->assertTrue($this->app->bound(StreamCategory::class));
        $this->assertInstanceOf(DetermineStreamCategory::class, $this->app[StreamCategory::class]);

        $this->assertTrue($this->app->bound(ChroniclerManager::class));
        $this->assertInstanceOf(EventStoreManager::class, $this->app[ChroniclerManager::class]);
        $this->assertTrue($this->app->bound(Chronicle::SERVICE_ID));

        $this->assertTrue($this->app->bound(InMemoryChroniclerProvider::class));
        $this->assertTrue($this->app->bound(ConnectionChroniclerProvider::class));
    }

    #[Test]
    public function it_assert_provides(): void
    {
        $provider = $this->app->getProvider(ChroniclerServiceProvider::class);

        $this->assertEquals([
            StreamEventSerializer::class,
            StreamCategory::class,
            ChroniclerManager::class,
            Chronicle::SERVICE_ID,
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
