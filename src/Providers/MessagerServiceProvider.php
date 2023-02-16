<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Providers;

use Illuminate\Support\ServiceProvider;
use Chronhub\Storm\Contracts\Message\UniqueId;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Storm\Contracts\Message\MessageAlias;
use Illuminate\Contracts\Support\DeferrableProvider;
use Chronhub\Storm\Contracts\Serializer\ContentSerializer;
use Chronhub\Storm\Contracts\Serializer\MessageSerializer;
use Chronhub\Storm\Contracts\Message\MessageFactory as Factory;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use function in_array;
use function array_map;

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

        $this->app->singleton(
            SystemClock::class,
            fn (Application $app): SystemClock => $app[config('messager.clock')]
        );

        $this->app->singleton(
            Factory::class,
            fn (Application $app): Factory => $app[config('messager.factory')]
        );

        if (in_array('serializer.normalizer.event_time', config('messager.serializer.normalizers'))) {
            $this->app->singleton('serializer.normalizer.event_time', function (Application $app): NormalizerInterface {
                return new DateTimeNormalizer([
                    DateTimeNormalizer::FORMAT_KEY => $app[SystemClock::class]->getFormat(),
                    DateTimeNormalizer::TIMEZONE_KEY => 'UTC',
                ]);
            });
        }

        $this->app->singleton(MessageSerializer::class, function (Application $app): MessageSerializer {
            $normalizers = array_map(
                fn (string $normalizer): NormalizerInterface => $app[$normalizer],
                config('messager.serializer.normalizers')
            );

            $concrete = config('messager.serializer.concrete');

            $contentSerializer = null;
            if ($app->bound(ContentSerializer::class)) {
                $contentSerializer = $app[ContentSerializer::class];
            }

            return new $concrete($contentSerializer, ...$normalizers);
        });

        $this->app->singleton(
            MessageAlias::class,
            fn (Application $app): MessageAlias => $app[config('messager.alias')]
        );

        $this->app->singleton(UniqueId::class, config('messager.unique_id'));
    }

    public function provides(): array
    {
        return [
            Factory::class,
            'serializer.normalizer.event_time',
            MessageSerializer::class,
            MessageAlias::class,
            UniqueId::class,
        ];
    }
}
