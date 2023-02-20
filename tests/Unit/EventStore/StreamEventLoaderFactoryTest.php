<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Illuminate\Container\Container;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Larastorm\EventStore\Loader\LazyQueryLoader;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoader;
use Chronhub\Larastorm\EventStore\Loader\CursorQueryLoader;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Larastorm\EventStore\Loader\StreamEventLoaderFactory;
use Illuminate\Contracts\Container\Container as ContainerContract;

final class StreamEventLoaderFactoryTest extends ProphecyTestCase
{
    private ContainerContract $container;

    protected function setUp(): void
    {
        $this->container = Container::getInstance();
        $this->container->bind(StreamEventLoader::class, function () {
            return $this->prophesize(StreamEventLoader::class)->reveal();
        });
    }

    /**
     * @test
     *
     * @dataProvider provideCursorKey
     */
    public function it_return_cursor_query_loader_instance(?string $key): void
    {
        $factory = new StreamEventLoaderFactory($this->container);

        $this->assertInstanceOf(CursorQueryLoader::class, $factory($key));
    }

    /**
     * @test
     */
    public function it_return_lazy_query_loader_instance(): void
    {
        $factory = new StreamEventLoaderFactory($this->container);

        $this->assertInstanceOf(LazyQueryLoader::class, $factory('lazy'));
    }

    /**
     * @test
     *
     * @dataProvider provideRandomInteger
     */
    public function it_return_lazy_query_loader_instance_with_chunk_size_defined(int $chunkSize): void
    {
        // checkMe
        $this->container->bind(
            StreamEventSerializer::class,
            fn (): StreamEventSerializer => $this->prophesize(StreamEventSerializer::class)->reveal()
        );

        $factory = new StreamEventLoaderFactory($this->container);

        $instance = $factory('lazy:'.$chunkSize);

        $this->assertInstanceOf(LazyQueryLoader::class, $instance);

        $this->assertEquals($chunkSize, $instance->chunkSize);
    }

    /**
     * @test
     */
    public function it_finally_resolve_service_with_container(): void
    {
        $this->container->bind('stream_event.loader', fn (Container $container): StreamEventLoader => $container[CursorQueryLoader::class]);

        $factory = new StreamEventLoaderFactory($this->container);

        $instance = $factory('stream_event.loader');

        $this->assertInstanceOf(CursorQueryLoader::class, $instance);
    }

    public function provideRandomInteger(): Generator
    {
        yield [500];
        yield [2000];
        yield [5000];
    }

    public function provideCursorKey(): Generator
    {
        yield ['cursor'];
        yield [null];
    }
}
