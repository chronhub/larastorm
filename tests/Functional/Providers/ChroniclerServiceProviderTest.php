<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use Chronhub\Storm\Chronicler\TrackStream;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Serializer\ConvertStreamEvent;
use Chronhub\Storm\Stream\DetermineStreamCategory;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Larastorm\EventStore\EventStoreManager;
use Chronhub\Storm\Serializer\DomainEventSerializer;
use Chronhub\Storm\Chronicler\TrackTransactionalStream;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Contracts\Serializer\StreamEventConverter;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;

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
            ],
            'providers' => [
                'connection' => [
                    'write' => [
                        'store' => 'pgsql',
                        'tracking' => [
                            'tracker_id' => TrackTransactionalStream::class,
                            'subscribers' => [
                                //'\Chronhub\Chronicler\Publisher\EventPublisherSubscriber::class',
                            ],
                        ],
                        'write_lock' => true,
                        'strategy' => 'single',
                        'query_loader' => 'cursor',
                    ],

                    'read' => [
                        'store' => 'pgsql',
                        'is_transactional' => false,
                        'write_lock' => false,
                        'strategy' => 'single',
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
                    '\Chronhub\Chronicler\Support\Console\CreateEventStreamCommand::class',
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
        ], $provider->provides());
    }

    protected function getPackageProviders($app): array
    {
        return [
            MessagerServiceProvider::class, // require date time normalizer binding
            ChroniclerServiceProvider::class,
        ];
    }
}
