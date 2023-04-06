<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Support\Console\ListMessagerSubscribersCommand;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Message\MessageAlias;
use Chronhub\Storm\Contracts\Message\MessageFactory as Factory;
use Chronhub\Storm\Contracts\Message\UniqueId;
use Chronhub\Storm\Contracts\Serializer\MessageSerializer;
use Chronhub\Storm\Message\AliasFromClassName;
use Chronhub\Storm\Message\Decorator\EventId;
use Chronhub\Storm\Message\Decorator\EventTime;
use Chronhub\Storm\Message\Decorator\EventType;
use Chronhub\Storm\Message\MessageFactory;
use Chronhub\Storm\Message\UniqueIdV4;
use Chronhub\Storm\Reporter\Subscribers\MakeMessage;
use Chronhub\Storm\Serializer\MessagingSerializer;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;

#[CoversClass(MessagerServiceProvider::class)]
final class MessagerServiceProviderTest extends OrchestraTestCase
{
    public function testConfiguration(): void
    {
        $this->assertEquals([
            'unique_id' => UniqueIdV4::class,
            'factory' => MessageFactory::class,
            'alias' => AliasFromClassName::class,
            'serializer' => [
                'normalizers' => [
                    UidNormalizer::class,
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
            'console' => [
                'commands' => [
                    ListMessagerSubscribersCommand::class,
                ],
            ],
        ], config('messager'));
    }

    public function testBindings(): void
    {
        $this->assertTrue($this->app->bound(Factory::class));
        $this->assertInstanceOf(MessageFactory::class, $this->app[Factory::class]);

        $this->assertTrue($this->app->bound(MessageSerializer::class));
        $this->assertInstanceOf(MessagingSerializer::class, $this->app[MessageSerializer::class]);

        $this->assertTrue($this->app->bound(MessageAlias::class));
        $this->assertInstanceOf(AliasFromClassName::class, $this->app[MessageAlias::class]);

        $this->assertTrue($this->app->bound(UniqueId::class));
        $this->assertInstanceOf(UniqueIdV4::class, $this->app[UniqueId::class]);

        $this->assertArrayHasKey('messager:subscribers', Artisan::all());
    }

    public function testProvides(): void
    {
        $provider = $this->app->getProvider(MessagerServiceProvider::class);

        $this->assertEquals([
            Factory::class,
            MessageSerializer::class,
            MessageAlias::class,
            UniqueId::class,
        ], $provider->provides());
    }

    protected function getPackageProviders($app): array
    {
        return [
            ClockServiceProvider::class,
            MessagerServiceProvider::class,
        ];
    }
}
