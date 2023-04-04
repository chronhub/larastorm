<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Illuminate\Container\Container;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\EventStore\Loader\LazyQueryLoader;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoader;
use Chronhub\Larastorm\EventStore\Loader\CursorQueryLoader;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Chronhub\Larastorm\EventStore\Loader\EventLoaderConnectionFactory;

#[CoversClass(EventLoaderConnectionFactory::class)]
final class StreamEventLoaderFactoryTest extends UnitTestCase
{
    private ContainerContract $container;

    protected function setUp(): void
    {
        $this->container = Container::getInstance();
        $this->container->bind(
            StreamEventLoader::class,
            fn () => $this->createMock(StreamEventLoader::class)
        );
    }

    #[DataProvider('provideCursorKey')]
    public function testCursorInstance(?string $name): void
    {
        $factory = new EventLoaderConnectionFactory($this->container);

        $this->assertInstanceOf(CursorQueryLoader::class, $factory->createEventLoader($name));
    }

    public function testLazyInstance(): void
    {
        $factory = new EventLoaderConnectionFactory($this->container);

        $this->assertInstanceOf(LazyQueryLoader::class, $factory->createEventLoader('lazy'));
    }

    #[DataProvider('provideRandomInteger')]
    public function testLazyInstanceWithChunkSizeDefined(int $chunkSize): void
    {
        $this->container->bind(
            StreamEventSerializer::class,
            fn (): StreamEventSerializer => $this->createMock(StreamEventSerializer::class)
        );

        $factory = new EventLoaderConnectionFactory($this->container);

        $instance = $factory->createEventLoader('lazy:'.$chunkSize);

        $this->assertInstanceOf(LazyQueryLoader::class, $instance);

        $this->assertEquals($chunkSize, $instance->chunkSize);
    }

    public function testResolveEventLoaderFromIoc(): void
    {
        $this->container->bind(
            'stream_event.loader',
            fn (Container $container): StreamEventLoader => $container[CursorQueryLoader::class]
        );

        $factory = new EventLoaderConnectionFactory($this->container);

        $instance = $factory->createEventLoader('stream_event.loader');

        $this->assertInstanceOf(CursorQueryLoader::class, $instance);
    }

    public static function provideRandomInteger(): Generator
    {
        yield [500];
        yield [2000];
        yield [5000];
    }

    public static function provideCursorKey(): Generator
    {
        yield ['cursor'];
        yield [null];
    }
}
