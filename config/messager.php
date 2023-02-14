<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | System Clock
    |--------------------------------------------------------------------------
    |
    | Default use date time immutable and UTC Timezone
    | note it does not use laravel env timezone configuration
    |
    */

    'clock' => \Chronhub\Storm\Clock\PointInTime::class,

    /*
    |--------------------------------------------------------------------------
    | Unique identifier
    |--------------------------------------------------------------------------
    |
    */

    'unique_id' => \Chronhub\Larastorm\Support\UniqueId\UniqueId::class,

    /*
    |--------------------------------------------------------------------------
    | Message factory
    |--------------------------------------------------------------------------
    |
    | Message factory is responsible to transform events object|array
    | into a valid Message instance
    |
    | @see \Chronhub\Storm\Reporter\Subscribers\MakeMessage::class
    |
    */

    'factory' => \Chronhub\Storm\Message\MessageFactory::class,

    /*
    |--------------------------------------------------------------------------
    | Message alias
    |--------------------------------------------------------------------------
    |
    */

    'alias' => \Chronhub\Storm\Message\AliasFromClassName::class,

    /*
    |--------------------------------------------------------------------------
    | Message Serializer
    |--------------------------------------------------------------------------
    |
    */

    'serializer' => [
        'concrete' => \Chronhub\Storm\Serializer\MessagingSerializer::class,
        'normalizers' => [
            \Symfony\Component\Serializer\Normalizer\UidNormalizer::class,
            'serializer.normalizer.event_time', // bound and tied to system clock
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Decorator
    |--------------------------------------------------------------------------
    |
    */

    'decorators' => [
        \Chronhub\Larastorm\Support\MessageDecorator\EventId::class,
        \Chronhub\Larastorm\Support\MessageDecorator\EventTime::class,
        \Chronhub\Larastorm\Support\MessageDecorator\EventType::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Subscriber
    |--------------------------------------------------------------------------
    |
    */

    'subscribers' => [
        \Chronhub\Storm\Reporter\Subscribers\MakeMessage::class,
    ],
];
