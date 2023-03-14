<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Persistence;

use Illuminate\Database\Connection;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\Query\Builder;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider as Provider;
use function array_map;

final readonly class EventStreamProvider implements Provider
{
    final public const TABLE_NAME = 'event_streams';

    private string $tableName;

    public function __construct(private Connection $connection, ?string $tableName = null)
    {
        $this->tableName = $tableName ?? self::TABLE_NAME;
    }

    public function createStream(string $streamName, ?string $tableName, ?string $category = null): bool
    {
        $eventStream = new EventStream($streamName, $tableName, $category);

        return $this->newQuery()->insert($eventStream->jsonSerialize());
    }

    public function deleteStream(string $streamName): bool
    {
        return 1 === $this->newQuery()->where('real_stream_name', $streamName)->delete();
    }

    public function filterByStreams(array $streamNames): array
    {
        return $this->newQuery()
            ->whereIn(
                'real_stream_name',
                array_map(
                    static fn (string|StreamName $streamName): string => $streamName instanceof StreamName ? $streamName->name : $streamName,
                    $streamNames)
            )
            ->orderBy('real_stream_name')
            ->get()
            ->pluck('real_stream_name')
            ->toArray();
    }

    public function filterByCategories(array $categoryNames): array
    {
        return $this->newQuery()
            ->whereIn('category', $categoryNames)
            ->orderBy('real_stream_name')
            ->get()
            ->pluck('real_stream_name')
            ->toArray();
    }

    public function allWithoutInternal(): array
    {
        return $this->newQuery()
            ->whereRaw("real_stream_name NOT LIKE '$%'")
            ->orderBy('real_stream_name')
            ->pluck('real_stream_name')
            ->toArray();
    }

    public function hasRealStreamName(string $streamName): bool
    {
        return $this->newQuery()->where('real_stream_name', $streamName)->exists();
    }

    private function newQuery(): Builder
    {
        return $this->connection->table($this->tableName);
    }
}
