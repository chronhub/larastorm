<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AGGREGATE REPOSITORY
    |--------------------------------------------------------------------------
    |
    */

    'repository' => [

        /*
        |--------------------------------------------------------------------------
        | Stream event Decorators
        |--------------------------------------------------------------------------
        |
        | if false, you must provide decorators in next event decorators
        |
        */

        'use_messager_decorators' => true,

        'event_decorators' => [],

        /*
        |--------------------------------------------------------------------------
        | Aggregate Repository
        |--------------------------------------------------------------------------
        |
        | Each aggregate repository is defined by his stream name
        |
        */

        'repositories' => [

            /**
             * Stream name
             */
            'my_stream_name' => [

                /**
                 * Available keys type:
                 *
                 *  generic  : will return a generic aggregate repository
                 *  extended : will return an extended aggregate repository with your fqn class
                 *
                 * @see \Chronhub\Storm\Aggregate\AggregateRepository
                 * @see \Chronhub\Storm\Aggregate\AbstractAggregateRepository
                 */
                'type' => [
                    'alias' => 'generic',
                    // 'concrete' => 'concrete for extended'
                ],

                /**
                 * Chronicler use by your aggregate repository
                 *
                 * provide a service id or array member
                 * with driver and name key from chronicler config
                 */
                'chronicler' => ['connection', 'write'],

                /**
                 * It must match the chronicler stream persistence
                 *      single stream producer => single stream persistence
                 *      one stream per aggregate producer => per aggregate stream persistence
                 */
                'strategy' => 'single',

                /**
                 * Specify your aggregate root class as string or
                 * an array with your aggregate root class and his subclasses
                 */
                'aggregate_type' => [
                    'root' => 'aggregate root class name',
                    'lineage' => [],
                ],

                /**
                 * Laravel cache config
                 *
                 * @see \Chronhub\Storm\Contracts\Aggregate\AggregateCache
                 */
                'cache' => [
                    // 0 to disable or remove key size|cache
                    'size' => 0,

                    /**
                     *  Unique Cache tag name per stream name
                     *  null tag will provide a default tag like {aggregate-aggregate_root_base_name}
                     */
                    'tag' => null,

                    /**
                     * Laravel default cache driver if null
                     * or a valid laravel cache driver which support tags
                     */
                    'driver' => null,
                ],

                /**
                 * Aggregate Event decorators specific for this repository
                 * merge with event decorators above
                 */
                'event_decorators' => [],
            ],
        ],
    ],
];
