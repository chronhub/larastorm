<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | System Clock
    |--------------------------------------------------------------------------
    |
    | Default provide datetime immutable and UTC Timezone which is basically a requirement
    | It use the monotonic clock from Symfony to generate time instance
    |
    */

    'clock' => \Chronhub\Storm\Clock\PointInTime::class,

    /*
    |--------------------------------------------------------------------------
    | Unique identifier
    |--------------------------------------------------------------------------
    |
    | The default provided only generate string and should be replaced
    | by extending the UniqueId interface to create instance
    | @see \Chronhub\Storm\Contracts\Message\UniqueId
    |
    | You should also change the eventId in message decorator and adapt normalizers in serializer
    | to feat the package used
    */

    'unique_id' => \Chronhub\Larastorm\Support\UniqueId\UniqueIdV4::class,

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
    | The default provided only check if event name is a valid class name
    | two other types is provided:
    |       - AliasFromInflector \Foo\Bar\RegisterCustomer to "register-customer"
    |       - AliasFromMap  [your_service_name => FQCN, [...]]
    */

    'alias' => \Chronhub\Storm\Message\AliasFromClassName::class,

    /*
    |--------------------------------------------------------------------------
    | Message Serializer
    |--------------------------------------------------------------------------
    |
    | @see \Chronhub\Storm\Serializer\JsonSerializerFactory
    */

    'serializer' => [
        'normalizers' => [
            \Symfony\Component\Serializer\Normalizer\UidNormalizer::class,
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
