<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Providers;

use Chronhub\Storm\Clock\PointInTime;
use Illuminate\Support\ServiceProvider;
use Chronhub\Larastorm\Support\Facade\Clock;
use Chronhub\Storm\Contracts\Message\UniqueId;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Storm\Contracts\Message\MessageAlias;
use Chronhub\Storm\Serializer\JsonSerializerFactory;
use Illuminate\Contracts\Support\DeferrableProvider;
use Chronhub\Storm\Contracts\Serializer\MessageSerializer;
use Chronhub\Storm\Contracts\Message\MessageFactory as Factory;

class MessagerServiceProvider extends ServiceProvider implements DeferrableProvider
{
    protected string $messagerPath = __DIR__.'/../../config/messager.php';

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([$this->messagerPath => config_path('messager.php')]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom($this->messagerPath, 'messager');

        $this->registerSerializer();

        $this->registerBindings();
    }

    protected function registerSerializer(): void
    {
        $this->app->singleton(MessageSerializer::class, function (Application $app): MessageSerializer {
            $serializerFactory = new JsonSerializerFactory(fn (): Application => $app);

            return $serializerFactory->createMessageSerializer(
                null,
                ...config('messager.serializer.normalizers', [])
            );
        });
    }

    protected function registerBindings(): void
    {
        $this->app->singleton(SystemClock::class, PointInTime::class);

        $this->app->alias(SystemClock::class, Clock::SERVICE_ID);

        $this->app->singleton(
            Factory::class,
            fn (Application $app): Factory => $app[config('messager.factory')]
        );

        $this->app->singleton(
            MessageAlias::class,
            fn (Application $app): MessageAlias => $app[config('messager.alias')]
        );

        $this->app->singleton(UniqueId::class, config('messager.unique_id'));
    }

    public function provides(): array
    {
        return [
            SystemClock::class,
            Clock::SERVICE_ID,
            Factory::class,
            MessageSerializer::class,
            MessageAlias::class,
            UniqueId::class,
        ];
    }
}
