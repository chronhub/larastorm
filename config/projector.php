<?php

declare(strict_types=1);

use Chronhub\Storm\Contracts\Projector\ProjectorOption;

return [

    'defaults' => [
        'projector' => 'connection',
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Stream Providers
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
    |       dispatcher:     dispatch laravel events when projection status changed
    |       options:        options key
    |       provider:       projection provider key
    |       scope:          projection query scope
    */

    'projectors' => [

        'connection' => [

            'default' => [
                'chronicler' => ['connection', 'write'],
                'dispatcher' => true,
                'options' => 'default',
                'provider' => 'eloquent',
                'scope' => \Chronhub\Larastorm\Projection\ConnectionProjectionQueryScope::class,
            ],

            'emit' => [
                'chronicler' => ['connection', 'read'],
                'dispatcher' => true,
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
    | Options can be an array or a string service class/id implementing projector option contract
    | not that a class or service id is immutable
    |
    | @see \Chronhub\Storm\Projector\Options\DefaultProjectorOption
    */

    'options' => [

        'default' => [],

        'lazy' => [
            ProjectorOption::SIGNAL => true,
            ProjectorOption::LOCKOUT => 500000,
            ProjectorOption::SLEEP => 100000,
            ProjectorOption::BLOCK_SIZE => 1000,
            ProjectorOption::TIMEOUT => 10000,
            ProjectorOption::RETRIES => '50, 1000, 50',
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

        'commands' => [
            \Chronhub\Larastorm\Support\Console\ReadProjectionCommand::class,
            \Chronhub\Larastorm\Support\Console\WriteProjectionCommand::class,
            \Chronhub\Larastorm\Support\Console\Generator\MakePersistentProjectionCommand::class,
            \Chronhub\Larastorm\Support\Console\Generator\MakeReadModelProjectionCommand::class,
            \Chronhub\Larastorm\Support\Console\Generator\MakeQueryProjectionCommand::class,
            \Chronhub\Larastorm\Support\Supervisor\Command\SuperviseProjectionCommand::class,
            \Chronhub\Larastorm\Support\Supervisor\Command\CheckSupervisedProjectionStatusCommand::class,
        ],
    ],
];
