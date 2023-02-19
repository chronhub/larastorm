<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Persistence;

use Illuminate\Database\Connection;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Support\Facades\Schema;
use Chronhub\Storm\Reporter\DomainEvent;
use Illuminate\Database\Schema\Blueprint;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Storm\Contracts\Serializer\StreamEventConverter;

final class PgsqlSingleStreamPersistence implements StreamPersistence
{
    public function __construct(private readonly StreamEventConverter $convertEvent)
    {
    }

    public function tableName(StreamName $streamName): string
    {
        return '_'.$streamName->name;
    }

    public function up(string $tableName): ?callable
    {
        Schema::create($tableName, function (Blueprint $table): void {
            $table->id('no');
            $table->uuid('event_id')->unique();
            $table->string('event_type');
            $table->json('content');
            $table->jsonb('headers');
            $table->uuid('aggregate_id');
            $table->string('aggregate_type');
            $table->bigInteger('aggregate_version');
            $table->timestampTz('created_at', 6);

            $table->unique(['aggregate_type', 'aggregate_id', 'aggregate_version']);
            $table->index(['aggregate_type', 'aggregate_id', 'no']);
        });

        return function (Connection $connection) use ($tableName): void {
            $connection->statement(
                'ALTER TABLE '.$tableName.' ADD CONSTRAINT aggregate_version_not_null CHECK ( (headers->\'__aggregate_version\') is not null )'
            );

            $connection->statement(
                'ALTER TABLE '.$tableName.' ADD CONSTRAINT aggregate_type_not_null CHECK ( (headers->\'__aggregate_type\') is not null )'
            );

            $connection->statement(
                'ALTER TABLE '.$tableName.' ADD CONSTRAINT aggregate_id_not_null CHECK ( (headers->\'__aggregate_id\') is not null )'
            );
        };
    }

    public function serializeEvent(DomainEvent $event): array
    {
        return $this->convertEvent->toArray($event, true);
    }

    public function isAutoIncremented(): bool
    {
        return true;
    }
}
