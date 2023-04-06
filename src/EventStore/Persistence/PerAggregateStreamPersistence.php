<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Persistence;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class PerAggregateStreamPersistence extends AbstractStreamPersistence
{
    public function up(string $tableName): ?callable
    {
        Schema::create($tableName, static function (Blueprint $table): void {
            $table->id('no');
            $table->uuid('event_id');
            $table->string('event_type');
            $table->json('content');
            $table->jsonb('headers');
            $table->uuid('aggregate_id');
            $table->string('aggregate_type');
            $table->bigInteger('aggregate_version')->unique();
            $table->timestampTz('created_at', 6);
        });

        return null;
    }

    public function isAutoIncremented(): bool
    {
        return false;
    }
}
