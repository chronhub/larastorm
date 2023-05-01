<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Snapshot;

use Chronhub\Larastorm\Exceptions\ConnectionQueryFailure;
use Chronhub\Storm\Contracts\Aggregate\AggregateRootWithSnapshotting;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Storm\Contracts\Snapshot\SnapshotSerializer;
use Chronhub\Storm\Contracts\Snapshot\SnapshotStore;
use Chronhub\Storm\Snapshot\Snapshot;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use RuntimeException;
use stdClass;
use function array_map;
use function is_resource;
use function stream_get_contents;

final readonly class ConnectionSnapshotStore implements SnapshotStore
{
    /**
     * @var array<class-string, string>
     */
    private array $mappingTables;

    public function __construct(
        private Connection $connection,
        private SnapshotSerializer $snapshotSerializer,
        private SystemClock $clock,
        private ?string $suffix,
        private ?string $tableName,
        array $mappingTables = [],
    ) {
        if (null === $tableName && $mappingTables === []) {
            throw new RuntimeException('Snapshot table name not configured');
        }

        // checkMe we could use both for whatever reason
        // by now, we restrict to one or the other
        if ($tableName && $mappingTables !== []) {
            throw new RuntimeException('Snapshot table name and mapping tables are mutually exclusive');
        }

        if ($mappingTables !== [] && $this->suffix !== null) {
            $mappingTables = array_map(function ($table) {
                return $table.$this->suffix;
            }, $mappingTables);
        }

        $this->mappingTables = $mappingTables;
    }

    public function get(string $aggregateType, string $aggregateId): ?Snapshot
    {
        $result = null;

        try {
            $result = $this->queryBuilder($aggregateType)
                ->where('aggregate_type', $aggregateType)
                ->where('aggregate_id', $aggregateId)
                ->first();
        } catch (QueryException $queryException) {
            if ($queryException->getCode() !== '00000') {
                throw ConnectionQueryFailure::fromQueryException($queryException);
            }
        }

        if ($result instanceof stdClass) {
            $result = (array) $result;
        }

        if ($result === null || $result === []) {
            return null;
        }

        return new Snapshot(
            $aggregateId,
            $aggregateType,
            $this->deserializeAggregate($result['aggregate_root']),
            (int) $result['last_version'],
            new DateTimeImmutable($result['created_at'], new DateTimeZone('UTC')),
        );
    }

    public function save(Snapshot ...$snapshots): void
    {
        if ($snapshots === []) {
            return;
        }

        try {
            $this->connection->transaction(function () use ($snapshots): void {
                foreach ($snapshots as $snapshot) {
                    $this->queryBuilder($snapshot->aggregateType)->updateOrInsert(
                        [
                            'aggregate_id' => $snapshot->aggregateId,
                            'aggregate_type' => $snapshot->aggregateType,
                        ],
                        $this->normalizeSnapshot($snapshot)
                    );
                }
            });
        } catch (QueryException $queryException) {
            throw ConnectionQueryFailure::fromQueryException($queryException);
        }
    }

    public function deleteByAggregateType(string $aggregateType): void
    {
        try {
            $this->connection->transaction(function () use ($aggregateType): void {
                $this->queryBuilder($aggregateType)->where('aggregate_type', $aggregateType)->delete();
            });
        } catch (QueryException $queryException) {
            throw ConnectionQueryFailure::fromQueryException($queryException);
        }
    }

    private function normalizeSnapshot(Snapshot $snapshot): array
    {
        return [
            'aggregate_id' => $snapshot->aggregateId,
            'aggregate_type' => $snapshot->aggregateType,
            'aggregate_root' => $this->snapshotSerializer->serialize($snapshot->aggregateRoot),
            'last_version' => $snapshot->lastVersion,
            'created_at' => $this->clock->now()->format($this->clock->getFormat()),
        ];
    }

    private function deserializeAggregate($serialized): AggregateRootWithSnapshotting
    {
        if (is_resource($serialized)) {
            $serialized = stream_get_contents($serialized);
        }

        return $this->snapshotSerializer->deserialize($serialized);
    }

    private function queryBuilder(string $aggregateType): Builder
    {
        if ($this->mappingTables === []) {
            return $this->connection->table($this->tableName);
        }

        $tableName = $this->mappingTables[$aggregateType] ?? null;

        if ($tableName === null) {
            throw new RuntimeException("No mapping table found for aggregate type $aggregateType");
        }

        return $this->connection->table($tableName);
    }
}
