<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Persistence;

use Chronhub\Storm\Contracts\Stream\StreamPersistenceWithQueryHint;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class MysqlSingleStreamPersistence extends AbstractStreamPersistence implements StreamPersistenceWithQueryHint
{
    final public const QUERY_INDEX = 'ix_query_aggregate';

    public function up(string $tableName): ?callable
    {
        Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
            $table->id('no');
            $table->uuid('event_id')->unique();
            $table->string('event_type');
            $table->json('content');
            $table->jsonb('headers');
            $table->uuid('aggregate_id');
            $table->string('aggregate_type');
            $table->bigInteger('aggregate_version');
            $table->timestampTz('created_at', 6);

            $table->unique(['aggregate_type', 'aggregate_id', 'aggregate_version'], $tableName.'_ix_unique_event');
            $table->index(['aggregate_type', 'aggregate_id', 'no'], $tableName.'_'.self::QUERY_INDEX);
        });

        return null;
    }

    public function isAutoIncremented(): bool
    {
        return true;
    }

    public function indexName(string $tableName): string
    {
        return $tableName.'_'.self::QUERY_INDEX;
    }
}
