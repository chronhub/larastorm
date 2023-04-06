<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Domain event serializer
    |--------------------------------------------------------------------------
    |
    */

    'event_serializer' => [
        'normalizers' => [
            \Symfony\Component\Serializer\Normalizer\UidNormalizer::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    */

    'defaults' => [
        'provider' => 'connection',

        /**
         * Connection is normally provided by the event store unless you switch to your own service id
         *
         * you can add a table key to change the table name but,
         * you should also change the migration and disable migration below
         *
         * also, your event stream table should use the same connection as your event store
         * (in case you use a different connection for query/write side)
         *
         * @see \Chronhub\Larastorm\EventStore\Persistence\EventStreamProvider::TABLE_NAME
         */
        'event_stream_provider' => [
            'connection' => [
                'name' => 'pgsql',
                'table_name' => 'event_streams',
            ],
        ],

        'providers' => [
            'connection' => \Chronhub\Larastorm\EventStore\EventStoreConnectionFactory::class,
            'in_memory' => \Chronhub\Storm\Chronicler\InMemory\InMemoryChroniclerFactory::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Store
    |--------------------------------------------------------------------------
    |
    |   store: laravel configuration connection name
    |
    |   cursor : CursorQueryLoader
    |           lazy: LazyQueryLoader or lazy:1000 to configure chunkSize
    |           or your own string service id
    |
    |   strategy : (required)
    |           available pgsql and mysql stream persistence
    |           or your own service id
    |
    |   write_lock : (optional : default fake write lock)
    |       true: use default write lock depends on driver
    |       false: a fake write lock
    |       string: your own service
    |
    |   is_transactional: (only for connection)
    |       required when you need a standalone/transactional (not eventable e.g. no stream tracker) chronicler instance
    |
    |   event_stream_provider: (only for connection)
    |       provide your own event stream provider key defined above or use the default connection
    */

    'providers' => [

        'connection' => [
            'publish' => [
                'store' => 'pgsql',
                'tracking' => [
                    'tracker_id' => \Chronhub\Storm\Chronicler\TrackTransactionalStream::class,
                    'subscribers' => [
                        \Chronhub\Storm\Publisher\EventPublisherSubscriber::class,
                    ],
                ],
                'write_lock' => true,
                'strategy' => \Chronhub\Larastorm\EventStore\Persistence\PgsqlSingleStreamPersistence::class,
                'query_loader' => 'cursor',
            ],

            'read' => [
                'store' => 'pgsql',
                'is_transactional' => false,
                'write_lock' => false,
                'strategy' => \Chronhub\Larastorm\EventStore\Persistence\PgsqlSingleStreamPersistence::class,
                'query_loader' => 'cursor',
            ],
        ],

        /**
         * In memory
         *
         * In memory driver keys are predefined
         * If you need your own in memory instance
         *      extend the manager
         *      or make your own chronicler provider
         */
        'in_memory' => [

            'standalone' => [],

            'transactional' => [],

            'eventable' => [
                'tracking' => [
                    'tracker_id' => \Chronhub\Storm\Chronicler\TrackStream::class,
                    'subscribers' => [],
                ],
            ],

            'transactional_eventable' => [
                'tracking' => [
                    'tracker_id' => \Chronhub\Storm\Chronicler\TrackTransactionalStream::class,
                    'subscribers' => [],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Console
    |--------------------------------------------------------------------------
    |
    */

    'console' => [
        'load_migration' => true,

        'commands' => [
            \Chronhub\Larastorm\Support\Console\CreateEventStreamCommand::class,
        ],
    ],
];
