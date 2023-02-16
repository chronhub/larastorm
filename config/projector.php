<?php

declare(strict_types=1);

use Chronhub\Storm\Contracts\Projector\ProjectorOption;

return [

    'defaults' => [
        'factory' => 'connection',
    ],

    /*
    |--------------------------------------------------------------------------
    | Projection providers
    |--------------------------------------------------------------------------
    |
    */

    'providers' => [
        'eloquent' => \Chronhub\Larastorm\Projection\Projection::class,
        'in_memory' => \Chronhub\Storm\Projector\Provider\InMemoryProjectionProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Projectors
    |--------------------------------------------------------------------------
    |
    | Each projector is bound to an event store
    |
    |       chronicler:     chronicler configuration keys or service registered in ioc
    |       options:        options key
    |       provider:       projection provider key
    |       scope:          projection query scope
    */

    'projectors' => [

        'connection' => [

            'default' => [
                'chronicler' => ['connection', 'write'],
                'options' => 'default',
                'provider' => 'eloquent',
                'scope' => \Chronhub\Larastorm\Projection\ConnectionProjectionQueryScope::class,
            ],

            'emit' => [
                'chronicler' => ['connection', 'read'],
                'options' => 'default',
                'provider' => 'eloquent',
                'scope' => \Chronhub\Larastorm\Projection\ConnectionProjectionQueryScope::class,
            ],
        ],

        'in_memory' => [
            'testing' => [
                'chronicler' => ['in_memory', 'standalone'],
                'provider' => 'in_memory',
                'options' => 'in_memory',
                'scope' => \Chronhub\Storm\Projector\InMemoryProjectionQueryScope::class,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Projector options
    |--------------------------------------------------------------------------
    |
    | Options can be an array or a string service class/id implementing option contract
    |
    */

    'options' => [

        /**
         * @see \Chronhub\Storm\Projector\Options\DefaultProjectorOption
         */
        'default' => [],

        'lazy' => [
            ProjectorOption::UPDATE_LOCK_THRESHOLD => 500000,
            ProjectorOption::SLEEP_BEFORE_UPDATE_LOCK => 100000,
            ProjectorOption::PERSIST_BLOCK_SIZE => 1000,
            ProjectorOption::LOCK_TIMEOUT_MS => 10000,
            ProjectorOption::DISPATCH_SIGNAL => true,
            ProjectorOption::RETRIES_MS => '50, 1000, 50',
            ProjectorOption::DETECTION_WINDOWS => null,
        ],

        'in_memory' => \Chronhub\Storm\Projector\Options\InMemoryProjectorOption::class,

        'snapshot' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Console
    |--------------------------------------------------------------------------
    |
    */

    'console' => [

        'load_migrations' => true,

        'commands' => [],
    ],
];
