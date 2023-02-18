<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Aggregate;

use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Cache\Repository;
use Chronhub\Storm\Aggregate\AggregateType;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Storm\Aggregate\NullAggregateCache;
use Chronhub\Storm\Stream\OneStreamPerAggregate;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Storm\Aggregate\AggregateRepository;
use Chronhub\Storm\Message\ChainMessageDecorator;
use Chronhub\Storm\Stream\SingleStreamPerAggregate;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootStub;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Larastorm\Aggregate\AggregateTaggedCache;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootChildStub;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootFinalStub;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Aggregate\AggregateRepositoryManager;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Chronhub\Storm\Chronicler\InMemory\StandaloneInMemoryChronicler;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepositoryManager as RepositoryManager;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepository as AggregateRepositoryContract;

final class InMemoryAggregateRepositoryManagerTest extends OrchestraTestCase
{
    private AggregateRepositoryManager $repositoryManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryManager = $this->app[RepositoryManager::class];
    }

    /**
     * @test
     */
    public function it_return_a_generic_aggregate_repository(): void
    {
        $this->assertTrue($this->app['config']['aggregate.repository.use_messager_decorators']);

        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
                'repository' => 'generic',
                'chronicler' => ['in_memory', 'standalone'],
                'strategy' => 'single',
                'aggregate_type' => [
                    'root' => AggregateRootStub::class,
                    'lineage' => [],
                ],
                'cache' => [
                    'size' => 0,
                    'tag' => null,
                    'driver' => null,
                ],
                'event_decorators' => [],
            ],
        ]);

        $aggregateRepository = $this->repositoryManager->create('transaction');

        $this->assertEquals(AggregateRepository::class, $aggregateRepository::class);
        $this->assertInstanceOf(AggregateRepository::class, $aggregateRepository);
        $this->assertEquals(SingleStreamPerAggregate::class, $aggregateRepository->streamProducer::class);
        $this->assertEquals(NullAggregateCache::class, $aggregateRepository->aggregateCache::class);
        $this->assertEquals(StandaloneInMemoryChronicler::class, $aggregateRepository->chronicler::class);

        $aggregateType = ReflectionProperty::getProperty($aggregateRepository, 'aggregateType');
        $this->assertEquals(AggregateType::class, $aggregateType::class);

        $eventDecorators = ReflectionProperty::getProperty($aggregateRepository, 'messageDecorator');
        $this->assertInstanceOf(ChainMessageDecorator::class, $eventDecorators);
    }

    /**
     * @test
     */
    public function it_define_aggregate_cache(): void
    {
        $this->assertTrue($this->app['config']['aggregate.repository.use_messager_decorators']);

        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
                'repository' => 'generic',
                'chronicler' => ['in_memory', 'standalone'],
                'strategy' => 'single',
                'aggregate_type' => [
                    'root' => AggregateRootStub::class,
                    'lineage' => [],
                ],
                'cache' => [
                    'size' => 2000,
                    'tag' => 'my_tag',
                    'driver' => 'redis',
                ],
                'event_decorators' => [],
            ],
        ]);

        $aggregateRepository = $this->repositoryManager->create('transaction');

        $this->assertInstanceOf(AggregateRepository::class, $aggregateRepository);
        $this->assertInstanceOf(AggregateTaggedCache::class, $aggregateRepository->aggregateCache);

        $size = ReflectionProperty::getProperty($aggregateRepository->aggregateCache, 'limit');
        $this->assertEquals(2000, $size);

        $tag = ReflectionProperty::getProperty($aggregateRepository->aggregateCache, 'cacheTag');
        $this->assertEquals('my_tag', $tag);

        $cacheDriver = ReflectionProperty::getProperty($aggregateRepository->aggregateCache, 'cache');
        $this->assertInstanceOf(Repository::class, $cacheDriver);
        $this->assertInstanceOf(RedisStore::class, $cacheDriver->getStore());
    }

    /**
     * @test
     */
    public function it_define_aggregate_producer_strategy(): void
    {
        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
                'repository' => 'generic',
                'chronicler' => ['in_memory', 'standalone'],
                'strategy' => 'per_aggregate',
                'aggregate_type' => [
                    'root' => AggregateRootStub::class,
                    'lineage' => [],
                ],
            ],
        ]);

        $aggregateRepository = $this->repositoryManager->create('transaction');

        $this->assertInstanceOf(AggregateRepository::class, $aggregateRepository);
        $this->assertEquals(OneStreamPerAggregate::class, $aggregateRepository->streamProducer::class);
    }

    /**
     * @test
     */
    public function it_defined_event_store_as_registered_service(): void
    {
        $eventStore = Chronicle::setDefaultDriver('in_memory')->create('standalone');

        $this->app->instance('event_store.in_memory.standalone', $eventStore);

        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
                'repository' => 'generic',
                'chronicler' => 'event_store.in_memory.standalone',
                'strategy' => 'single',
                'aggregate_type' => [
                    'root' => AggregateRootStub::class,
                    'lineage' => [],
                ],
            ],
        ]);

        $aggregateRepository = $this->repositoryManager->create('transaction');

        $this->assertInstanceOf(AggregateRepository::class, $aggregateRepository);
        $this->assertSame($eventStore, $aggregateRepository->chronicler);
    }

    /**
     * @test
     */
    public function it_define_aggregate_type_as_string_aggregate_root(): void
    {
        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
                'repository' => 'generic',
                'chronicler' => ['in_memory', 'standalone'],
                'strategy' => 'single',
                'aggregate_type' => AggregateRootStub::class,
            ],
        ]);

        $aggregateRepository = $this->repositoryManager->create('transaction');

        $this->assertInstanceOf(AggregateRepository::class, $aggregateRepository);

        $aggregateType = ReflectionProperty::getProperty($aggregateRepository, 'aggregateType');
        $this->assertEquals(AggregateType::class, $aggregateType::class);

        $this->assertEquals(AggregateRootStub::class, $aggregateType->current());
    }

    /**
     * @test
     */
    public function it_define_aggregate_type_as_registered_service(): void
    {
        $instance = new AggregateType(AggregateRootStub::class);
        $this->app->instance('aggregate.type.transaction', $instance);

        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
                'repository' => 'generic',
                'chronicler' => ['in_memory', 'standalone'],
                'strategy' => 'single',
                'aggregate_type' => 'aggregate.type.transaction',
            ],
        ]);

        $aggregateRepository = $this->repositoryManager->create('transaction');

        $this->assertInstanceOf(AggregateRepository::class, $aggregateRepository);

        $aggregateType = ReflectionProperty::getProperty($aggregateRepository, 'aggregateType');
        $this->assertEquals(AggregateType::class, $aggregateType::class);

        $this->assertEquals(AggregateRootStub::class, $aggregateType->current());
    }

    /**
     * @test
     */
    public function it_define_aggregate_type_with_children(): void
    {
        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
                'repository' => 'generic',
                'chronicler' => ['in_memory', 'standalone'],
                'strategy' => 'single',
                'aggregate_type' => [
                    'root' => AggregateRootStub::class,
                    'lineage' => [AggregateRootChildStub::class],
                ],
            ],
        ]);

        $aggregateRepository = $this->repositoryManager->create('transaction');

        $this->assertInstanceOf(AggregateRepository::class, $aggregateRepository);

        $aggregateType = ReflectionProperty::getProperty($aggregateRepository, 'aggregateType');
        $this->assertInstanceOf(AggregateType::class, $aggregateType);

        $this->assertEquals(AggregateRootStub::class, $aggregateType->current());
        $this->assertTrue($aggregateType->isSupported(AggregateRootChildStub::class));
        $this->assertFalse($aggregateType->isSupported(AggregateRootFinalStub::class));
    }

    /**
     * @test
     */
    public function it_extend_repository_manager(): void
    {
        $expectedConfig = [
            'repository' => null,
            'chronicler' => ['in_memory', 'standalone'],
            'strategy' => 'single',
            'aggregate_type' => AggregateRootStub::class,
        ];

        $this->app['config']->set('aggregate.repository.repositories.withdraw', $expectedConfig);

        $mock = $this->createMock(AggregateRepositoryContract::class);

        $this->repositoryManager->extends('withdraw',
            function (Application $app, string $streamName, array $config) use ($expectedConfig, $mock): AggregateRepositoryContract {
                $this->assertEquals($expectedConfig, $config);
                $this->assertEquals('withdraw', $streamName);

                return $mock;
            });

        $this->assertSame($mock, $this->repositoryManager->create('withdraw'));
    }

    /**
     * @test
     */
    public function it_raise_exception_when_stream_name_is_not_defined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Repository config with stream name foo is not defined');

        $this->app['config']->set('aggregate.repository.repositories', []);

        $this->repositoryManager->create('foo');
    }

    /**
     * @test
     */
    public function it_raise_exception_when_repository_driver_is_not_defined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Aggregate repository with stream name transaction is not defined');

        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
                'repository' => 'bar',
            ],
        ]);

        $this->repositoryManager->create('transaction');
    }

    /**
     * @test
     */
    public function it_raise_exception_when_aggregate_producer_strategy_is_not_defined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Strategy given for stream name transaction is not defined');

        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
                'repository' => 'generic',
                'chronicler' => ['in_memory', 'standalone'],
                'strategy' => 'foo',
                'aggregate_type' => AggregateRootStub::class,
            ],
        ]);

        $this->repositoryManager->create('transaction');
    }

    protected function getPackageProviders($app): array
    {
        return [ChroniclerServiceProvider::class];
    }
}
