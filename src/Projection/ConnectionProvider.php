<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use StdClass;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Chronhub\Storm\Contracts\Projector\ProjectionModel;
use Chronhub\Storm\Contracts\Projector\ProjectionProvider;

final readonly class ConnectionProvider implements ProjectionProvider
{
    final public const TABLE_NAME = 'projections';

    private string $tableName;

    public function __construct(private Connection $connection, ?string $tableName = null)
    {
        $this->tableName = $tableName ?? self::TABLE_NAME;
    }

    public function createProjection(string $projectionName, string $status): bool
    {
        $projection = new Projection($projectionName, $status, null, null, null);

        return $this->newQuery()->insert($projection->jsonSerialize());
    }

    public function updateProjection(string $projectionName, array $data): bool
    {
        return $this->newQuery()->where('name', $projectionName)->update($data) === 1;
    }

    public function deleteProjection(string $projectionName): bool
    {
        return $this->newQuery()->where('name', $projectionName)->delete() === 1;
    }

    public function acquireLock(string $projectionName, string $status, string $lockedUntil, string $datetime): bool
    {
        $query = $this->newQuery()
            ->where('name', $projectionName)
            ->where(static function (Builder $query) use ($datetime): void {
                $query->whereRaw('locked_until IS NULL OR locked_until < ?', [$datetime]);
            })->update([
                'status' => $status,
                'locked_until' => $lockedUntil,
            ]);

        return $query === 1;
    }

    public function retrieve(string $projectionName): ?ProjectionModel
    {
        $result = $this->newQuery()->where('name', $projectionName)->first();

        if ($result === null) {
            return null;
        }

        if ($result instanceof StdClass) {
            $result = (array) $result;
        }

        return new Projection(
            $result['name'], $result['status'], $result['position'],
            $result['state'], $result['locked_until']);
    }

    public function filterByNames(string ...$names): array
    {
        return $this->newQuery()
            ->whereIn('name', $names)
            ->orderBy('name')
            ->pluck('name')
            ->toArray();
    }

    public function exists(string $projectionName): bool
    {
        return $this->newQuery()->where('name', $projectionName)->exists();
    }

    private function newQuery(): Builder
    {
        return $this->connection->table($this->tableName);
    }
}
