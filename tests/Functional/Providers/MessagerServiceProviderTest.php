<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use Chronhub\Storm\Clock\PointInTime;
use Chronhub\Storm\Message\MessageFactory;
use Chronhub\Larastorm\Support\Facade\Clock;
use Chronhub\Storm\Contracts\Message\UniqueId;
use Chronhub\Storm\Message\AliasFromClassName;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Storm\Contracts\Message\MessageAlias;
use Chronhub\Storm\Serializer\MessagingSerializer;
use Chronhub\Larastorm\Support\UniqueId\UniqueIdV4;
use Chronhub\Storm\Reporter\Subscribers\MakeMessage;
use Chronhub\Larastorm\Support\MessageDecorator\EventId;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Support\MessageDecorator\EventTime;
use Chronhub\Larastorm\Support\MessageDecorator\EventType;
use Chronhub\Storm\Contracts\Serializer\MessageSerializer;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Chronhub\Storm\Contracts\Message\MessageFactory as Factory;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

final class MessagerServiceProviderTest extends OrchestraTestCase
{
    /**
     * @test
     */
    public function it_fix_messager_configuration(): void
    {
        $this->assertEquals([
            'clock' => PointInTime::class,
            'unique_id' => UniqueIdV4::class,
            'factory' => MessageFactory::class,
            'alias' => AliasFromClassName::class,
            'serializer' => [
                'concrete' => MessagingSerializer::class,
                'normalizers' => [
                    UidNormalizer::class,
                    'serializer.normalizer.event_time',
                ],
            ],
            'decorators' => [
                EventId::class,
                EventTime::class,
                EventType::class,
            ],
            'subscribers' => [
                MakeMessage::class,
            ],
        ], config('messager'));
    }

    /**
     * @test
     */
    public function it_assert_bindings(): void
    {
        $this->assertTrue($this->app->bound(SystemClock::class));
        $this->assertTrue($this->app->bound(Clock::SERVICE_ID));
        $this->assertInstanceOf(PointInTime::class, $this->app[SystemClock::class]);

        $this->assertTrue($this->app->bound(Factory::class));
        $this->assertInstanceOf(MessageFactory::class, $this->app[Factory::class]);

        $this->assertTrue($this->app->bound('serializer.normalizer.event_time'));
        $this->assertInstanceOf(DateTimeNormalizer::class, $this->app['serializer.normalizer.event_time']);

        $this->assertTrue($this->app->bound(MessageSerializer::class));
        $this->assertInstanceOf(MessagingSerializer::class, $this->app[MessageSerializer::class]);

        $this->assertTrue($this->app->bound(MessageAlias::class));
        $this->assertInstanceOf(AliasFromClassName::class, $this->app[MessageAlias::class]);

        $this->assertTrue($this->app->bound(UniqueId::class));
        $this->assertInstanceOf(UniqueIdV4::class, $this->app[UniqueId::class]);
    }

    /**
     * @test
     */
    public function it_assert_provides(): void
    {
        $provider = $this->app->getProvider(MessagerServiceProvider::class);

        $this->assertEquals([
            SystemClock::class,
            Clock::SERVICE_ID,
            Factory::class,
            'serializer.normalizer.event_time',
            MessageSerializer::class,
            MessageAlias::class,
            UniqueId::class,
        ], $provider->provides());
    }

    protected function getPackageProviders($app): array
    {
        return [MessagerServiceProvider::class];
    }
}
