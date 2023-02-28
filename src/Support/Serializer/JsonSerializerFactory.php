<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Serializer;

use Closure;
use Symfony\Component\Serializer\Serializer;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Serializer\SerializeToJson;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Storm\Serializer\MessagingSerializer;
use Chronhub\Storm\Serializer\DomainEventSerializer;
use Chronhub\Storm\Serializer\MessagingContentSerializer;
use Chronhub\Storm\Contracts\Serializer\ContentSerializer;
use Chronhub\Storm\Contracts\Serializer\MessageSerializer;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use function array_map;
use function is_string;

final class JsonSerializerFactory
{
    protected Container $container;

    public function __construct(Closure $container)
    {
        $this->container = $container();
    }

    public function createForMessage(?ContentSerializer $contentSerializer = null,
                                       NormalizerInterface|DenormalizerInterface|string ...$normalizers): MessageSerializer
    {
        $symfonySerializer = $this->getSerializer(
            ...$this->resolveNormalizers(...$normalizers)
        );

        $contentSerializer ??= new MessagingContentSerializer();

        return new MessagingSerializer($contentSerializer, $symfonySerializer);
    }

    public function createForStream(?ContentSerializer $contentSerializer = null,
                                    NormalizerInterface|DenormalizerInterface|string ...$normalizers): StreamEventSerializer
    {
        $symfonySerializer = $this->getSerializer(
            ...$this->resolveNormalizers(...$normalizers)
        );

        $contentSerializer ??= new MessagingContentSerializer();

        return new DomainEventSerializer($contentSerializer, $symfonySerializer);
    }

    protected function dateTimeNormalizer(): DateTimeNormalizer
    {
        return new DateTimeNormalizer([
            DateTimeNormalizer::FORMAT_KEY => $this->container->make(SystemClock::class)->getFormat(),
            DateTimeNormalizer::TIMEZONE_KEY => 'UTC',
        ]);
    }

    protected function getSerializer(NormalizerInterface|DenormalizerInterface ...$normalizers): Serializer
    {
        $normalizers[] = $this->dateTimeNormalizer();

        return new Serializer($normalizers, [(new SerializeToJson())->getEncoder()]);
    }

    protected function resolveNormalizers(NormalizerInterface|DenormalizerInterface|string ...$normalizers): array
    {
        return array_map(function ($normalizer): NormalizerInterface|DenormalizerInterface {
            if (is_string($normalizer)) {
                return $this->container->make($normalizer);
            }

            return $normalizer;
        }, $normalizers);
    }
}
