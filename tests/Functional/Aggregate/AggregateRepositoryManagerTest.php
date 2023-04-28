<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Aggregate;

use Chronhub\Larastorm\Aggregate\AggregateRepositoryFactory;
use Chronhub\Larastorm\Aggregate\AggregateRepositoryManager;
use Chronhub\Larastorm\Aggregate\AggregateTaggedCache;
use Chronhub\Larastorm\Providers\AggregateRepositoryServiceProvider;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Support\Contracts\AggregateRepositoryManager as Manager;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootChildStub;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootFinalStub;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootStub;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Storm\Aggregate\AggregateType;
use Chronhub\Storm\Aggregate\GenericAggregateRepository;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepository;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepository as AggregateRepositoryContract;
use Chronhub\Storm\Stream\OneStreamPerAggregate;
use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(AggregateRepositoryManager::class)]
#[CoversClass(AggregateRepositoryFactory::class)]
final class AggregateRepositoryManagerTest extends OrchestraTestCase
{
    private AggregateRepositoryManager $repositoryManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryManager = $this->app[Manager::class];
    }

    public function testReturnGenericAggregateRepository(): void
    {
        $this->assertTrue($this->app['config']['aggregate.repository.use_messager_decorators']);

        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
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

        $this->assertEquals(GenericAggregateRepository::class, $aggregateRepository::class);
    }

    public function testConfigureCache(): void
    {
        $this->assertTrue($this->app['config']['aggregate.repository.use_messager_decorators']);

        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
                'chronicler' => ['in_memory', 'standalone'],
                'strategy' => 'single',
                'aggregate_type' => AggregateRootStub::class,
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

        $aggregateCache = ReflectionProperty::getProperty($aggregateRepository, 'aggregateCache');

        $this->assertInstanceOf(AggregateTaggedCache::class, $aggregateCache);
        $this->assertEquals(2000, $aggregateCache->limit);
        $this->assertEquals('my_tag', $aggregateCache->tag);

        $cacheDriver = ReflectionProperty::getProperty($aggregateCache, 'cache');
        $this->assertInstanceOf(Repository::class, $cacheDriver);
        $this->assertInstanceOf(RedisStore::class, $cacheDriver->getStore());
    }

    public function testStreamProducer(): void
    {
        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
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

        $streamProducer = ReflectionProperty::getProperty($aggregateRepository, 'streamProducer');
        $this->assertEquals(OneStreamPerAggregate::class, $streamProducer::class);
    }

    public function testReturnSameEventStoreInstance(): void
    {
        $eventStore = Chronicle::setDefaultDriver('in_memory')->create('standalone');

        $this->app->instance('event_store.in_memory.standalone', $eventStore);

        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
                'chronicler' => 'event_store.in_memory.standalone',
                'strategy' => 'single',
                'aggregate_type' => AggregateRootStub::class,
            ],
        ]);

        $aggregateRepository = $this->repositoryManager->create('transaction');

        $this->assertInstanceOf(AggregateRepository::class, $aggregateRepository);

        $chronicler = ReflectionProperty::getProperty($aggregateRepository, 'chronicler');
        $this->assertSame($eventStore, $chronicler);
    }

    public function testDefineAggregateTypeFromRootString(): void
    {
        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
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

    public function testDefineAggregateTypeResolvedFromIoc(): void
    {
        $instance = new AggregateType(AggregateRootStub::class);
        $this->app->instance('aggregate.type.transaction', $instance);

        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
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

    public function testDefineAggregateTypeFromRootWithChildren(): void
    {
        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
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

    public function testExtendsRepositoryManager(): void
    {
        $expectedConfig = [
            'chronicler' => ['in_memory', 'standalone'],
            'strategy' => 'single',
            'aggregate_type' => AggregateRootStub::class,
        ];

        $this->app['config']->set('aggregate.repository.repositories.withdraw', $expectedConfig);

        $mock = $this->createMock(AggregateRepositoryContract::class);

        $this->repositoryManager->extends('withdraw',
            function (Container $app, string $streamName, array $config) use ($expectedConfig, $mock) {
                $this->assertEquals($expectedConfig, $config);
                $this->assertEquals('withdraw', $streamName);

                return $mock;
            });

        $this->assertSame($mock, $this->repositoryManager->create('withdraw'));
    }

    public function testExceptionRaisedWhenStreamNameNotDefined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Repository config with stream name foo is not defined');

        $this->app['config']->set('aggregate.repository.repositories', []);

        $this->repositoryManager->create('foo');
    }

    public function testExceptionRaisedWhenProducerStrategyNotDefined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Strategy given for stream name transaction is not defined');

        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
                'chronicler' => ['in_memory', 'standalone'],
                'strategy' => 'foo',
                'aggregate_type' => AggregateRootStub::class,
            ],
        ]);

        $this->repositoryManager->create('transaction');
    }

    public function testExceptionRaisedWhenSnapshotServiceProviderIsNotRegistered(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SnapshotStoreManager not bound in container');

        $this->app['config']->set('aggregate.repository.repositories', [
            'transaction' => [
                'chronicler' => ['in_memory', 'standalone'],
                'strategy' => 'single',
                'aggregate_type' => AggregateRootStub::class,
                'use_snapshot' => 'foo',
            ],
        ]);

        $this->repositoryManager->create('transaction');
    }

    protected function getPackageProviders($app): array
    {
        return [
            ChroniclerServiceProvider::class,
            AggregateRepositoryServiceProvider::class,
        ];
    }
}
