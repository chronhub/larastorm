<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Persistence;

use Chronhub\Storm\Stream\StreamName;
use Illuminate\Support\Facades\Schema;
use Chronhub\Storm\Reporter\DomainEvent;
use Illuminate\Database\Schema\Blueprint;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Storm\Contracts\Serializer\StreamEventConverter;

final class SingleStreamPersistence implements StreamPersistence
{
    /**
     * Index name
     */
    private string $indexQuery = 'ix_query_aggregate';

    public function __construct(private readonly StreamEventConverter $convertEvent)
    {
    }

    public function tableName(StreamName $streamName): string
    {
        return '_'.$streamName->name;
    }

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
            $table->index(['aggregate_type', 'aggregate_id', 'no'], $tableName.'_'.$this->indexQuery);
        });

        return null;
    }

    public function serializeEvent(DomainEvent $event): array
    {
        return $this->convertEvent->toArray($event, true);
    }

    public function isAutoIncremented(): bool
    {
        return true;
    }

    public function indexName(string $tableName): ?string
    {
        return $this->indexQuery;
    }
}
