<?php

declare(strict_types=1);

return [

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
    |       - AliasFromMap  [your_service_name => FQN, [...]]
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
    | Message Decorators
    |--------------------------------------------------------------------------
    |
    */

    'decorators' => [
        \Chronhub\Storm\Message\Decorator\EventId::class,
        \Chronhub\Storm\Message\Decorator\EventTime::class,
        \Chronhub\Storm\Message\Decorator\EventType::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Subscribers
    |--------------------------------------------------------------------------
    |
    */

    'subscribers' => [
        \Chronhub\Storm\Reporter\Subscribers\MakeMessage::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Console
    |--------------------------------------------------------------------------
    |
    */

    'console' => [

        'commands' => [
            \Chronhub\Larastorm\Support\Console\ListMessagerSubscribersCommand::class,
        ],
    ],
];
