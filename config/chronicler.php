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
        'concrete' => \Chronhub\Storm\Serializer\DomainEventSerializer::class,
        'normalizers' => [
            \Symfony\Component\Serializer\Normalizer\UidNormalizer::class,
            'serializer.normalizer.event_time',
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
        'providers' => [
            'connection' => \Chronhub\Larastorm\EventStore\ConnectionChroniclerProvider::class,
            'in_memory' => \Chronhub\Storm\Chronicler\InMemory\InMemoryChroniclerProvider::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Store
    |--------------------------------------------------------------------------
    |
    |   query loader: (optional : default cursor)
    |       cursor: CursorQueryLoader
    |       lazy: LazyQueryLoader or lazy:1000 to configure chunkSize
    |       or your own string service id
    |
    |   strategy: (required)
    |       single: single stream persistence
    |       single_indexed: single stream persistence (only for mysql)
    |       per_aggregate: per aggregate stream persistence
    |
    |   write_lock: (optional : default fake write lock)
    |       true: use default write lock depends on driver
    |       false: a fake write lock
    |       string: your own service
    |
    |   is_transactional: (only for connection)
    |       required when you need a standalone/transactional (not eventable e.g. no stream tracker) chronicler instance
    |
    */

    'providers' => [

        /**
         * Connection
         *
         * available pgsql and mysql with default mysql, mariadb, percona engines
         */
        'connection' => [
            'write' => [
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
