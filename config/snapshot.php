<?php

declare(strict_types=1);

return [

    'connection' => [

        'default' => 'pgsql',

        /**
         * Store snapshots in one table
         */
        'table_name' => 'snapshots',

        /**
         * Store snapshots per stream name
         *
         * ['fqn_aggregate_type' => 'stream_name']
         *
         * Note AR lineage must be included too if used.
         * Migration command will care of mounting one table only.
         */
        'mapping_tables' => false,

        /**
         * Snapshot table suffix
         *
         * stream_name + suffix = snapshot table name
         */
        'suffix' => '_snapshot',

        'query_scope' => \Chronhub\Larastorm\Snapshot\ConnectionSnapshotQueryScope::class,

        'serializer' => \Chronhub\Storm\Snapshot\Base64EncodeSerializer::class,

        'console' => [
            // for one table
            'load_migration' => true,

            // for mapping tables
            'commands' => [
                \Chronhub\Larastorm\Snapshot\SnapshotMappingTablesMigrationCommand::class,
            ],
        ],
    ],

    'in_memory' => [
        'query_scope' => \Chronhub\Storm\Snapshot\InMemorySnapshotQueryScope::class,
    ],
];
