<?php

declare(strict_types=1);

use Chronhub\Storm\Contracts\Projector\ProjectorOption;

return [

    'defaults' => [
        'projector' => 'connection',
    ],

    /*
    |--------------------------------------------------------------------------
    | Projection Providers
    |--------------------------------------------------------------------------
    |
    | connection : use db connection to store projection
    |
    |   if you only used one projection provider across all projectors,
    |   you should bind it and pass your service id to the connection.
    |   note: if you change the table name, you should also change the migration and
    |   disable the migration below
    |
    | in_memory : use in memory projection
    |
    */

    'providers' => [
        'connection' => [
            'name' => 'pgsql',
            'table' => 'projections',
        ],
        'in_memory' => \Chronhub\Storm\Projector\Provider\InMemoryProjectionProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Projectors
    |--------------------------------------------------------------------------
    |
    | Each projector is bound to an event store
    |
    |   chronicler:     chronicler configuration keys or better, an event store service registered in ioc
    |   dispatcher:     dispatch laravel events when projection status changed
    |   options:        options key
    |   provider:       projection provider key
    |   scope:          projection query scope
    */

    'projectors' => [

        'connection' => [

            'default' => [
                'chronicler' => ['connection', 'publish'],
                'dispatcher' => true,
                'options' => 'default',
                'provider' => 'connection',
                'scope' => \Chronhub\Larastorm\Projection\ConnectionQueryScope::class,
            ],

            'emit' => [
                'chronicler' => ['connection', 'read'],
                'dispatcher' => true,
                'options' => 'default',
                'provider' => 'connection',
                'scope' => \Chronhub\Larastorm\Projection\ConnectionQueryScope::class,
            ],
        ],

        'in_memory' => [
            'testing' => [
                'chronicler' => ['in_memory', 'standalone'],
                'provider' => 'in_memory',
                'options' => 'in_memory',
                'scope' => \Chronhub\Storm\Projector\InMemoryQueryScope::class,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Projector options
    |--------------------------------------------------------------------------
    |
    | Options can be an array or a string service class/id implementing projector option contract
    | not that a class or service id is immutable and, you won't be able to change it when running
    | a projection, for example, from a console command.
    |
    | note that array will be merged into a default projector option instance
    | @see \Chronhub\Storm\Projector\Options\DefaultProjectorOption
    |
    |   Signal              :  dispatch async signal
    |   Lockout             :  lock threshold incrementation in milliseconds
    |   Timeout             :  lock timeout in milliseconds
    |   Sleep               :  time in milliseconds to sleep before updating lock
    |   Block size          :  number of stream events to be persisted before ending a cycle
    |   Retries             :  an array of retries in milliseconds or a string of retries separated by comma,
    |                          representing arguments of the range function
    |   Detection windows   :  a valid string interval, for example, 'PT1H' or 'PT1M'
    |                          Use when resetting projection to avoid gap detection which past the detection windows,
    |                          compared to stream event time and present time
    |                          note that an empty retries options will make this option useless
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
