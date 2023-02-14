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
        | Event Decorators
        |--------------------------------------------------------------------------
        |
        | if false, you must provide decorators in next event decorators
        | Decorate domain event/ aggregate changed for each AR
        | merge with messager and aggregate decorators
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
                 * Return a default generic aggregate repository
                 */
                'repository' => 'generic',

                /**
                 * Chronicler use by your aggregate repository
                 *
                 * provide a service id or array member
                 * with name and provider key from chronicler config
                 */
                'chronicler' => ['write', 'connection'],

                /**
                 * It must match the chronicler producer strategy
                 */
                'strategy' => 'single',

                /**
                 * Specify your aggregate root class as string or
                 * an array with your aggregate root class and his subclasses
                 */
                'aggregate_type' => [
                    'root' => 'AG class name',
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
                     *  null tag will provide a default tag like {identity-my_stream_name}
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

                /**
                 * Aggregate snapshot
                 *
                 * array can also be removed instead of using false
                 */
                'snapshot' => [
                    /**
                     * Enable snapshot
                     */
                    'use_snapshot' => false,

                    /**
                     * Snapshot stream name
                     *
                     * determine your own snapshot stream name
                     * nullable stream name will provide by default {my_stream_name_snapshot}
                     */
                    'stream_name' => null,

                    /**
                     * Snapshot store service id
                     *
                     * must be a service registered in ioc
                     *
                     * @see '\Chronhub\Snapshot\Store\SnapshotStore'
                     */
                    'store' => 'snapshot.store.service.id',

                    /**
                     * Snapshot Aggregate Repository
                     */
                    'repository' => 'snapshot',

                    /**
                     * Persist snapshot every x events
                     */
                    'persist_every_x_events' => 1000,

                    /**
                     * Projector name would be used to take snapshot
                     */
                    'projector' => 'emit',
                ],
            ],
        ],
    ],
];
